<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Auto Sync Tours based on database config
        $schedule->call(function () {
            $configs = \App\Models\WholesalerApiConfig::where('sync_enabled', true)
                ->whereNotNull('sync_schedule')
                ->get();
            
            foreach ($configs as $config) {
                // Check if this config should run now based on its cron schedule
                $cron = new \Cron\CronExpression($config->sync_schedule);
                
                // Check if cron should have run in the last minute
                if ($cron->isDue()) {
                    // Check if there's already a running sync for this wholesaler
                    $hasRunningSync = \App\Models\SyncLog::where('wholesaler_id', $config->wholesaler_id)
                        ->where('status', 'running')
                        ->where('started_at', '>', now()->subHours(2)) // Timeout after 2 hours
                        ->exists();
                    
                    if ($hasRunningSync) {
                        \Illuminate\Support\Facades\Log::info('Scheduled sync skipped - already running', [
                            'wholesaler_id' => $config->wholesaler_id,
                        ]);
                        continue;
                    }
                    
                    \App\Jobs\SyncToursJob::dispatch(
                        $config->wholesaler_id,
                        null,
                        'incremental'
                    );
                    
                    \Illuminate\Support\Facades\Log::info('Scheduled sync dispatched', [
                        'wholesaler_id' => $config->wholesaler_id,
                        'schedule' => $config->sync_schedule,
                    ]);
                }
            }
        })->everyMinute()->name('check-sync-schedules')->withoutOverlapping();
        
        // Full sync daily at configured time (or default 3 AM)
        $schedule->call(function () {
            $configs = \App\Models\WholesalerApiConfig::where('sync_enabled', true)
                ->whereNotNull('full_sync_schedule')
                ->get();
            
            foreach ($configs as $config) {
                $cron = new \Cron\CronExpression($config->full_sync_schedule);
                
                if ($cron->isDue()) {
                    // Check if there's already a running sync for this wholesaler
                    $hasRunningSync = \App\Models\SyncLog::where('wholesaler_id', $config->wholesaler_id)
                        ->where('status', 'running')
                        ->where('started_at', '>', now()->subHours(2))
                        ->exists();
                    
                    if ($hasRunningSync) {
                        \Illuminate\Support\Facades\Log::info('Scheduled full sync skipped - already running', [
                            'wholesaler_id' => $config->wholesaler_id,
                        ]);
                        continue;
                    }
                    
                    \App\Jobs\SyncToursJob::dispatch(
                        $config->wholesaler_id,
                        null,
                        'full'
                    );
                    
                    \Illuminate\Support\Facades\Log::info('Scheduled full sync dispatched', [
                        'wholesaler_id' => $config->wholesaler_id,
                        'schedule' => $config->full_sync_schedule,
                    ]);
                }
            }
        })->everyMinute()->name('check-full-sync-schedules')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON for API authentication errors
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login again.',
                ], 401);
            }
        });
    })->create();
