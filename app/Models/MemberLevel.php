<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberLevel extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'min_spending',
        'discount_percent',
        'point_multiplier',
        'redemption_rate',
        'benefits',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'min_spending' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'point_multiplier' => 'decimal:2',
        'redemption_rate' => 'decimal:2',
        'benefits' => 'array',
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(WebMember::class, 'current_level_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get the default level
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Get level for given lifetime spending amount
     */
    public static function getLevelForSpending(float $lifetimeSpending): ?self
    {
        return static::active()
            ->where('min_spending', '<=', $lifetimeSpending)
            ->orderByDesc('min_spending')
            ->first();
    }
}
