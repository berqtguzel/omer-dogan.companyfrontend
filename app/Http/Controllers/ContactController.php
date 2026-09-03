<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactFormMail;
use App\Http\Requests\ContactFormRequest;
use App\Services\DashboardService;
use App\Support\OmrConfig;

class ContactController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }


    public function index()
    {
        $locale = strtolower((string) (request()->route('locale') ?: OmrConfig::defaultLocale()));

        return inertia('kontakt/index', [
            'forms' => ContactFormController::getForms($locale)->values()->all(),
        ]);
    }


    public function submit(ContactFormRequest $request)
    {
        try {
            $response = $this->dashboardService->submitContact($request->validated());

            if (!$response) {
                throw new \Exception('Dashboard API error');
            }

            if (config('dashboard.send_backup_email', true)) {
                SettingsController::applyMailSettings(OmrConfig::tenantId(), session('locale', OmrConfig::defaultLocale()));

                Mail::to(config('mail.admin_email') ?: config('mail.from.address'))
                    ->send(new ContactFormMail($request->validated()));
            }

            return back()->with('success', 'Ihre Nachricht wurde erfolgreich gesendet.');
        } catch (\Exception $e) {
            report($e);
            return back()->with('error', 'Es gab einen Fehler beim Senden Ihrer Nachricht. Bitte versuchen Sie es später erneut.');
        }
    }
}
