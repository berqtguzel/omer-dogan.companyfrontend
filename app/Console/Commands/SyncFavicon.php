<?php

namespace App\Console\Commands;

use App\Services\FaviconSyncService;
use Illuminate\Console\Command;

class SyncFavicon extends Command
{
    protected $signature = 'favicon:sync
                            {--locale= : Locale for API settings}
                            {--force : Download and overwrite even when unchanged}';

    protected $description = 'Download the tenant API favicon once and preserve its real public file format';

    public function handle(FaviconSyncService $faviconSync): int
    {
        $result = $faviconSync->sync($this->option('locale'), (bool) $this->option('force'));

        if (! $result['ok']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['changed']
            ? 'Tenant favicon written to public/'.basename((string) $result['path']).'.'
            : 'Tenant favicon is already up to date.');

        return self::SUCCESS;
    }
}
