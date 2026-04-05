<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('scheduler:heartbeat')]
#[Description('Log a heartbeat entry to verify the scheduler is running')]
class SchedulerHeartbeat extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Log::info('Scheduler heartbeat', [
            'timestamp' => now()->toDateTimeString(),
            'server' => gethostname(),
        ]);
    }
}
