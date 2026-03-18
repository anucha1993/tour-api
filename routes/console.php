<?php

use App\Jobs\AutoCloseExpiredJob;
use App\Models\SystemSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-close expired periods and tours - runs daily at configured time from UI
$autoCloseRunTime = rescue(fn () => SystemSetting::getValue('auto_close.run_time', '01:00'), '01:00', false);
Schedule::job(new AutoCloseExpiredJob())->dailyAt($autoCloseRunTime)
    ->name('auto-close-expired')
    ->withoutOverlapping()
    ->onOneServer();

// Expire member points - runs daily at 2:00 AM
Schedule::command('points:expire')->dailyAt('02:00')
    ->name('expire-member-points')
    ->withoutOverlapping()
    ->onOneServer();

// Artisan command to run auto-close manually (global setting)
Artisan::command('tours:auto-close', function () {
    $this->info('Running auto-close for expired periods and tours (global mode)...');
    
    AutoCloseExpiredJob::dispatch();
    
    $this->info('Auto-close job dispatched.');
})->purpose('Auto-close expired periods and tours based on global settings');

// FIX: Auto-heal stuck syncs ทุก 5 นาที
// ถ้า heartbeat หยุดเกิน 5 นาที → mark as failed + release cache lock
Schedule::call(function () {
    $stuckSyncs = \App\Models\SyncLog::where('status', 'running')
        ->where(function ($q) {
            $q->where(function ($q2) {
                $q2->whereNotNull('last_heartbeat_at')
                    ->where('last_heartbeat_at', '<', now()->subMinutes(5));
            })
            ->orWhere(function ($q2) {
                $q2->whereNull('last_heartbeat_at')
                    ->where('started_at', '<', now()->subMinutes(5));
            });
        })
        ->get();
    
    foreach ($stuckSyncs as $sync) {
        $sync->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_summary' => ['message' => 'Auto-healed by scheduler: heartbeat stopped'],
        ]);
        \Illuminate\Support\Facades\Cache::lock("sync_lock:wholesaler:{$sync->wholesaler_id}")->forceRelease();
        \Illuminate\Support\Facades\Log::warning('Scheduler: Auto-healed stuck sync', [
            'sync_log_id' => $sync->id,
            'wholesaler_id' => $sync->wholesaler_id,
        ]);
    }
})->everyFiveMinutes()
  ->name('auto-heal-stuck-syncs')
  ->withoutOverlapping();
