<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TourTab extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'badge_text',
        'badge_color',
        'conditions',
        'display_limit',
        'sort_by',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'display_limit' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // Sort options
    const SORT_POPULAR = 'popular';
    const SORT_PRICE_ASC = 'price_asc';
    const SORT_PRICE_DESC = 'price_desc';
    const SORT_NEWEST = 'newest';
    const SORT_DEPARTURE_DATE = 'departure_date';

    const SORT_OPTIONS = [
        self::SORT_POPULAR => 'ยอดนิยม',
        self::SORT_PRICE_ASC => 'ราคาต่ำ-สูง',
        self::SORT_PRICE_DESC => 'ราคาสูง-ต่ำ',
        self::SORT_NEWEST => 'ใหม่ล่าสุด',
        self::SORT_DEPARTURE_DATE => 'วันเดินทางใกล้สุด',
    ];

    // Condition types
    const CONDITION_TYPES = [
        'price_min' => 'ราคาขั้นต่ำ',
        'price_max' => 'ราคาสูงสุด',
        'countries' => 'ประเทศ',
        'regions' => 'ภูมิภาค',
        'wholesalers' => 'Wholesaler',
        'departure_within_days' => 'เดินทางภายใน (วัน)',
        'has_discount' => 'มีส่วนลด',
        'discount_min_percent' => 'ส่วนลดขั้นต่ำ (%)',
        'tour_type' => 'ประเภททัวร์',
        'min_days' => 'จำนวนวันขั้นต่ำ',
        'max_days' => 'จำนวนวันสูงสุด',
        'is_premium' => 'ทัวร์พรีเมียม',
        'created_within_days' => 'สร้างภายใน (วัน)',
        'has_available_seats' => 'มีที่ว่าง',
        'min_views' => 'ยอดคนเข้าชมขั้นต่ำ',
    ];

    /**
     * Auto-generate slug from name if not provided
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    /**
     * Scope for active tabs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered tabs
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get tours matching this tab's conditions
     */
    public function getTours(int $limit = null)
    {
        $limit = $limit ?? $this->display_limit;
        
        $query = Tour::query()
            ->where('status', 'active')
            ->whereHas('periods', function ($q) {
                $q->where('departure_date', '>=', now()->toDateString())
                  ->where('status', 'active');
            });

        // Apply conditions
        $this->applyConditions($query);

        // Apply sorting
        $this->applySorting($query);

        return $query->limit($limit)->get();
    }

    /**
     * Apply filter conditions to query
     */
    protected function applyConditions($query)
    {
        $conditions = $this->conditions ?? [];

        // Price range
        if (!empty($conditions['price_min'])) {
            $query->whereHas('periods', function ($q) use ($conditions) {
                $q->where('price_adult', '>=', $conditions['price_min']);
            });
        }
        if (!empty($conditions['price_max'])) {
            $query->whereHas('periods', function ($q) use ($conditions) {
                $q->where('price_adult', '<=', $conditions['price_max']);
            });
        }

        // Countries
        if (!empty($conditions['countries'])) {
            $query->whereIn('country_id', (array) $conditions['countries']);
        }

        // Regions
        if (!empty($conditions['regions'])) {
            $query->whereHas('country', function ($q) use ($conditions) {
                $q->whereIn('region', (array) $conditions['regions']);
            });
        }

        // Wholesalers
        if (!empty($conditions['wholesalers'])) {
            $query->whereIn('wholesaler_id', (array) $conditions['wholesalers']);
        }

        // Departure within days
        if (!empty($conditions['departure_within_days'])) {
            $query->whereHas('periods', function ($q) use ($conditions) {
                $q->where('departure_date', '<=', now()->addDays($conditions['departure_within_days'])->toDateString());
            });
        }

        // Has discount
        if (!empty($conditions['has_discount'])) {
            $query->whereHas('periods', function ($q) {
                $q->where('discount_percent', '>', 0)
                  ->orWhereColumn('price_adult', '<', 'original_price');
            });
        }

        // Discount min percent
        if (!empty($conditions['discount_min_percent'])) {
            $query->whereHas('periods', function ($q) use ($conditions) {
                $q->where('discount_percent', '>=', $conditions['discount_min_percent']);
            });
        }

        // Tour type
        if (!empty($conditions['tour_type'])) {
            $query->where('tour_type', $conditions['tour_type']);
        }

        // Duration (days)
        if (!empty($conditions['min_days'])) {
            $query->where('days', '>=', $conditions['min_days']);
        }
        if (!empty($conditions['max_days'])) {
            $query->where('days', '<=', $conditions['max_days']);
        }

        // Premium tours
        if (!empty($conditions['is_premium'])) {
            $query->where('is_premium', true);
        }

        // Created within days
        if (!empty($conditions['created_within_days'])) {
            $query->where('created_at', '>=', now()->subDays($conditions['created_within_days']));
        }

        // Has available seats
        if (!empty($conditions['has_available_seats'])) {
            $query->whereHas('periods', function ($q) {
                $q->where('available_seats', '>', 0);
            });
        }

        // Minimum view count
        if (!empty($conditions['min_views'])) {
            $query->where('view_count', '>=', (int) $conditions['min_views']);
        }

        return $query;
    }

    /**
     * Apply sorting to query
     */
    protected function applySorting($query)
    {
        switch ($this->sort_by) {
            case self::SORT_PRICE_ASC:
                $query->orderByRaw('(SELECT MIN(price_adult) FROM periods WHERE periods.tour_id = tours.id AND periods.status = "active") ASC');
                break;
            case self::SORT_PRICE_DESC:
                $query->orderByRaw('(SELECT MIN(price_adult) FROM periods WHERE periods.tour_id = tours.id AND periods.status = "active") DESC');
                break;
            case self::SORT_NEWEST:
                $query->orderBy('created_at', 'desc');
                break;
            case self::SORT_DEPARTURE_DATE:
                $query->orderByRaw('(SELECT MIN(departure_date) FROM periods WHERE periods.tour_id = tours.id AND periods.status = "active" AND periods.departure_date >= CURDATE()) ASC');
                break;
            case self::SORT_POPULAR:
            default:
                $query->orderBy('view_count', 'desc')->orderBy('created_at', 'desc');
                break;
        }

        return $query;
    }
}
