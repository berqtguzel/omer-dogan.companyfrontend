<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PanelMailSettingsTest extends TestCase
{
    public function test_masked_password_is_replaced_from_private_panel_endpoint(): void
    {
        $tenant = 'panel_mail_test_tenant';

        config([
            'services.omr.base' => 'https://panel.test/api',
            'services.omr.api_version' => 2,
            'services.omr.tenant_id' => $tenant,
            'services.omr.main_tenant' => $tenant,
            'services.omr.dashboard_site_id' => null,
            'mail.mailers.smtp.password' => null,
        ]);

        Cache::flush();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/settings/smtp_password')) {
                return Http::response([
                    'success' => true,
                    'data' => [
                        'key' => 'smtp_password',
                        'value' => 'panel-secret',
                    ],
                ]);
            }

            if (str_contains($request->url(), '/settings/email')) {
                return Http::response(['data' => []]);
            }

            return Http::response([
                'data' => [
                    ['key' => 'smtp_host', 'value' => 'smtp.panel.test'],
                    ['key' => 'smtp_port', 'value' => '587'],
                    ['key' => 'smtp_username', 'value' => 'mailbox-user'],
                    ['key' => 'smtp_password', 'value' => '***'],
                    ['key' => 'smtp_encryption', 'value' => 'tls'],
                    ['key' => 'from_email', 'value' => 'mail@example.test'],
                ],
            ]);
        });

        SettingsController::applyMailSettings($tenant, 'de');

        $this->assertSame('panel-secret', config('mail.mailers.smtp.password'));
        $this->assertSame('smtp.panel.test', config('mail.mailers.smtp.host'));
        $this->assertSame('mailbox-user', config('mail.mailers.smtp.username'));

        Http::assertSent(fn ($request) => str_contains(
            $request->url(),
            '/api/v2/settings/smtp_password'
        ));
    }
}
