<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Setting;

class Offer extends Model
{
    protected $fillable = [
        'period_id',
        'currency',
        'price_adult',
        'discount_adult',
        'price_child',
        'discount_child_bed',
        'price_child_nobed',
        'discount_child_nobed',
        'price_infant',
        'price_joinland',
        'price_single',
        'discount_single',
        'deposit',
        'commission_agent',
        'commission_sale',
        'cancellation_policy',
        'refund_policy',
        'notes',
        'ttl_minutes',
        'promo_name',
        'promo_start_date',
        'promo_end_date',
        'promo_quota',
        'promo_used',
        'promotion_id',
    ];

    protected $casts = [
        'price_adult' => 'decimal:2',
        'discount_adult' => 'decimal:2',
        'price_child' => 'decimal:2',
        'discount_child_bed' => 'decimal:2',
        'price_child_nobed' => 'decimal:2',
        'discount_child_nobed' => 'decimal:2',
        'price_infant' => 'decimal:2',
        'price_joinland' => 'decimal:2',
        'price_single' => 'decimal:2',
        'discount_single' => 'decimal:2',
        'deposit' => 'decimal:2',
        'commission_agent' => 'decimal:2',
        'commission_sale' => 'decimal:2',
        'promo_start_date' => 'date',
        'promo_end_date' => 'date',
    ];

    // Relationships
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(OfferPromotion::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    // Helpers
    public function getDiscountedPriceAttribute(): float
    {
        $discount = $this->promotions()
            ->active()
            ->where('type', 'discount_amount')
            ->sum('value');

        $percentDiscount = $this->promotions()
            ->active()
            ->where('type', 'discount_percent')
            ->sum('value');

        $price = $this->price_adult - $discount;
        $price = $price * (1 - $percentDiscount / 100);

        return max(0, $price);
    }

    public function hasActivePromotion(): bool
    {
        return $this->promotions()->active()->exists();
    }

    public function isExpired(): bool
    {
        return $this->updated_at->addMinutes($this->ttl_minutes)->isPast();
    }

    /**
     * คำนวณ discount percentage
     */
    public function getDiscountPercentAttribute(): float
    {
        if (!$this->price_adult || $this->price_adult <= 0) {
            return 0;
        }
        
        $discount = $this->discount_adult ?? 0;
        return round(($discount / $this->price_adult) * 100, 2);
    }
}
