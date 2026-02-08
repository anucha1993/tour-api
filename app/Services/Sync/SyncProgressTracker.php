<?php

namespace App\Services\Sync;

use App\Models\SyncLog;
use Illuminate\Support\Facades\Log;

/**
 * SyncProgressTracker - Track sync progress and heartbeat
 * 
 * Features:
 * - Progress percentage tracking
 * - Heartbeat monitoring
 * - Cancellation detection
 * - Rate limit tracking
 */
class SyncProgressTracker
{
    protected SyncLog $syncLog;
    protected int $heartbeatInterval = 60; // seconds
    protected int $lastHeartbeat = 0;

    public function __construct(SyncLog $syncLog)
    {
        $this->syncLog = $syncLog;
        $this->lastHeartbeat = time();
    }

    /**
     * Initialize progress tracking
     */
    public function initialize(int $totalItems, int $chunkSize = 50): self
    {
        $this->syncLog->update([
            'total_items' => $totalItems,
            'processed_items' => 0,
            'progress_percent' => 0,
            'chunk_size' => $chunkSize,
            'total_chunks' => ceil($totalItems / $chunkSize),
            'current_chunk' => 0,
            'last_heartbeat_at' => now(),
        ]);

        return $this;
    }

    /**
     * Update progress after processing an item
     */
    public function incrementProgress(string $currentItemCode = null): self
    {
        $this->syncLog->increment('processed_items');
        
        $processed = $this->syncLog->processed_items;
        $total = $this->syncLog->total_items;
        $percent = $total > 0 ? round(($processed / $total) * 100) : 0;

        $updates = [
            'progress_percent' => $percent,
        ];

        if ($currentItemCode) {
            $updates['current_item_code'] = $currentItemCode;
        }

        $this->syncLog->update($updates);

        // Update heartbeat if interval passed
        $this->maybeUpdateHeartbeat();

        return $this;
    }

    /**
     * Move to next chunk
     */
    public function nextChunk(): self
    {
        $this->syncLog->increment('current_chunk');
        $this->updateHeartbeat();
        return $this;
    }

    /**
     * Update heartbeat timestamp
     */
    public function updateHeartbeat(): self
    {
        $this->syncLog->update([
            'last_heartbeat_at' => now(),
        ]);
        $this->lastHeartbeat = time();
        return $this;
    }

    /**
     * Maybe update heartbeat if interval passed
     */
    protected function maybeUpdateHeartbeat(): void
    {
        if (time() - $this->lastHeartbeat >= $this->heartbeatInterval) {
            $this->updateHeartbeat();
        }
    }

    /**
     * Check if cancellation was requested
     */
    public function isCancelled(): bool
    {
        // Refresh from database
        $this->syncLog->refresh();
        return $this->syncLog->cancel_requested;
    }

    /**
     * Check if sync should stop (cancelled or timeout)
     */
    public function shouldStop(): bool
    {
        $this->syncLog->refresh();

        // Check cancellation
        if ($this->syncLog->cancel_requested) {
            Log::info('SyncProgressTracker: Cancellation requested', [
                'sync_log_id' => $this->syncLog->id,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Increment API call counter
     */
    public function incrementApiCall(): self
    {
        $this->syncLog->increment('api_calls_count');
        return $this;
    }

    /**
     * Get current progress info
     */
    public function getProgress(): array
    {
        return [
            'total_items' => $this->syncLog->total_items,
            'processed_items' => $this->syncLog->processed_items,
            'progress_percent' => $this->syncLog->progress_percent,
            'current_item_code' => $this->syncLog->current_item_code,
            'current_chunk' => $this->syncLog->current_chunk,
            'total_chunks' => $this->syncLog->total_chunks,
            'api_calls_count' => $this->syncLog->api_calls_count,
        ];
    }

    /**
     * Mark sync as completed
     */
    public function complete(array $stats = []): void
    {
        $this->syncLog->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress_percent' => 100,
            'tours_synced' => $stats['tours_created'] ?? 0,
            'tours_updated' => $stats['tours_updated'] ?? 0,
            'tours_failed' => $stats['errors'] ?? 0,
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function fail(string $errorMessage): void
    {
        $this->syncLog->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark sync as cancelled
     */
    public function markCancelled(string $reason = 'User requested'): void
    {
        $this->syncLog->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark sync as timeout
     */
    public function markTimeout(): void
    {
        $this->syncLog->update([
            'status' => 'timeout',
            'cancelled_at' => now(),
            'cancel_reason' => 'Heartbeat timeout - no activity detected',
            'completed_at' => now(),
        ]);
    }

    /**
     * Get sync log
     */
    public function getSyncLog(): SyncLog
    {
        return $this->syncLog;
    }
}
