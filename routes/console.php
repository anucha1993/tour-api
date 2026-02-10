<?php

use App\Jobs\AutoCloseExpiredJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-close expired periods and tours - runs daily at 1:00 AM
Schedule::job(new AutoCloseExpiredJob())->dailyAt('01:00')
    ->name('auto-close-expired')
    ->withoutOverlapping()
    ->onOneServer();

// Artisan command to run auto-close manually (global setting)
Artisan::command('tours:auto-close', function () {
    $this->info('Running auto-close for expired periods and tours (global mode)...');
    
    AutoCloseExpiredJob::dispatch();
    
    $this->info('Auto-close job dispatched.');
})->purpose('Auto-close expired periods and tours based on global settings');
