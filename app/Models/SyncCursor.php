<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncCursor extends Model
{
    protected $fillable = [
        'wholesaler_id',
        'sync_type',
        'cursor_value',
        'cursor_type',
        'last_sync_id',
        'last_synced_at',
        'total_received',
        'last_batch_count',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the wholesaler that owns this cursor
     */
    public function wholesaler(): BelongsTo
    {
        return $this->belongsTo(Wholesaler::class);
    }

    /**
     * Get or create cursor for wholesaler
     */
    public static function getOrCreate(int $wholesalerId, string $syncType = 'all'): self
    {
        return static::firstOrCreate(
            ['wholesaler_id' => $wholesalerId, 'sync_type' => $syncType],
            ['cursor_value' => null, 'cursor_type' => 'string']
        );
    }

    /**
     * Update cursor after successful sync
     */
    public function updateAfterSync(string $newCursor, int $batchCount, string $syncId): void
    {
        $this->update([
            'cursor_value' => $newCursor,
            'last_sync_id' => $syncId,
            'last_synced_at' => now(),
            'total_received' => $this->total_received + $batchCount,
            'last_batch_count' => $batchCount,
        ]);
    }

    /**
     * Reset cursor for full sync
     */
    public function reset(): void
    {
        $this->update([
            'cursor_value' => null,
            'last_sync_id' => null,
        ]);
    }
}
