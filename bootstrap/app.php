<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

// Helper: check if schedule string is due now
// Supports both time-list format ("03:55" or "09:00,21:00") and cron expressions ("55 3 * * *")
function isScheduleDue(string $schedule): bool
{
    $schedule = trim($schedule);
    
    // Time-list format: "HH:MM" or "HH:MM,HH:MM,..."
    if (preg_match('/^\d{1,2}:\d{2}(\s*,\s*\d{1,2}:\d{2})*$/', $schedule)) {
        $nowH = (int) date('G'); // 0-23
        $nowM = (int) date('i'); // 0-59
        $times = array_map('trim', explode(',', $schedule));
        foreach ($times as $time) {
            [$h, $m] = explode(':', $time);
            if ((int) $h === $nowH && (int) $m === $nowM) {
                return true;
            }
        }
        return false;
    }
    
    // Cron expression format
    try {
        $cron = new \Cron\CronExpression($schedule);
        return $cron->isDue();
    } catch (\Throwable $e) {
        try {
            \Illuminate\Support\Facades\Log::warning('Invalid schedule format', [
                'schedule' => $schedule,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $ignored) {
            // Facade not ready
        }
        return false;
    }
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
        $schedule->call(function () {
            $configs = \App\Models\WholesalerApiConfig::where('sync_enabled', true)
                ->whereNotNull('sync_schedule')
                ->get();
            
            foreach ($configs as $config) {
                if (isScheduleDue($config->sync_schedule)) {
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
            }
        })->everyMinute()->name('check-sync-schedules')->withoutOverlapping();
        
        // Full sync daily at configured time (or default 3 AM)
        $schedule->call(function () {
            $configs = \App\Models\WholesalerApiConfig::where('sync_enabled', true)
                ->whereNotNull('full_sync_schedule')
                ->get();
            
            foreach ($configs as $config) {
                if (isScheduleDue($config->full_sync_schedule)) {
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

        // Prevent TrimStrings from trimming whitespace values in string_transform
        // e.g. splitBy=" " (space) should be preserved, not trimmed to "" then converted to null
        $middleware->trimStrings(except: [
            'mappings.*.string_transform.splitBy',
            'mappings.*.string_transform.joinWith',
            'mappings.*.string_transform.replaceFrom',
            'mappings.*.string_transform.replaceTo',
        ]);

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
