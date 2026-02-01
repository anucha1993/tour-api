<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncErrorLog extends Model
{
    protected $fillable = [
        'sync_log_id',
        'wholesaler_id',
        'external_tour_code',
        'tour_id',
        'section_name',
        'field_name',
        'error_type',
        'error_message',
        'received_value',
        'expected_type',
        'raw_data',
        'stack_trace',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the sync log
     */
    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(SyncLog::class);
    }

    /**
     * Get the wholesaler
     */
    public function wholesaler(): BelongsTo
    {
        return $this->belongsTo(Wholesaler::class);
    }

    /**
     * Get the tour if linked
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Get the resolver user
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Mark as resolved
     */
    public function resolve(int $userId, ?string $notes = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Scope for unresolved errors
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope by error type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('error_type', $type);
    }
}
