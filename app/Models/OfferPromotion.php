<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferPromotion extends Model
{
    public $timestamps = false;

    public const TYPE_DISCOUNT_AMOUNT = 'discount_amount';
    public const TYPE_DISCOUNT_PERCENT = 'discount_percent';
    public const TYPE_FREEBIE = 'freebie';

    public const TYPES = [
        self::TYPE_DISCOUNT_AMOUNT => 'ลดเป็นจำนวนเงิน',
        self::TYPE_DISCOUNT_PERCENT => 'ลดเป็นเปอร์เซ็นต์',
        self::TYPE_FREEBIE => 'ของแถม',
    ];

    protected $fillable = [
        'offer_id',
        'promo_code',
        'name',
        'type',
        'value',
        'apply_to',
        'start_at',
        'end_at',
        'conditions',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'conditions' => 'array',
        'is_active' => 'boolean',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_at')
                    ->orWhere('start_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_at')
                    ->orWhere('end_at', '>=', now());
            });
    }

    // Helpers
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        
        $now = now();
        if ($this->start_at && $now->lt($this->start_at)) return false;
        if ($this->end_at && $now->gt($this->end_at)) return false;
        
        return true;
    }

    public function getDiscountLabelAttribute(): string
    {
        return match($this->type) {
            self::TYPE_DISCOUNT_AMOUNT => "ลด {$this->value} บาท",
            self::TYPE_DISCOUNT_PERCENT => "ลด {$this->value}%",
            self::TYPE_FREEBIE => $this->name,
            default => '',
        };
    }
}
