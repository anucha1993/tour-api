<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointRule extends Model
{
    protected $fillable = [
        'action',
        'name',
        'description',
        'icon',
        'calc_type',
        'points',
        'percent_of_amount',
        'max_points_per_day',
        'max_points_per_action',
        'cooldown_minutes',
        'expire_days',
        'is_active',
    ];

    protected $casts = [
        'points' => 'integer',
        'percent_of_amount' => 'decimal:2',
        'max_points_per_day' => 'integer',
        'max_points_per_action' => 'integer',
        'cooldown_minutes' => 'integer',
        'expire_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get active rule by action name
     */
    public static function getByAction(string $action): ?self
    {
        return static::active()->where('action', $action)->first();
    }

    /**
     * Calculate points for a given amount
     */
    public function calculatePoints(float $amount = 0): int
    {
        if ($this->calc_type === 'percent' && $amount > 0) {
            $pts = (int) floor($amount * $this->percent_of_amount / 100);
        } else {
            $pts = $this->points;
        }

        if ($this->max_points_per_action && $pts > $this->max_points_per_action) {
            $pts = $this->max_points_per_action;
        }

        return $pts;
    }
}
