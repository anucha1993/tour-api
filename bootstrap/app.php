<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

// Helper: check if schedule string is time-list format (e.g. "09:30,12:00,18:00")
function isTimeListFormat(string $schedule): bool
{
    return (bool) preg_match('/^\d{1,2}:\d{2}(\s*,\s*\d{1,2}:\d{2})*$/', trim($schedule));
}

// Helper: check if any time in a time-list matches the current HH:mm
function isTimeListDue(string $schedule): bool
{
    $now = now()->format('H:i');
    $times = array_map('trim', explode(',', $schedule));
    foreach ($times as $time) {
        // Normalize "9:30" → "09:30"
        $parts = explode(':', $time);
        $normalized = str_pad($parts[0], 2, '0', STR_PAD_LEFT) . ':' . $parts[1];
        if ($normalized === $now) {
            return true;
        }
    }
    return false;
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Auto Sync Tours based on database config
        // Supports two schedule formats:
        //   1. Time-list: "09:30,12:00,18:00" (preferred — no conflicts possible)
        //   2. Legacy cron: "0 */2 * * *" (backward compatible)
        $schedule->call(function () {
            $configs = \App\Models\WholesalerApiConfig::where('sync_enabled', true)
                ->whereNotNull('sync_schedule')
                ->get();
            
            foreach ($configs as $config) {
                $isDue = false;
                $scheduleStr = trim($config->sync_schedule);

                if (isTimeListFormat($scheduleStr)) {
                    // Time-list format: "09:30,12:00,18:00"
                    $isDue = isTimeListDue($scheduleStr);
                } else {
                    // Legacy cron format
                    $cron = new \Cron\CronExpression($scheduleStr);
                    $isDue = $cron->isDue();
                }

                if (!$isDue) continue;
                
                // Check if there's already a running sync for this wholesaler
                $hasRunningSync = \App\Models\SyncLog::where('wholesaler_id', $config->wholesaler_id)
                    ->where('status', 'running')
                    ->where('started_at', '>', now()->subMinutes(15))
                    ->where(function ($q) {
                        $q->where('last_heartbeat_at', '>', now()->subMinutes(15))
                          ->orWhere('started_at', '>', now()->subMinutes(5));
                    })
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
        })->everyMinute()->name('check-sync-schedules')->withoutOverlapping();
        
        // Full sync daily at configured time (or default 3 AM)
        $schedule->call(function () {
            $configs = \App\Models\WholesalerApiConfig::where('sync_enabled', true)
                ->whereNotNull('full_sync_schedule')
                ->get();
            
            foreach ($configs as $config) {
                $isDue = false;
                $scheduleStr = trim($config->full_sync_schedule);

                if (isTimeListFormat($scheduleStr)) {
                    $isDue = isTimeListDue($scheduleStr);
                } else {
                    $cron = new \Cron\CronExpression($scheduleStr);
                    $isDue = $cron->isDue();
                }

                if (!$isDue) continue;
                
                // Check if there's already a running sync for this wholesaler
                $hasRunningSync = \App\Models\SyncLog::where('wholesaler_id', $config->wholesaler_id)
                    ->where('status', 'running')
                    ->where('started_at', '>', now()->subMinutes(15))
                    ->where(function ($q) {
                        $q->where('last_heartbeat_at', '>', now()->subMinutes(15))
                          ->orWhere('started_at', '>', now()->subMinutes(5));
                    })
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
        })->everyMinute()->name('check-full-sync-schedules')->withoutOverlapping();
        
        // Auto-cancel stuck syncs every 5 minutes
        $schedule->command('sync:cancel-stuck --timeout=30 --force')
            ->everyFiveMinutes()
            ->name('cancel-stuck-syncs')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Note: Don't use statefulApi() when frontend uses Bearer token auth
        // statefulApi() enables CSRF which requires cookie-based authentication

        // Override 'auth' middleware alias so redirectTo() returns null
        // instead of trying to generate route('login') which doesn't exist in this API-only app
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON 401 for unauthenticated requests
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        });

        // Fallback: catch RouteNotFoundException from missing 'login' named route
        // in case custom Authenticate middleware does not intercept in time
        $exceptions->render(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        });

        // Always render JSON for API routes
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*');
        });
    })->create();
