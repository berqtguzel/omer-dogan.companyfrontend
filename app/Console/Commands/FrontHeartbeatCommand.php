<?php

namespace App\Console\Commands;

use App\Services\GlobalPanelReporter;
use Illuminate\Console\Command;

class FrontHeartbeatCommand extends Command
{
    protected $signature = 'front:heartbeat';

    protected $description = 'Report deploy path and host info to the central tenant panel';

    public function handle(GlobalPanelReporter $reporter): int
    {
        $this->line('Tenant: '.config('global_panel.tenant_id'));
        $this->line('Panel: '.config('global_panel.url'));
        $this->line('Deploy path: '.(config('global_panel.deploy_path') ?: base_path()));

        $result = $reporter->sendHeartbeat();

        if ($result['ok'] ?? false) {
            $this->info($result['message']);
            if (! empty($result['response'])) {
                $this->line(json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        }

        $this->error($result['message'] ?? 'Heartbeat failed.');

        return self::FAILURE;
    }
}
