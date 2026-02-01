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
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'error_summary' => 'array',
        'ack_sent' => 'boolean',
        'ack_sent_at' => 'datetime',
        'ack_accepted' => 'boolean',
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
