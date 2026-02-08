<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncLog extends Model
{
    protected $fillable = [
        'wholesaler_id',
        'sync_type',
        'sync_id',
        'started_at',
        'completed_at',
        'duration_seconds',
        'status',
        'tours_received',
        'tours_created',
        'tours_updated',
        'tours_skipped',
        'tours_failed',
        'periods_received',
        'periods_created',
        'periods_updated',
        'error_count',
        'error_summary',
        'ack_sent',
        'ack_sent_at',
        'ack_accepted',
        // New fields for progress tracking
        'last_heartbeat_at',
        'heartbeat_timeout_minutes',
        'total_items',
        'processed_items',
        'progress_percent',
        'current_item_code',
        'chunk_size',
        'current_chunk',
        'total_chunks',
        'api_calls_count',
        'rate_limit_reset_at',
        'cancel_requested',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'error_summary' => 'array',
        'ack_sent' => 'boolean',
        'ack_sent_at' => 'datetime',
        'ack_accepted' => 'boolean',
        // New casts
        'last_heartbeat_at' => 'datetime',
        'rate_limit_reset_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancel_requested' => 'boolean',
    ];

    /**
     * Get the wholesaler
     */
    public function wholesaler(): BelongsTo
    {
        return $this->belongsTo(Wholesaler::class);
    }

    /**
     * Get error logs
     */
    public function errorLogs(): HasMany
    {
        return $this->hasMany(SyncErrorLog::class);
    }

    /**
     * Check if sync is stuck (no heartbeat for timeout period)
     */
    public function isStuck(): bool
    {
        if ($this->status !== 'running') {
            return false;
        }

        $timeout = $this->heartbeat_timeout_minutes ?? 30;
        
        if ($this->last_heartbeat_at) {
            return $this->last_heartbeat_at->lt(now()->subMinutes($timeout));
        }

        return $this->started_at->lt(now()->subMinutes($timeout));
    }

    /**
     * Check if cancellation was requested
     */
    public function isCancellationRequested(): bool
    {
        return (bool) $this->cancel_requested;
    }

    /**
     * Scope for running syncs
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for stuck syncs
     */
    public function scopeStuck($query, int $timeoutMinutes = 30)
    {
        return $query->where('status', 'running')
            ->where(function ($q) use ($timeoutMinutes) {
                $q->where(function ($sub) use ($timeoutMinutes) {
                    $sub->whereNull('last_heartbeat_at')
                        ->where('started_at', '<', now()->subMinutes($timeoutMinutes));
                })->orWhere(function ($sub) use ($timeoutMinutes) {
                    $sub->whereNotNull('last_heartbeat_at')
                        ->where('last_heartbeat_at', '<', now()->subMinutes($timeoutMinutes));
                });
            });
    }

    /**
     * Start a new sync
     */
    public static function startSync(int $wholesalerId, string $syncType = 'incremental'): self
    {
        return static::create([
            'wholesaler_id' => $wholesalerId,
            'sync_type' => $syncType,
            'sync_id' => 'sync_' . date('Ymd_His') . '_' . uniqid(),
            'started_at' => now(),
            'status' => 'running',
        ]);
    }

    /**
     * Complete the sync
     */
    public function complete(string $status = 'completed'): void
    {
        $this->update([
            'status' => $status,
            'completed_at' => now(),
            'duration_seconds' => now()->diffInSeconds($this->started_at),
        ]);
    }

    /**
     * Increment tour stats
     */
    public function incrementTours(string $action, int $count = 1): void
    {
        $field = match($action) {
            'received' => 'tours_received',
            'created' => 'tours_created',
            'updated' => 'tours_updated',
            'skipped' => 'tours_skipped',
            'failed' => 'tours_failed',
            default => null,
        };

        if ($field) {
            $this->increment($field, $count);
        }
    }

    /**
     * Increment period stats
     */
    public function incrementPeriods(string $action, int $count = 1): void
    {
        $field = match($action) {
            'received' => 'periods_received',
            'created' => 'periods_created',
            'updated' => 'periods_updated',
            default => null,
        };

        if ($field) {
            $this->increment($field, $count);
        }
    }

    /**
     * Log an error
     */
    public function logError(array $errorData): SyncErrorLog
    {
        $this->increment('error_count');
        
        return $this->errorLogs()->create(array_merge($errorData, [
            'wholesaler_id' => $this->wholesaler_id,
        ]));
    }

    /**
     * Mark ACK as sent
     */
    public function markAckSent(bool $accepted = true): void
    {
        $this->update([
            'ack_sent' => true,
            'ack_sent_at' => now(),
            'ack_accepted' => $accepted,
        ]);
    }
}
