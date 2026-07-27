<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetAdmin extends Command
{
    protected $signature = 'admin:reset';

    protected $description = 'Remove all administrator account rows';

    public function handle(): int
    {
        $userCount = User::query()->count();

        if ($userCount === 0) {
            $this->info('No administrator account rows exist.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Remove all {$userCount} administrator account row(s)?")) {
            $this->info('Administrator reset cancelled.');

            return self::SUCCESS;
        }

        User::query()->delete();

        $this->info('Administrator account rows cleared. If authentication is enabled, the next request will open setup.');

        return self::SUCCESS;
    }
}
