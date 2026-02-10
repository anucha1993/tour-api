<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    use HasFactory;

    // Regions
    public const REGIONS = [
        'ASIA' => 'เอเชีย',
        'EUROPE' => 'ยุโรป',
        'AMERICA' => 'อเมริกา',
        'OCEANIA' => 'โอเชียเนีย',
        'AFRICA' => 'แอฟริกา',
        'MIDDLE_EAST' => 'ตะวันออกกลาง',
    ];

    // Sub-regions
    public const SUB_REGIONS = [
        'EAST_ASIA' => 'เอเชียตะวันออก',
        'SOUTHEAST_ASIA' => 'เอเชียตะวันออกเฉียงใต้',
        'SOUTH_ASIA' => 'เอเชียใต้',
        'WEST_EUROPE' => 'ยุโรปตะวันตก',
        'EAST_EUROPE' => 'ยุโรปตะวันออก',
        'NORTH_EUROPE' => 'ยุโรปเหนือ',
        'SOUTH_EUROPE' => 'ยุโรปใต้',
    ];

    // Themes
    public const THEMES = [
        'SHOPPING' => 'ช้อปปิ้ง',
        'CULTURE' => 'วัฒนธรรม',
        'TEMPLE' => 'ไหว้พระ',
        'NATURE' => 'ธรรมชาติ',
        'BEACH' => 'ทะเล',
        'ADVENTURE' => 'ผจญภัย',
        'HONEYMOON' => 'ฮันนีมูน',
        'FAMILY' => 'ครอบครัว',
        'PREMIUM' => 'พรีเมียม',
        'BUDGET' => 'ประหยัด',
    ];

    // Badges
    public const BADGES = [
        'HOT' => 'ขายดี',
        'NEW' => 'ใหม่',
        'BEST_SELLER' => 'ยอดนิยม',
        'PROMOTION' => 'โปรโมชัน',
        'LIMITED' => 'จำนวนจำกัด',
    ];

    // Promotion Types
    public const PROMOTION_TYPE_NONE = 'none';
    public const PROMOTION_TYPE_NORMAL = 'normal';
    public const PROMOTION_TYPE_FIRE_SALE = 'fire_sale';

    public const PROMOTION_TYPES = [
        self::PROMOTION_TYPE_NONE => 'ไม่มีโปร',
        self::PROMOTION_TYPE_NORMAL => 'โปรโมชั่น',
        self::PROMOTION_TYPE_FIRE_SALE => 'โปรไฟไหม้',
    ];

    // Tour Types
    public const TOUR_TYPES = [
        'join' => 'Join Tour',
        'incentive' => 'Incentive',
        'collective' => 'Collective',
    ];

    // Suitable For
    public const SUITABLE_FOR = [
        'FAMILY' => 'ครอบครัว',
        'COUPLE' => 'คู่รัก',
        'GROUP' => 'หมู่คณะ',
        'SOLO' => 'เดี่ยว',
        'SENIOR' => 'ผู้สูงอายุ',
        'KIDS' => 'เด็ก',
    ];

    protected $fillable = [
        'wholesaler_id',
        'external_id',
        'tour_code',
        'wholesaler_tour_code',
        // Sync fields
        'data_source',
        'sync_status',
        'sync_locked',
        'last_synced_at',
        'sync_hash',
        'external_updated_at',
        'manual_override_fields',
        'title',
        'tour_type',
        'primary_country_id',
        'region',
        'sub_region',
        'duration_days',
        'duration_nights',
        'highlights',
        'shopping_highlights',
        'food_highlights',
        'special_highlights',
        'hotel_star',
        'hotel_star_min',
        'hotel_star_max',
        'inclusions',
        'exclusions',
        'conditions',
        'description',
        'slug',
        'meta_title',
        'meta_description',
        'keywords',
        'hashtags',
        'cover_image_url',
        'cover_image_alt',
        'og_image_url',
        'pdf_url',
        'docx_url',
        'themes',
        'suitable_for',
        'departure_airports',
        'min_price',
        'display_price',
        'price_adult',
        'discount_adult',
        'discount_amount',
        'discount_label',
        'promotion_type',
        'max_discount_percent',
        'next_departure_date',
        'total_departures',
        'available_seats',
        'has_promotion',
        'badge',
        'tour_category',
        'transport_id',
        'popularity_score',
        'sort_order',
        'status',
        'view_count',
        'is_published',
        'published_at',
        'updated_at_source',
    ];

    protected $casts = [
        'keywords' => 'array',
        'hashtags' => 'array',
        'themes' => 'array',
        'suitable_for' => 'array',
        'departure_airports' => 'array',
        'highlights' => 'array',
        'shopping_highlights' => 'array',
        'food_highlights' => 'array',
        'special_highlights' => 'array',
        // Sync fields
        'sync_locked' => 'boolean',
        'last_synced_at' => 'datetime',
        'external_updated_at' => 'datetime',
        'manual_override_fields' => 'array',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'display_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'next_departure_date' => 'date',
        'has_promotion' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'updated_at_source' => 'datetime',
    ];

    // Relationships
    public function wholesaler(): BelongsTo
    {
        return $this->belongsTo(Wholesaler::class);
    }

    /**
     * Primary country (for SEO, main display)
     */
    public function primaryCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'primary_country_id');
    }

    /**
     * Alias for backward compatibility
     */
    public function country(): BelongsTo
    {
        return $this->primaryCountry();
    }

    /**
     * All countries this tour visits (Many-to-Many)
     */
    public function countries()
    {
        return $this->belongsToMany(Country::class, 'tour_countries')
            ->withPivot(['is_primary', 'sort_order', 'days_in_country'])
            ->orderByPivot('sort_order');
    }

    /**
     * All cities this tour visits (Many-to-Many)
     */
    public function cities()
    {
        return $this->belongsToMany(City::class, 'tour_cities')
            ->withPivot(['country_id', 'sort_order', 'days_in_city'])
            ->orderByPivot('sort_order');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(TourLocation::class)->orderBy('sort_order');
    }

    public function gallery(): HasMany
    {
        return $this->hasMany(TourGallery::class)->orderBy('sort_order');
    }

    public function transports(): HasMany
    {
        return $this->hasMany(TourTransport::class)->orderBy('sort_order');
    }

    public function itineraries(): HasMany
    {
        return $this->hasMany(TourItinerary::class)->orderBy('day_number');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class)->orderBy('start_date');
    }

    // Accessors - Convert relative paths to full URLs
    public function getPdfUrlAttribute($value): ?string
    {
        if (!$value) return null;
        
        // If already a full URL (http/https or Cloudflare), return as-is
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        
        // Convert relative path to full URL using APP_URL
        return rtrim(config('app.url'), '/') . '/' . ltrim($value, '/');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeInRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    public function scopeInCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    public function scopeWithTheme($query, string $theme)
    {
        return $query->whereJsonContains('themes', $theme);
    }

    public function scopePriceRange($query, ?float $min, ?float $max)
    {
        if ($min !== null) {
            $query->where('min_price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('min_price', '<=', $max);
        }
        return $query;
    }

    // Methods
    public function recalculateAggregates(?array $configOverride = null): void
    {
        // Get aggregation config (global + wholesaler override)
        $config = $this->getAggregationConfig($configOverride);
        
        $openPeriods = $this->periods()
            ->where('status', 'open')
            ->where('start_date', '>=', now()->toDateString())
            ->get();

        // Get all prices from offers
        $prices = $openPeriods->map(fn($p) => $p->offer?->price_adult)->filter()->values();
        $discounts = $openPeriods->map(fn($p) => $p->offer?->discount_adult)->filter()->values();
        
        // Calculate price_adult using config method
        $priceAdult = $this->aggregateValue($prices, $config['price_adult'] ?? 'min');
        
        // Calculate discount_adult using config method
        $discountAdult = $this->aggregateValue($discounts, $config['discount_adult'] ?? 'max');
        
        // Calculate min/max prices
        $minPrice = $this->aggregateValue($prices, $config['min_price'] ?? 'min');
        $maxPrice = $this->aggregateValue($prices, $config['max_price'] ?? 'max');
        
        // Calculate display_price using config method
        $displayPrice = $this->aggregateValue($prices, $config['display_price'] ?? 'min');
        
        // หาส่วนลดจาก promotion (max discount)
        $maxPromoDiscount = 0;
        $discountLabel = null;
        foreach ($openPeriods as $period) {
            if ($period->offer) {
                $promo = $period->offer->promotions()
                    ->where('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('start_at')
                          ->orWhere('start_at', '<=', now());
                    })
                    ->where(function($q) {
                        $q->whereNull('end_at')
                          ->orWhere('end_at', '>=', now());
                    })
                    ->orderByDesc('value')
                    ->first();
                    
                if ($promo && $promo->value > $maxPromoDiscount) {
                    $maxPromoDiscount = $promo->value;
                    $discountLabel = $promo->name;
                }
            }
        }
        
        // Combine discount_adult + promotion discount
        $totalDiscount = max($discountAdult ?? 0, $maxPromoDiscount);

        // คำนวณ hotel star จาก itineraries
        $hotelStars = $this->itineraries()
            ->whereNotNull('hotel_star')
            ->pluck('hotel_star');
        
        // คำนวณ max_discount_percent และ promotion_type
        $maxDiscountPercent = 0;
        foreach ($openPeriods as $period) {
            if ($period->offer && $period->offer->price_adult > 0) {
                $discount = $period->offer->discount_adult ?? 0;
                $percent = ($discount / $period->offer->price_adult) * 100;
                $maxDiscountPercent = max($maxDiscountPercent, $percent);
            }
        }
        
        // Get promotion thresholds from settings
        $thresholds = Setting::get('promotion_thresholds', [
            'fire_sale_min_percent' => 30,
            'normal_promo_min_percent' => 1,
        ]);
        
        // Determine promotion_type based on max discount percent
        $promotionType = 'none';
        if ($maxDiscountPercent >= ($thresholds['fire_sale_min_percent'] ?? 30)) {
            $promotionType = 'fire_sale';
        } elseif ($maxDiscountPercent >= ($thresholds['normal_promo_min_percent'] ?? 1)) {
            $promotionType = 'normal';
        }

        // คำนวณ hotel_star หลัก: ใช้ค่าที่พบบ่อยสุด (mode), ถ้าเท่ากันใช้ค่าสูงสุด
        $hotelStar = null;
        if ($hotelStars->isNotEmpty()) {
            $starCounts = $hotelStars->countBy();
            $maxCount = $starCounts->max();
            // หา star ที่มี count = maxCount แล้วเอาค่าสูงสุด
            $hotelStar = $starCounts->filter(fn($count) => $count === $maxCount)->keys()->max();
        }

        $this->update([
            'price_adult' => $priceAdult,
            'discount_adult' => $discountAdult,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'display_price' => $displayPrice,
            'discount_amount' => $totalDiscount > 0 ? $totalDiscount : null,
            'discount_label' => $discountLabel,
            'next_departure_date' => $openPeriods->min('start_date'),
            'total_departures' => $openPeriods->count(),
            'available_seats' => $openPeriods->sum('available'),
            'has_promotion' => $totalDiscount > 0,
            'hotel_star' => $hotelStar,
            'hotel_star_min' => $hotelStars->min(),
            'hotel_star_max' => $hotelStars->max(),
            'promotion_type' => $promotionType,
            'max_discount_percent' => round($maxDiscountPercent, 2),
        ]);
    }
    
    /**
     * Get aggregation config (global default + wholesaler override)
     */
    protected function getAggregationConfig(?array $configOverride = null): array
    {
        // Default config
        $defaultConfig = [
            'price_adult' => 'min',
            'discount_adult' => 'max',
            'min_price' => 'min',
            'max_price' => 'max',
            'display_price' => 'min',
            'discount_amount' => 'max',
        ];
        
        // Get global config from settings
        $globalConfig = Setting::get('tour_aggregations', $defaultConfig);
        
        // Get wholesaler-specific override
        $wholesalerConfig = [];
        if ($this->wholesaler_id) {
            $apiConfig = WholesalerApiConfig::where('wholesaler_id', $this->wholesaler_id)->first();
            if ($apiConfig && $apiConfig->aggregation_config) {
                $wholesalerConfig = is_array($apiConfig->aggregation_config) 
                    ? $apiConfig->aggregation_config 
                    : json_decode($apiConfig->aggregation_config, true) ?? [];
            }
        }
        
        // Merge: default < global < wholesaler < configOverride
        return array_merge($defaultConfig, $globalConfig, $wholesalerConfig, $configOverride ?? []);
    }
    
    /**
     * Aggregate collection values using specified method
     */
    protected function aggregateValue($values, string $method): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }
        
        return match($method) {
            'min' => $values->min(),
            'max' => $values->max(),
            'avg' => round($values->avg(), 2),
            'first' => $values->first(),
            'last' => $values->last(),
            default => $values->min(),
        };
    }

    /**
     * Get promotion type label in Thai
     */
    public function getPromotionTypeLabelAttribute(): string
    {
        return self::PROMOTION_TYPES[$this->promotion_type] ?? 'ไม่มีโปร';
    }

    /**
     * Check if tour has fire sale promotion
     */
    public function isFireSale(): bool
    {
        return $this->promotion_type === self::PROMOTION_TYPE_FIRE_SALE;
    }

    /**
     * Check if tour has any promotion
     */
    public function hasPromotion(): bool
    {
        return $this->promotion_type !== self::PROMOTION_TYPE_NONE;
    }

    /**
     * Scope: Filter by promotion type
     */
    public function scopePromoType($query, string $type)
    {
        return $query->where('promotion_type', $type);
    }

    /**
     * Scope: Only fire sale tours
     */
    public function scopeFireSale($query)
    {
        return $query->where('promotion_type', self::PROMOTION_TYPE_FIRE_SALE);
    }

    /**
     * Scope: Tours with any promotion (normal or fire_sale)
     */
    public function scopeWithPromotion($query)
    {
        return $query->whereIn('promotion_type', [
            self::PROMOTION_TYPE_NORMAL,
            self::PROMOTION_TYPE_FIRE_SALE,
        ]);
    }

    /**
     * Generate tour code: NT + YYYYMM + XXX
     * เช่น NT202601001, NT202601002
     */
    public static function generateTourCode(): string
    {
        $prefix = 'NT' . now()->format('Ym'); // YYYYMM เช่น 202601
        
        // หา tour code ล่าสุดของเดือนนี้
        $lastTour = static::where('tour_code', 'like', $prefix . '%')
            ->orderByDesc('tour_code')
            ->first();
        
        if ($lastTour) {
            $lastNum = (int) substr($lastTour->tour_code, -3);
            $newNum = $lastNum + 1;
        } else {
            $newNum = 1;
        }
        
        return $prefix . str_pad($newNum, 3, '0', STR_PAD_LEFT);
    }

    // =====================================================
    // Manual Override Fields Management (for Smart Sync)
    // =====================================================

    /**
     * Check if a field has been manually overridden
     */
    public function isFieldOverridden(string $field): bool
    {
        $overrides = $this->manual_override_fields ?? [];
        return isset($overrides[$field]);
    }

    /**
     * Get list of manually overridden fields
     */
    public function getOverriddenFields(): array
    {
        return array_keys($this->manual_override_fields ?? []);
    }

    /**
     * Mark a field as manually overridden
     */
    public function markFieldAsOverridden(string $field): void
    {
        $overrides = $this->manual_override_fields ?? [];
        $overrides[$field] = now()->toIso8601String();
        $this->manual_override_fields = $overrides;
    }

    /**
     * Mark multiple fields as manually overridden
     */
    public function markFieldsAsOverridden(array $fields): void
    {
        $overrides = $this->manual_override_fields ?? [];
        $timestamp = now()->toIso8601String();
        foreach ($fields as $field) {
            $overrides[$field] = $timestamp;
        }
        $this->manual_override_fields = $overrides;
    }

    /**
     * Clear override flag for a field (allow sync to update it again)
     */
    public function clearFieldOverride(string $field): void
    {
        $overrides = $this->manual_override_fields ?? [];
        unset($overrides[$field]);
        $this->manual_override_fields = empty($overrides) ? null : $overrides;
    }

    /**
     * Clear all override flags
     */
    public function clearAllOverrides(): void
    {
        $this->manual_override_fields = null;
    }

    /**
     * Get fields that can be synced (not overridden)
     * 
     * @param array $fields Fields to check
     * @param array|null $alwaysSyncFields Fields that should always be synced regardless of override
     * @param array|null $neverSyncFields Fields that should never be synced
     * @return array Fields that can be safely synced
     */
    public function getSyncableFields(array $fields, ?array $alwaysSyncFields = null, ?array $neverSyncFields = null): array
    {
        $overrides = $this->manual_override_fields ?? [];
        $alwaysSyncFields = $alwaysSyncFields ?? [];
        $neverSyncFields = $neverSyncFields ?? [];
        
        return array_filter($fields, function($field) use ($overrides, $alwaysSyncFields, $neverSyncFields) {
            // Never sync these fields
            if (in_array($field, $neverSyncFields)) {
                return false;
            }
            
            // Always sync these fields
            if (in_array($field, $alwaysSyncFields)) {
                return true;
            }
            
            // Otherwise, only sync if not manually overridden
            return !isset($overrides[$field]);
        });
    }
}
