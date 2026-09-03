<?php

namespace App\Console\Commands;

use App\Services\UpdateService;
use Illuminate\Console\Command;

class RunUpdate extends Command
{
    protected $signature = 'update:run {version=latest : Tag, commit hash or latest}';

    protected $description = 'Pull from GitHub, run composer/npm build, and refresh caches';

    public function handle(UpdateService $update): int
    {
        if (! $update->enabled()) {
            $this->error('Update disabled. Set UPDATE_ENABLED=true in .env');

            return self::FAILURE;
        }

        $version = (string) $this->argument('version');
        $this->info("Updating {$version} for ".config('app.url'));

        $result = $update->performUpdate($version);

        foreach ($result['logs'] ?? [] as $line) {
            $this->line($line);
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
