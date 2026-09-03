<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GlobalPanelReporter
{
    public function isConfigured(): bool
    {
        return config('global_panel.url') !== ''
            && config('global_panel.tenant_id') !== ''
            && config('global_panel.register_token') !== '';
    }

    /**
     * @return array{ok: bool, message: string, response?: array<string, mixed>}
     */
    public function sendHeartbeat(): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Missing GLOBAL_PANEL_URL, TENANT_ID, or FRONT_REGISTER_TOKEN in .env',
            ];
        }

        $deployPath = config('global_panel.deploy_path') ?: base_path();
        $deployPath = rtrim(str_replace('\\', '/', $deployPath), '/');

        $payload = [
            'tenant_id' => config('global_panel.tenant_id'),
            'token' => config('global_panel.register_token'),
            'deploy_path' => $deployPath,
            'app_url' => config('app.url'),
            'ssh_host' => $this->resolveSshHost(),
            'hostname' => gethostname() ?: php_uname('n'),
            'public_ip' => $this->resolvePublicIp(),
            'front_version' => $this->resolveFrontVersion(),
            'php_version' => PHP_VERSION,
        ];

        $url = config('global_panel.url').'/front/heartbeat';

        $request = Http::connectTimeout(5)
            ->timeout(20)
            ->acceptJson()
            ->withOptions(['verify' => ! app()->environment('local', 'development')]);

        $response = $request->post($url, $payload);

        if ($response->successful()) {
            $body = $response->json();

            return [
                'ok' => true,
                'message' => 'Heartbeat sent successfully.',
                'response' => is_array($body) ? $body : [],
            ];
        }

        $body = $response->json();
        $message = is_array($body) ? ($body['message'] ?? $response->body()) : $response->body();

        return [
            'ok' => false,
            'message' => 'Heartbeat failed ('.$response->status().'): '.$message,
        ];
    }

    protected function resolveSshHost(): ?string
    {
        $host = env('SSH_REPORT_HOST');
        if (is_string($host) && $host !== '') {
            return $host;
        }

        return $this->resolvePublicIp();
    }

    protected function resolvePublicIp(): ?string
    {
        $ip = env('SERVER_PUBLIC_IP');
        if (is_string($ip) && $ip !== '') {
            return $ip;
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->withOptions(['verify' => ! app()->environment('local', 'development')])
                ->get('https://api.ipify.org?format=json');

            if ($response->successful()) {
                $data = $response->json();

                return is_array($data) ? ($data['ip'] ?? null) : null;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    protected function resolveFrontVersion(): ?string
    {
        $versionFile = base_path('version.json');
        if (is_file($versionFile)) {
            $data = json_decode((string) file_get_contents($versionFile), true);
            if (is_array($data) && ! empty($data['version'])) {
                return (string) $data['version'];
            }
        }

        $envVersion = env('FRONT_VERSION');
        if (is_string($envVersion) && $envVersion !== '') {
            return $envVersion;
        }

        return null;
    }
}
