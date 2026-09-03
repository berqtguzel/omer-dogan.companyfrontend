<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FetchApiData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-api-data';

    protected $description = 'Alias for media:sync (remote images to local storage)';

    public function handle(): int
    {
        return $this->call('media:sync');
    }
}
