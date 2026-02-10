<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecommendedTourSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'conditions',
        'display_limit',
        'sort_by',
        'sort_order',
        'weight',
        'schedule_start',
        'schedule_end',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'display_limit' => 'integer',
        'sort_order' => 'integer',
        'weight' => 'integer',
        'is_active' => 'boolean',
        'schedule_start' => 'datetime',
        'schedule_end' => 'datetime',
    ];

    // Reuse sort options from TourTab
    const SORT_OPTIONS = TourTab::SORT_OPTIONS;
    const CONDITION_TYPES = TourTab::CONDITION_TYPES;

    /**
     * Scope: active sections
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope: currently scheduled (within schedule window or no schedule set)
     */
    public function scopeScheduled($query)
    {
        $now = now();
        return $query->where(function ($q) use ($now) {
            $q->where(function ($q2) use ($now) {
                // Has schedule_start but no end
                $q2->whereNotNull('schedule_start')
                   ->whereNull('schedule_end')
                   ->where('schedule_start', '<=', $now);
            })->orWhere(function ($q2) use ($now) {
                // Has both start and end
                $q2->whereNotNull('schedule_start')
                   ->whereNotNull('schedule_end')
                   ->where('schedule_start', '<=', $now)
                   ->where('schedule_end', '>=', $now);
            })->orWhere(function ($q2) {
                // No schedule at all (always valid)
                $q2->whereNull('schedule_start')
                   ->whereNull('schedule_end');
            });
        });
    }

    /**
     * Get tours matching this section's conditions
     */
    public function getTours(int $limit = null)
    {
        $limit = $limit ?? $this->display_limit;

        $query = Tour::query()
            ->where('status', 'active')
            ->where('available_seats', '>', 0) // Exclude Sold Out tours
            ->whereHas('periods', function ($q) {
                $q->where('start_date', '>=', now()->toDateString())
                  ->where('status', 'open');
            });

        $this->applyConditions($query);
        $this->applySorting($query);

        return $query->limit($limit)->get();
    }

    /**
     * Apply filter conditions to query (same logic as TourTab)
     */
    protected function applyConditions($query)
    {
        $rawConditions = $this->conditions ?? [];

        $conditions = [];
        foreach ($rawConditions as $cond) {
            if (is_array($cond) && isset($cond['type']) && isset($cond['value'])) {
                $conditions[$cond['type']] = $cond['value'];
            } elseif (is_object($cond) && isset($cond->type) && isset($cond->value)) {
                $conditions[$cond->type] = $cond->value;
            }
        }

        if (empty($conditions) && !empty($rawConditions) && isset($rawConditions['price_min'])) {
            $conditions = $rawConditions;
        }

        if (!empty($conditions['price_min'])) {
            $query->where('min_price', '>=', $conditions['price_min']);
        }
        if (!empty($conditions['price_max'])) {
            $query->where('min_price', '<=', $conditions['price_max']);
        }
        if (!empty($conditions['countries'])) {
            $query->whereIn('country_id', (array) $conditions['countries']);
        }
        if (!empty($conditions['regions'])) {
            $query->whereHas('country', function ($q) use ($conditions) {
                $q->whereIn('region', (array) $conditions['regions']);
            });
        }
        if (!empty($conditions['wholesalers'])) {
            $query->whereIn('wholesaler_id', (array) $conditions['wholesalers']);
        }
        if (!empty($conditions['departure_within_days'])) {
            $query->whereHas('periods', function ($q) use ($conditions) {
                $q->where('start_date', '<=', now()->addDays($conditions['departure_within_days'])->toDateString());
            });
        }
        if (!empty($conditions['has_discount'])) {
            $query->where(function ($q) {
                $q->where('has_promotion', true)
                  ->orWhere('discount_adult', '>', 0)
                  ->orWhere('max_discount_percent', '>', 0);
            });
        }
        if (!empty($conditions['discount_min_percent'])) {
            $query->where('max_discount_percent', '>=', $conditions['discount_min_percent']);
        }
        if (!empty($conditions['tour_type'])) {
            $query->where('tour_type', $conditions['tour_type']);
        }
        if (!empty($conditions['min_days'])) {
            $query->where('days', '>=', $conditions['min_days']);
        }
        if (!empty($conditions['max_days'])) {
            $query->where('days', '<=', $conditions['max_days']);
        }
        if (!empty($conditions['is_premium'])) {
            $query->where('is_premium', true);
        }
        if (!empty($conditions['created_within_days'])) {
            $query->where('created_at', '>=', now()->subDays($conditions['created_within_days']));
        }
        if (!empty($conditions['has_available_seats'])) {
            $query->where('available_seats', '>', 0);
        }

        return $query;
    }

    /**
     * Apply sorting to query (same logic as TourTab)
     */
    protected function applySorting($query)
    {
        switch ($this->sort_by) {
            case 'price_asc':
                $query->orderByRaw('COALESCE(min_price, 999999999) ASC');
                break;
            case 'price_desc':
                $query->orderByRaw('COALESCE(min_price, 0) DESC');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'departure_date':
                $query->orderByRaw('COALESCE(next_departure_date, "9999-12-31") ASC');
                break;
            case 'popular':
            default:
                $query->orderByRaw('COALESCE(view_count, 0) DESC')->orderBy('created_at', 'desc');
                break;
        }

        return $query;
    }
}
