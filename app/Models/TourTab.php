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
        'display_mode',
        'badge_icon',
        'badge_expires_at',
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
        'badge_expires_at' => 'datetime',
    ];

    // Display mode options
    const DISPLAY_TAB = 'tab';
    const DISPLAY_BADGE = 'badge';
    const DISPLAY_BOTH = 'both';
    const DISPLAY_PERIOD = 'period';

    const DISPLAY_MODES = [
        self::DISPLAY_TAB => 'แท็บหน้าแรก',
        self::DISPLAY_BADGE => 'Badge ทุกหน้า',
        self::DISPLAY_BOTH => 'ทั้งแท็บ + Badge',
        self::DISPLAY_PERIOD => 'แสดงในรอบเดินทาง',
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
        'discount_min_amount' => 'ส่วนลดรอบเดินทางขั้นต่ำ (บาท)',
        'discount_total_min_amount' => 'ส่วนลดรวมขั้นต่ำ (บาท)',
        'tour_type' => 'ประเภททัวร์',
        'min_days' => 'จำนวนวันขั้นต่ำ',
        'max_days' => 'จำนวนวันสูงสุด',
        'is_premium' => 'ทัวร์พรีเมียม',
        'created_within_days' => 'สร้างภายใน (วัน)',
        'has_available_seats' => 'มีที่ว่าง',
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
            ->where('available_seats', '>', 0) // Exclude Sold Out tours
            ->whereHas('periods', function ($q) {
                $q->where('start_date', '>=', now()->toDateString())
                  ->where('status', 'open');
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
        $rawConditions = $this->conditions ?? [];
        
        // Convert from [{type: 'price_min', value: 10000}] to ['price_min' => 10000]
        $conditions = [];
        foreach ($rawConditions as $cond) {
            if (is_array($cond) && isset($cond['type']) && isset($cond['value'])) {
                $conditions[$cond['type']] = $cond['value'];
            } elseif (is_object($cond) && isset($cond->type) && isset($cond->value)) {
                $conditions[$cond->type] = $cond->value;
            }
        }
        
        // If already flat array format (backwards compatibility)
        if (empty($conditions) && !empty($rawConditions) && isset($rawConditions['price_min'])) {
            $conditions = $rawConditions;
        }

        // Price range - use tours.min_price directly
        if (!empty($conditions['price_min'])) {
            $query->where('min_price', '>=', $conditions['price_min']);
        }
        if (!empty($conditions['price_max'])) {
            $query->where('min_price', '<=', $conditions['price_max']);
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
                $q->where('start_date', '<=', now()->addDays($conditions['departure_within_days'])->toDateString());
            });
        }

        // Has discount - use tours.has_promotion or discount_adult > 0
        if (!empty($conditions['has_discount'])) {
            $query->where(function ($q) {
                $q->where('has_promotion', true)
                  ->orWhere('discount_adult', '>', 0)
                  ->orWhere('discount_amount', '>', 0)
                  ->orWhere('max_discount_percent', '>', 0);
            });
        }

        // Discount min percent - use tours.max_discount_percent
        if (!empty($conditions['discount_min_percent'])) {
            $query->where('max_discount_percent', '>=', $conditions['discount_min_percent']);
        }

        // Discount min amount (baht) - ส่วนลดจากรอบเดินทาง (offers.discount_adult -> tours.discount_adult)
        if (!empty($conditions['discount_min_amount'])) {
            $query->where('discount_adult', '>=', $conditions['discount_min_amount']);
        }

        // Discount total min amount (baht) - ส่วนลดรวมทั้งหมด (รอบเดินทาง + โปรโมชั่น -> tours.discount_amount)
        if (!empty($conditions['discount_total_min_amount'])) {
            $query->where('discount_amount', '>=', $conditions['discount_total_min_amount']);
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

        // Has available seats - use tours.available_seats
        if (!empty($conditions['has_available_seats'])) {
            $query->where('available_seats', '>', 0);
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
                // Use tours.min_price directly
                $query->orderByRaw('COALESCE(min_price, 999999999) ASC');
                break;
            case self::SORT_PRICE_DESC:
                $query->orderByRaw('COALESCE(min_price, 0) DESC');
                break;
            case self::SORT_NEWEST:
                $query->orderBy('created_at', 'desc');
                break;
            case self::SORT_DEPARTURE_DATE:
                // Use tours.next_departure_date directly
                $query->orderByRaw('COALESCE(next_departure_date, "9999-12-31") ASC');
                break;
            case self::SORT_POPULAR:
            default:
                // Use COALESCE to treat NULL as 0, so tours with NULL view_count are treated same as 0
                $query->orderByRaw('COALESCE(view_count, 0) DESC')->orderBy('created_at', 'desc');
                break;
        }

        return $query;
    }
}
