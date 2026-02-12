<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PopularCountrySetting extends Model
{
    use HasFactory;

    // Selection modes
    public const MODE_AUTO = 'auto';
    public const MODE_MANUAL = 'manual';

    public const SELECTION_MODES = [
        self::MODE_AUTO => 'อัตโนมัติ (ตามจำนวนทัวร์)',
        self::MODE_MANUAL => 'กำหนดเอง',
    ];

    // Sort options
    public const SORT_BY_TOUR_COUNT = 'tour_count';
    public const SORT_BY_NAME = 'name';
    public const SORT_BY_MANUAL = 'manual';

    public const SORT_OPTIONS = [
        self::SORT_BY_TOUR_COUNT => 'จำนวนทัวร์',
        self::SORT_BY_NAME => 'ชื่อประเทศ',
        self::SORT_BY_MANUAL => 'กำหนดเอง',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'selection_mode',
        'filters',
        'tour_conditions',
        'display_count',
        'min_tour_count',
        'sort_by',
        'sort_direction',
        'is_active',
        'sort_order',
        'cache_minutes',
        'last_cached_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'tour_conditions' => 'array',
        'is_active' => 'boolean',
        'display_count' => 'integer',
        'min_tour_count' => 'integer',
        'sort_order' => 'integer',
        'cache_minutes' => 'integer',
        'last_cached_at' => 'datetime',
    ];

    /**
     * Relationship: Country items with custom display data
     * Items can be used in BOTH modes:
     * - Manual mode: Defines which countries to show and their display data
     * - Auto mode: Provides custom display data (images, titles) for auto-selected countries
     */
    public function items(): HasMany
    {
        return $this->hasMany(PopularCountryItem::class, 'setting_id')->orderBy('sort_order');
    }

    /**
     * Relationship: Active country items
     */
    public function activeItems(): HasMany
    {
        return $this->hasMany(PopularCountryItem::class, 'setting_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /**
     * Scope: Active settings only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Get the cache key for this setting
     */
    public function getCacheKey(): string
    {
        return "popular_countries:{$this->slug}";
    }

    /**
     * Clear cache for this setting
     */
    public function clearCache(): void
    {
        Cache::forget($this->getCacheKey());
    }

    /**
     * Get popular countries based on this setting's configuration
     * 
     * @return array Countries with tour counts and custom display data
     */
    public function getPopularCountries(): array
    {
        $cacheKey = $this->getCacheKey();
        $cacheMinutes = $this->cache_minutes ?: 60;

        return Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () {
            return $this->fetchPopularCountries();
        });
    }

    /**
     * Fetch popular countries without cache
     */
    public function fetchPopularCountries(): array
    {
        // Build base tour query with conditions
        $tourQuery = $this->buildBaseTourQuery();

        if ($this->selection_mode === self::MODE_MANUAL) {
            // Manual mode: Get items with custom display data
            return $this->getManualCountries($tourQuery);
        }

        // Auto mode: Get top countries by tour count
        return $this->getAutoCountries($tourQuery);
    }

    /**
     * Build base tour query with MANDATORY conditions
     * 
     * Mandatory conditions (always applied):
     * - Status is 'active' (excludes draft, closed)
     *   Note: When status is 'active' means it's ready to show on website
     *   (UI has 3 statuses: แบบร่าง/draft, เปิดใช้งาน/active, ปิดใช้งาน/closed)
     * - Has at least one upcoming period (status='open', start_date >= today)
     *   This excludes tours without periods or tours that have already departed
     * - Has available seats (available_seats > 0)
     *   This excludes sold out tours
     */
    protected function buildBaseTourQuery()
    {
        // Status 'active' = เปิดใช้งาน (ready to display on website)
        $query = Tour::query()
            ->where('status', 'active')
            ->where('available_seats', '>', 0); // Exclude Sold Out tours

        // Apply additional filters from settings
        if (!empty($this->filters)) {
            $filters = $this->filters;

            // Filter by wholesaler
            if (!empty($filters['wholesaler_ids'])) {
                $query->whereIn('wholesaler_id', $filters['wholesaler_ids']);
            }

            // Filter by themes
            if (!empty($filters['themes'])) {
                $query->where(function ($q) use ($filters) {
                    foreach ($filters['themes'] as $theme) {
                        $q->orWhereJsonContains('themes', $theme);
                    }
                });
            }

            // Filter by price range
            if (!empty($filters['min_price'])) {
                $query->where('min_price', '>=', $filters['min_price']);
            }
            if (!empty($filters['max_price'])) {
                $query->where('min_price', '<=', $filters['max_price']);
            }

            // Filter by hotel star
            if (!empty($filters['hotel_star_min'])) {
                $query->where('hotel_star', '>=', $filters['hotel_star_min']);
            }
            if (!empty($filters['hotel_star_max'])) {
                $query->where('hotel_star', '<=', $filters['hotel_star_max']);
            }

            // Filter by duration
            if (!empty($filters['duration_min'])) {
                $query->where('duration_days', '>=', $filters['duration_min']);
            }
            if (!empty($filters['duration_max'])) {
                $query->where('duration_days', '<=', $filters['duration_max']);
            }

            // Filter by region
            if (!empty($filters['regions'])) {
                $query->whereIn('region', $filters['regions']);
            }
        }

        // MANDATORY: Only tours with upcoming open periods (excludes tours without periods or already departed)
        $query->whereHas('periods', function ($q) {
            $q->where('status', 'open')
              ->where('start_date', '>=', now()->toDateString());
        });

        // Apply additional tour conditions
        if (!empty($this->tour_conditions)) {
            $conditions = $this->tour_conditions;

            // Additional period filters
            $hasAdditionalPeriodFilters = !empty($conditions['travel_months']) || 
                                          !empty($conditions['travel_date_from']) || 
                                          !empty($conditions['travel_date_to']) || 
                                          !empty($conditions['min_available_seats']);

            if ($hasAdditionalPeriodFilters) {
                $query->whereHas('periods', function ($q) use ($conditions) {
                    $q->where('status', 'open')
                      ->where('start_date', '>=', now()->toDateString());

                    // Filter by travel month
                    if (!empty($conditions['travel_months'])) {
                        $q->where(function ($sq) use ($conditions) {
                            foreach ($conditions['travel_months'] as $month) {
                                $sq->orWhereMonth('start_date', $month);
                            }
                        });
                    }

                    // Filter by travel date range
                    if (!empty($conditions['travel_date_from'])) {
                        $q->where('start_date', '>=', $conditions['travel_date_from']);
                    }
                    if (!empty($conditions['travel_date_to'])) {
                        $q->where('start_date', '<=', $conditions['travel_date_to']);
                    }

                    // Has available seats
                    if (!empty($conditions['min_available_seats'])) {
                        $q->where('available', '>=', $conditions['min_available_seats']);
                    }
                });
            }
        }

        return $query;
    }

    /**
     * Get manually selected countries with custom display data
     */
    protected function getManualCountries($tourQuery): array
    {
        // Get active items with country data
        $items = $this->activeItems()->with('country')->get();
        
        if ($items->isEmpty()) {
            return [];
        }

        $countryIds = $items->pluck('country_id')->toArray();
        
        // Get tour counts per country
        $tourCounts = (clone $tourQuery)
            ->whereIn('primary_country_id', $countryIds)
            ->select('primary_country_id', DB::raw('COUNT(*) as tour_count'))
            ->groupBy('primary_country_id')
            ->pluck('tour_count', 'primary_country_id')
            ->toArray();

        // Map items with tour counts
        $countries = $items
            ->map(function ($item) use ($tourCounts) {
                $tourCount = $tourCounts[$item->country_id] ?? 0;
                
                // Filter by min_tour_count
                if ($tourCount < $this->min_tour_count) {
                    return null;
                }

                return $this->formatItemData($item, $tourCount);
            })
            ->filter()
            ->values()
            ->toArray();

        // Sort if not manual order
        if ($this->sort_by !== self::SORT_BY_MANUAL) {
            $countries = $this->sortCountries($countries);
        }

        return array_slice($countries, 0, $this->display_count);
    }

    /**
     * Get auto-selected countries by tour count
     */
    protected function getAutoCountries($tourQuery): array
    {
        // Get tour counts per country
        $tourCountsQuery = (clone $tourQuery)
            ->select('primary_country_id', DB::raw('COUNT(*) as tour_count'))
            ->groupBy('primary_country_id')
            ->having('tour_count', '>=', $this->min_tour_count);

        // Apply sorting
        if ($this->sort_by === self::SORT_BY_TOUR_COUNT) {
            $tourCountsQuery->orderBy('tour_count', $this->sort_direction);
        }

        $tourCounts = $tourCountsQuery
            ->limit($this->display_count * 2) // Get extra for safety
            ->pluck('tour_count', 'primary_country_id')
            ->toArray();

        if (empty($tourCounts)) {
            return [];
        }

        // Load saved items for custom display data (images, titles, etc.)
        // If items relation is already loaded (e.g., from preview with existing setting), use it
        // Otherwise, query from database
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            $savedItems = $this->items->where('is_active', true)->keyBy('country_id');
        } elseif ($this->exists) {
            $savedItems = $this->activeItems()->with('country')->get()->keyBy('country_id');
        } else {
            $savedItems = collect();
        }

        // Get countries
        $countries = Country::whereIn('id', array_keys($tourCounts))
            ->where('is_active', true)
            ->get()
            ->map(function ($country) use ($tourCounts, $savedItems) {
                $tourCount = $tourCounts[$country->id] ?? 0;
                
                // Check if we have custom display data for this country
                if ($savedItems->has($country->id)) {
                    return $this->formatItemData($savedItems->get($country->id), $tourCount);
                }
                
                return $this->formatCountryData($country, $tourCount);
            })
            ->toArray();

        // Sort countries
        $countries = $this->sortCountries($countries);

        return array_slice($countries, 0, $this->display_count);
    }

    /**
     * Sort countries based on setting
     */
    protected function sortCountries(array $countries): array
    {
        usort($countries, function ($a, $b) {
            if ($this->sort_by === self::SORT_BY_NAME) {
                $result = strcmp($a['name_th'] ?? $a['name_en'], $b['name_th'] ?? $b['name_en']);
            } else {
                $result = $a['tour_count'] <=> $b['tour_count'];
            }

            return $this->sort_direction === 'desc' ? -$result : $result;
        });

        return $countries;
    }

    /**
     * Format item data for response (manual mode with custom data)
     */
    protected function formatItemData(PopularCountryItem $item, int $tourCount): array
    {
        $country = $item->country;
        
        return [
            'id' => $country->id,
            'iso2' => $country->iso2,
            'iso3' => $country->iso3,
            'name_en' => $country->name_en,
            'name_th' => $country->name_th,
            'slug' => $country->slug,
            'region' => $country->region,
            'flag_emoji' => $country->flag_emoji,
            'tour_count' => $tourCount,
            // Custom display data
            'display_name' => $item->display_name ?: ($country->name_th ?: $country->name_en),
            'image_url' => $item->image,
            'alt_text' => $item->alt_text,
            'title' => $item->title,
            'subtitle' => $item->subtitle,
            'link_url' => $item->link_url,
        ];
    }

    /**
     * Format country data for response (auto mode)
     */
    protected function formatCountryData(Country $country, int $tourCount): array
    {
        return [
            'id' => $country->id,
            'iso2' => $country->iso2,
            'iso3' => $country->iso3,
            'name_en' => $country->name_en,
            'name_th' => $country->name_th,
            'slug' => $country->slug,
            'region' => $country->region,
            'flag_emoji' => $country->flag_emoji,
            'tour_count' => $tourCount,
            // Default display data
            'display_name' => $country->name_th ?: $country->name_en,
            'image_url' => null,
            'alt_text' => null,
            'title' => null,
            'subtitle' => null,
            'link_url' => null,
        ];
    }

    /**
     * Preview the result without caching
     */
    public function preview(): array
    {
        return $this->fetchPopularCountries();
    }

    /**
     * Preview with specific country IDs (for manual mode preview before saving)
     */
    public function previewWithCountryIds(array $countryIds): array
    {
        $tourQuery = $this->buildBaseTourQuery();
        
        // Get countries
        $countries = Country::whereIn('id', $countryIds)
            ->where('is_active', true)
            ->get();
        
        if ($countries->isEmpty()) {
            return [];
        }

        // Get tour counts per country
        $tourCounts = (clone $tourQuery)
            ->whereIn('primary_country_id', $countryIds)
            ->select('primary_country_id', DB::raw('COUNT(*) as tour_count'))
            ->groupBy('primary_country_id')
            ->pluck('tour_count', 'primary_country_id')
            ->toArray();

        // Map countries with tour counts
        $result = $countries
            ->map(function ($country) use ($tourCounts) {
                $tourCount = $tourCounts[$country->id] ?? 0;
                
                // Filter by min_tour_count
                if ($tourCount < $this->min_tour_count) {
                    return null;
                }

                return $this->formatCountryData($country, $tourCount);
            })
            ->filter()
            ->values()
            ->toArray();

        // Sort
        $result = $this->sortCountries($result);

        return array_slice($result, 0, $this->display_count);
    }

    /**
     * Get filter options for the UI
     */
    public static function getFilterOptions(): array
    {
        return [
            'selection_modes' => self::SELECTION_MODES,
            'sort_options' => self::SORT_OPTIONS,
            'themes' => Tour::THEMES,
            'regions' => Country::REGIONS,
        ];
    }
}
