<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlashSaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'flash_sale_id',
        'tour_id',
        'period_id',
        'flash_price',
        'original_price',
        'discount_percent',
        'quantity_limit',
        'quantity_sold',
        'sort_order',
        'is_active',
        'flash_end_date',
    ];

    protected $casts = [
        'flash_price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'quantity_limit' => 'integer',
        'quantity_sold' => 'integer',
        'is_active' => 'boolean',
        'flash_end_date' => 'datetime',
    ];

    // ─── Relationships ───
    public function flashSale()
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    // ─── Helpers ───
    public function getRemainingAttribute(): ?int
    {
        if ($this->quantity_limit === null) return null;
        return max(0, $this->quantity_limit - $this->quantity_sold);
    }

    public function getSoldPercentAttribute(): ?float
    {
        if ($this->quantity_limit === null || $this->quantity_limit === 0) return null;
        return round(($this->quantity_sold / $this->quantity_limit) * 100, 1);
    }

    public function isSoldOut(): bool
    {
        return $this->quantity_limit !== null && $this->quantity_sold >= $this->quantity_limit;
    }
}
