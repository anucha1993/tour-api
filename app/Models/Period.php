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
        $this->available = max(0, $this->capacity - $this->booked);
        
        if ($this->available === 0 && $this->status === self::STATUS_OPEN) {
            $this->status = self::STATUS_SOLD_OUT;
        }
        
        $this->save();
    }
}
