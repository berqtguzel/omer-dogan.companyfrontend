<?php

namespace App\Http\Controllers;

use App\Support\OmrConfig;
use App\Support\Omr\OmrCachedClient;
use App\Mail\ContactFormMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ContactFormController extends Controller
{
    public static function getForms(string $locale = 'de')
    {
        $mainTenant = OmrConfig::tenantForSharedContent();
        $locale = strtolower(trim($locale)) ?: 'de';
        $cacheKey = "contact_forms_v2_{$mainTenant}_{$locale}";
        $staleKey = "contact_forms_v2_stale_{$mainTenant}_{$locale}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            return collect($cached);
        }

        $stale = Cache::get($staleKey);

        if (OmrCachedClient::isCoolingDown($mainTenant)) {
            return collect(is_array($stale) && $stale !== [] ? $stale : self::fallbackForms());
        }

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Tenant-ID' => $mainTenant,
                ])
                ->get(OmrConfig::apiUrl('contact/forms'), [
                    'tenant' => $mainTenant,
                    'locale' => $locale,
                ]);

            if (! $response->successful()) {
                if ($response->serverError() || $response->status() === 429) {
                    OmrCachedClient::markCoolingDown($mainTenant, 2);
                }

                Log::warning('ContactForms API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $fallback = is_array($stale) && $stale !== [] ? $stale : self::fallbackForms();
                Cache::put($cacheKey, $fallback, now()->addMinutes(5));

                return collect($fallback);
            }

            $forms = self::normalizeForms($response->json()['data'] ?? []);

            if ($forms === []) {
                Cache::put($cacheKey, self::fallbackForms(), now()->addMinutes(5));

                return collect(is_array($stale) && $stale !== [] ? $stale : self::fallbackForms());
            }

            Cache::put($cacheKey, $forms, now()->addDays(7));
            Cache::put($staleKey, $forms, now()->addDays(30));

            return collect($forms);
        } catch (\Throwable $e) {
            OmrCachedClient::markCoolingDown($mainTenant, 2);

            Log::error('ContactForms Fetch Error: '.$e->getMessage());

            $fallback = is_array($stale) && $stale !== [] ? $stale : self::fallbackForms();
            Cache::put($cacheKey, $fallback, now()->addMinutes(5));

            return collect($fallback);
        }
    }

    private static function normalizeForms($data): array
    {
        return collect(is_array($data) ? $data : [])->map(fn ($form, $index) => [
            'id' => $form['id'] ?? $index,
            'name' => $form['name'] ?? "Form #{$index}",
            'fields' => collect($form['fields'] ?? [])->map(fn ($field, $fieldIndex) => [
                'id' => $field['id'] ?? $fieldIndex,
                'name' => $field['name'] ?? "field_{$fieldIndex}",
                'label' => $field['label'] ?? 'Field',
                'type' => strtolower($field['type'] ?? 'text'),
                'required' => ! empty($field['required']),
                'placeholder' => $field['placeholder'] ?? '',
                'options' => $field['options'] ?? [],
            ])->values()->all(),
        ])->filter(fn ($form) => $form['fields'] !== [])->values()->all();
    }

    private static function fallbackForms(): array
    {
        return [[
            'id' => 1,
            'name' => 'Contact',
            'fields' => [
                ['id' => 0, 'name' => 'field_0', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => '', 'options' => []],
                ['id' => 1, 'name' => 'field_1', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'placeholder' => '', 'options' => []],
                ['id' => 2, 'name' => 'field_2', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'placeholder' => '', 'options' => []],
                ['id' => 3, 'name' => 'field_3', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'placeholder' => '', 'options' => []],
            ],
        ]];
    }

    public function submit(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $locale = $request->input('locale', 'de');
        $mainTenant = OmrConfig::tenantForSharedContent();

        try {
            $url = OmrConfig::apiUrl("contact/forms/{$id}/submit");

            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Tenant-ID' => $mainTenant,
                ])
                ->post($url, [
                    'tenant' => $mainTenant,
                    'locale' => $locale,
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                    'message' => $validated['message'],
                ]);
            $json = $response->json() ?? [];


            if ($response->status() === 422) {
                return response()->json([
                    "message" => $json["message"] ?? "Validation failed",
                    "errors" => $json["errors"] ?? $json,
                ], 422);
            }

            if (!$response->successful()) {
                return response()->json($json ?: [
                    'message' => 'Submit failed',
                ], $response->status());
            }

            // The contact has already been persisted by the upstream API at this
            // point. A notification e-mail failure must not turn that successful
            // submission into a 500 response and encourage duplicate submissions.
            try {
                SettingsController::applyMailSettings(OmrConfig::tenantId(), $locale);

                Mail::to(config('mail.admin_email', config('mail.from.address')))
                    ->send(new ContactFormMail($validated));
            } catch (\Throwable $mailException) {
                Log::error('Contact notification mail failed', [
                    'form_id' => $id,
                    'message' => $mailException->getMessage(),
                ]);
            }

            return response()->json($json ?: [
                'message' => 'Submitted successfully',
            ], $response->status());

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("Contact Submit Error: " . $e->getMessage());
            return response()->json([
                "error" => "Submit failed",
                "debug" => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }
}
