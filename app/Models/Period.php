<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Period extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_SOLD_OUT = 'sold_out';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN => 'เปิดจอง',
        self::STATUS_CLOSED => 'ปิดจอง',
        self::STATUS_SOLD_OUT => 'เต็ม',
        self::STATUS_CANCELLED => 'ยกเลิก',
    ];

    // Sale Status constants
    public const SALE_AVAILABLE = 'available';
    public const SALE_BOOKING = 'booking';
    public const SALE_SOLD_OUT = 'sold_out';

    public const SALE_STATUSES = [
        self::SALE_AVAILABLE => 'ไลน์',
        self::SALE_BOOKING => 'จอง',
        self::SALE_SOLD_OUT => 'เต็ม',
    ];

    protected $fillable = [
        'tour_id',
        'external_id',
        'period_code',
        'start_date',
        'end_date',
        'capacity',
        'booked',
        'available',
        'status',
        'is_visible',
        'sale_status',
        'updated_at_source',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'updated_at_source' => 'datetime',
        'is_visible' => 'boolean',
    ];

    // Relationships
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', now()->toDateString());
    }

    public function scopeAvailable($query)
    {
        return $query->where('available', '>', 0);
    }

    // Boot — auto-calculate available on every save
    protected static function boot()
    {
        parent::boot();

        static::saving(function (Period $period) {
            // Ensure booked is not negative
            $period->booked = max(0, (int) $period->booked);
            
            // Recalculate available from capacity - booked
            // Clamp to 0 minimum because DB column is unsigned integer
            $period->available = max(0, (int) $period->capacity - (int) $period->booked);
            
            // Auto-calculate sale_status based on available seats
            if ($period->available === 0) {
                $period->sale_status = self::SALE_SOLD_OUT;   // เต็ม
            } elseif ($period->available < 4) {
                $period->sale_status = self::SALE_AVAILABLE;  // ไลน์
            } else {
                $period->sale_status = self::SALE_BOOKING;    // จอง
            }
            
            // Auto-mark as sold_out if no seats available and currently open
            if ($period->available === 0 && $period->status === self::STATUS_OPEN) {
                $period->status = self::STATUS_SOLD_OUT;
            }
        });
    }

    // Accessor — safety net: always compute available even if DB value is wrong
    public function getAvailableAttribute($value): int
    {
        $computed = max(0, (int) $this->capacity - max(0, (int) $this->booked));
        // If stored value doesn't match, return the computed value
        if ((int) $value !== $computed) {
            return $computed;
        }
        return (int) $value;
    }

    // Helpers
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_OPEN 
            && $this->available > 0 
            && $this->start_date >= now()->toDateString();
    }

    public function getDurationAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function updateAvailability(): void
    {
        // Just save — boot() auto-calculates available, sale_status, and status
        $this->save();
    }

    /**
     * Compute sale_status from available seats.
     */
    public static function computeSaleStatus(int $available): string
    {
        if ($available === 0) {
            return self::SALE_SOLD_OUT;
        } elseif ($available < 4) {
            return self::SALE_AVAILABLE;
        }
        return self::SALE_BOOKING;
    }
}
