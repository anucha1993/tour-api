<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'discount_value',
        'is_active',
        'sort_order',
        'banner_url',
        'cloudflare_id',
        'link_url',
        'start_date',
        'end_date',
        'badge_text',
        'badge_color',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Type constants
    const TYPE_DISCOUNT_AMOUNT = 'discount_amount';
    const TYPE_DISCOUNT_PERCENT = 'discount_percent';
    const TYPE_FREE_GIFT = 'free_gift';
    const TYPE_INSTALLMENT = 'installment';
    const TYPE_SPECIAL = 'special';

    const TYPES = [
        self::TYPE_DISCOUNT_AMOUNT => 'ส่วนลด (บาท)',
        self::TYPE_DISCOUNT_PERCENT => 'ส่วนลด (%)',
        self::TYPE_FREE_GIFT => 'ของแถม',
        self::TYPE_INSTALLMENT => 'ผ่อนชำระ',
        self::TYPE_SPECIAL => 'พิเศษ',
    ];

    /**
     * Get all offers using this promotion.
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * Scope for active promotions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordering.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
