<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class BookingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'event_type',
        'status',
        'source',
        'message',
        'payload',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Convenience static logger. Never throws — swallows persistence errors
     * because event logging must not break the booking flow itself.
     */
    public static function log(
        int $bookingId,
        string $eventType,
        string $status = 'info',
        ?string $message = null,
        ?array $payload = null,
        ?string $source = null,
        ?int $userId = null,
    ): ?self {
        try {
            return static::create([
                'booking_id' => $bookingId,
                'event_type' => $eventType,
                'status' => $status,
                'source' => $source,
                'message' => $message !== null ? mb_substr($message, 0, 1000) : null,
                'payload' => $payload,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingEvent::log failed', [
                'booking_id' => $bookingId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
