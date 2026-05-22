<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_code',
        'web_member_id',
        'tour_id',
        'period_id',
        'flash_sale_item_id',
        'integration_id',
        'provider',
        'provider_booking_ref',
        'provider_quote_ref',
        'provider_status',
        'hold_expires_at',
        'currency',
        'provider_payload',
        'qty_adult',
        'qty_adult_single',
        'qty_child_bed',
        'qty_child_nobed',
        'qty_infant',
        'qty_triple',
        'qty_twin',
        'qty_double',
        'price_adult',
        'price_single',
        'price_child_bed',
        'price_child_nobed',
        'price_infant',
        'total_amount',
        'first_name',
        'last_name',
        'email',
        'phone',
        'sale_code',
        'special_request',
        'status',
        'source',
        'admin_note',
        'cancelled_by',
    ];

    protected $casts = [
        'hold_expires_at' => 'datetime',
        'provider_payload' => 'array',
        'price_adult' => 'decimal:2',
        'price_single' => 'decimal:2',
        'price_child_bed' => 'decimal:2',
        'price_child_nobed' => 'decimal:2',
        'price_infant' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'qty_adult' => 'integer',
        'qty_adult_single' => 'integer',
        'qty_child_bed' => 'integer',
        'qty_child_nobed' => 'integer',
        'qty_infant' => 'integer',
        'qty_triple' => 'integer',
        'qty_twin' => 'integer',
        'qty_double' => 'integer',
    ];

    // ─── Relationships ───

    public function member()
    {
        return $this->belongsTo(WebMember::class, 'web_member_id');
    }

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    public function flashSaleItem()
    {
        return $this->belongsTo(FlashSaleItem::class);
    }

    public function integration()
    {
        return $this->belongsTo(WholesalerApiConfig::class, 'integration_id');
    }

    public function passengers()
    {
        return $this->hasMany(BookingPassenger::class);
    }

    public function leadPassenger()
    {
        return $this->hasOne(BookingPassenger::class)->where('is_lead', true);
    }

    // ─── Scopes ───

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeFromFlashSale($query)
    {
        return $query->where('source', 'flash_sale');
    }

    public function scopeFromWebsite($query)
    {
        return $query->where('source', 'website');
    }

    public function scopeHeld($query)
    {
        return $query->where('provider_status', 'held');
    }

    public function scopeExpiredHold($query)
    {
        return $query->where('provider_status', 'held')
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now());
    }

    // ─── Helpers ───

    /**
     * Generate a unique booking code like BK-250225-XXXX
     */
    public static function generateBookingCode(): string
    {
        $prefix = 'BK-' . now()->format('ymd') . '-';
        $attempts = 0;

        do {
            $code = $prefix . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
            $exists = static::where('booking_code', $code)->exists();
            $attempts++;
        } while ($exists && $attempts < 10);

        return $code;
    }

    /**
     * Get full customer name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get total passengers count
     */
    public function getTotalPassengersAttribute(): int
    {
        return $this->qty_adult + $this->qty_child_bed + $this->qty_child_nobed;
    }

    /**
     * Get status label in Thai
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->status === 'cancelled' && $this->cancelled_by === 'customer') {
            return 'ยกเลิกโดยลูกค้า';
        }

        return match ($this->status) {
            'pending' => 'รอดำเนินการ',
            'confirmed' => 'ยืนยันแล้ว',
            'paid' => 'ชำระเงินแล้ว',
            'cancelled' => 'ยกเลิก',
            'completed' => 'เสร็จสมบูรณ์',
            default => $this->status,
        };
    }

    /**
     * Is flash sale booking?
     */
    public function isFlashSale(): bool
    {
        return $this->source === 'flash_sale';
    }
}
