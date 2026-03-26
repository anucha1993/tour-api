<?php

namespace App\Jobs;

use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TestHeadcodeConnectionJob
 *
 * Runs Phase-1 fetch (ping mode) for a Headcode adapter in a queue worker
 * process so the web server thread is never blocked.
 *
 * Result is stored in cache under "headcode_test:{taskId}" for 5 minutes.
 * The frontend polls GET /integrations/{id}/test-headcode/{taskId}/status
 * every 2 seconds until status is 'success' or 'failed'.
 */
class TestHeadcodeConnectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;    // No retry — test should give immediate feedback
    public int $timeout = 60;   // 60s max (Phase-1 only, normally ~8-12s)

    public function __construct(
        private readonly int    $configId,
        private readonly string $taskId,
    ) {}

    public function handle(): void
    {
        $cacheKey = "headcode_test:{$this->taskId}";

        try {
            $config = WholesalerApiConfig::find($this->configId);

            if (!$config) {
                Cache::put($cacheKey, [
                    'status'  => 'failed',
                    'message' => 'ไม่พบ Integration',
                ], now()->addMinutes(5));
                return;
            }

            $adapter = AdapterFactory::create($config->wholesaler_id);

            $start  = microtime(true);
            $result = $adapter->fetchTours('__ping__');
            $ms     = (int) ((microtime(true) - $start) * 1000);

            if ($result->success) {
                $count = count($result->tours ?? []);
                Cache::put($cacheKey, [
                    'status'       => 'success',
                    'tours_count'  => $count,
                    'elapsed_ms'   => $ms,
                ], now()->addMinutes(5));

                Log::info('TestHeadcodeConnectionJob: success', [
                    'config_id'   => $this->configId,
                    'tours_count' => $count,
                    'elapsed_ms'  => $ms,
                ]);
            } else {
                Cache::put($cacheKey, [
                    'status'  => 'failed',
                    'message' => $result->errorMessage ?? 'API returned error',
                ], now()->addMinutes(5));
            }
        } catch (\Throwable $e) {
            Log::error('TestHeadcodeConnectionJob failed', [
                'config_id' => $this->configId,
                'error'     => $e->getMessage(),
            ]);
            Cache::put($cacheKey, [
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ], now()->addMinutes(5));
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("headcode_test:{$this->taskId}", [
            'status'  => 'failed',
            'message' => 'Job failed: ' . $e->getMessage(),
        ], now()->addMinutes(5));
    }
}
