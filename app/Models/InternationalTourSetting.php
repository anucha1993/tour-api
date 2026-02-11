<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InternationalTourSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'conditions',
        'display_limit',
        'per_page',
        'sort_by',
        'show_periods',
        'max_periods_display',
        'show_transport',
        'show_hotel_star',
        'show_meal_count',
        'show_commission',
        'filter_country',
        'filter_city',
        'filter_search',
        'filter_airline',
        'filter_departure_month',
        'filter_price_range',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'conditions' => 'array',
        'display_limit' => 'integer',
        'per_page' => 'integer',
        'max_periods_display' => 'integer',
        'show_periods' => 'boolean',
        'show_transport' => 'boolean',
        'show_hotel_star' => 'boolean',
        'show_meal_count' => 'boolean',
        'show_commission' => 'boolean',
        'filter_country' => 'boolean',
        'filter_city' => 'boolean',
        'filter_search' => 'boolean',
        'filter_airline' => 'boolean',
        'filter_departure_month' => 'boolean',
        'filter_price_range' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Reuse condition types from TourTab
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
        'created_within_days' => 'สร้างภายใน (วัน)',
        'has_available_seats' => 'มีที่ว่าง',
    ];

    const SORT_OPTIONS = [
        'popular' => 'ยอดนิยม',
        'price_asc' => 'ราคาต่ำ-สูง',
        'price_desc' => 'ราคาสูง-ต่ำ',
        'newest' => 'ใหม่ล่าสุด',
        'departure_date' => 'วันเดินทางใกล้สุด',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get base query for active international tours (excluding Thailand)
     */
    public function getBaseQuery()
    {
        $query = Tour::query()
            ->where('status', 'active')
            ->whereHas('periods', function ($q) {
                $q->where('start_date', '>=', now()->toDateString())
                  ->where('status', 'open');
            })
            // Exclude domestic tours (Thailand id=8)
            ->where(function ($q) {
                $q->where('primary_country_id', '!=', 8)
                  ->orWhereNull('primary_country_id');
            });

        // Apply conditions
        $this->applyConditions($query);

        return $query;
    }

    /**
     * Get tours matching conditions with pagination support
     */
    public function getTours(int $perPage = null, array $filters = [])
    {
        $query = $this->getBaseQuery();

        // Apply user-provided filters (from URL params)
        $this->applyUserFilters($query, $filters);

        // Apply sorting from user or default
        $sortBy = $filters['sort_by'] ?? $this->sort_by;
        $this->applySorting($query, $sortBy);

        $perPage = $perPage ?? $this->per_page;

        return $query->with([
            'primaryCountry:id,name_th,name_en,iso2,flag_emoji',
            'cities:id,name_th,name_en,slug',
            'transports' => function ($q) {
                $q->orderBy('sort_order');
            },
            'transports.transport:id,code,name,image',
            'periods' => function ($q) {
                $q->where('start_date', '>=', now()->toDateString())
                  ->where('status', 'open')
                  ->where('is_visible', true)
                  ->orderBy('start_date')
                  ->limit($this->max_periods_display);
            },
            'periods.offer',
        ])->paginate($perPage);
    }

    /**
     * Apply admin-defined conditions
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
            $query->whereIn('primary_country_id', (array) $conditions['countries']);
        }
        if (!empty($conditions['regions'])) {
            $query->whereHas('primaryCountry', function ($q) use ($conditions) {
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
            $query->where('duration_days', '>=', $conditions['min_days']);
        }
        if (!empty($conditions['max_days'])) {
            $query->where('duration_days', '<=', $conditions['max_days']);
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
     * Apply user-facing filters (from URL query params)
     */
    protected function applyUserFilters($query, array $filters)
    {
        // Country filter
        if (!empty($filters['country_id'])) {
            $query->where('primary_country_id', $filters['country_id']);
        }

        // City filter
        if (!empty($filters['city_id'])) {
            $query->whereHas('cities', function ($q) use ($filters) {
                $q->where('cities.id', $filters['city_id']);
            });
        }

        // Search keyword
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('tour_code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Airline/Transport filter
        if (!empty($filters['airline_id'])) {
            $query->whereHas('transports', function ($q) use ($filters) {
                $q->where('transport_id', $filters['airline_id']);
            });
        }

        // Departure month filter (format: YYYY-MM)
        if (!empty($filters['departure_month'])) {
            $month = $filters['departure_month'];
            $query->whereHas('periods', function ($q) use ($month) {
                $q->where('status', 'open')
                  ->whereRaw("DATE_FORMAT(start_date, '%Y-%m') = ?", [$month]);
            });
        }

        // Price range
        if (!empty($filters['price_min'])) {
            $query->where('min_price', '>=', $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $query->where('min_price', '<=', $filters['price_max']);
        }

        return $query;
    }

    /**
     * Apply sorting
     */
    protected function applySorting($query, string $sortBy = null)
    {
        $sortBy = $sortBy ?? $this->sort_by ?? 'popular';

        switch ($sortBy) {
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
