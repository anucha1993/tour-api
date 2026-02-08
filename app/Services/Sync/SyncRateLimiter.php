<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Log;

/**
 * SyncRateLimiter - Rate limiting for API calls
 * 
 * Features:
 * - Configurable rate limits per wholesaler
 * - Exponential backoff on rate limit hit
 * - Automatic delay between calls
 */
class SyncRateLimiter
{
    protected int $maxCallsPerMinute;
    protected int $callCount = 0;
    protected int $windowStart;
    protected int $minDelayMs;
    protected int $backoffMultiplier = 2;
    protected int $currentBackoff = 0;
    protected int $maxBackoffSeconds = 300; // 5 minutes max

    public function __construct(int $maxCallsPerMinute = 60, int $minDelayMs = 100)
    {
        $this->maxCallsPerMinute = $maxCallsPerMinute;
        $this->minDelayMs = $minDelayMs;
        $this->windowStart = time();
    }

    /**
     * Wait before making API call (respects rate limit)
     */
    public function throttle(): void
    {
        // Check if we need to reset window
        if (time() - $this->windowStart >= 60) {
            $this->callCount = 0;
            $this->windowStart = time();
            $this->currentBackoff = 0;
        }

        // Check if at rate limit
        if ($this->callCount >= $this->maxCallsPerMinute) {
            $waitTime = 60 - (time() - $this->windowStart);
            if ($waitTime > 0) {
                Log::info('SyncRateLimiter: Rate limit reached, waiting', [
                    'wait_seconds' => $waitTime,
                ]);
                sleep($waitTime);
                $this->callCount = 0;
                $this->windowStart = time();
            }
        }

        // Apply backoff if any
        if ($this->currentBackoff > 0) {
            Log::info('SyncRateLimiter: Applying backoff', [
                'backoff_seconds' => $this->currentBackoff,
            ]);
            sleep($this->currentBackoff);
        }

        // Minimum delay between calls
        usleep($this->minDelayMs * 1000);

        $this->callCount++;
    }

    /**
     * Record successful API call (reset backoff)
     */
    public function recordSuccess(): void
    {
        $this->currentBackoff = 0;
    }

    /**
     * Record rate limit hit (increase backoff)
     */
    public function recordRateLimitHit(): void
    {
        if ($this->currentBackoff === 0) {
            $this->currentBackoff = 1;
        } else {
            $this->currentBackoff = min(
                $this->currentBackoff * $this->backoffMultiplier,
                $this->maxBackoffSeconds
            );
        }

        Log::warning('SyncRateLimiter: Rate limit hit, increasing backoff', [
            'new_backoff' => $this->currentBackoff,
        ]);
    }

    /**
     * Record API error (mild backoff)
     */
    public function recordError(): void
    {
        if ($this->currentBackoff === 0) {
            $this->currentBackoff = 1;
        } else {
            $this->currentBackoff = min(
                $this->currentBackoff + 1,
                30 // Max 30 seconds for errors
            );
        }
    }

    /**
     * Get current stats
     */
    public function getStats(): array
    {
        return [
            'calls_in_window' => $this->callCount,
            'max_calls_per_minute' => $this->maxCallsPerMinute,
            'current_backoff' => $this->currentBackoff,
            'window_remaining_seconds' => max(0, 60 - (time() - $this->windowStart)),
        ];
    }

    /**
     * Set rate limit (calls per minute)
     */
    public function setRateLimit(int $callsPerMinute): self
    {
        $this->maxCallsPerMinute = $callsPerMinute;
        return $this;
    }
}
