<?php

namespace App\Jobs;

use App\Models\Tour;
use App\Models\Period;
use App\Models\Offer;
use App\Models\Setting;
use App\Models\Transport;
use App\Models\TourItinerary;
use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use App\Models\SyncLog;
use App\Services\NotificationService;
use App\Services\WholesalerAdapters\AdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SyncPeriodsJob - Phase 2 of Two-Phase Sync
 * 
 * ดึงข้อมูลรอบเดินทาง (periods/schedules) จาก API แยก
 * ใช้สำหรับ Wholesaler ที่แยก endpoint ระหว่าง tours และ periods
 * 
 * @see docs/api/two-phase-sync.md
 */
class SyncPeriodsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    protected int $tourId;
    protected string $externalId;
    protected int $integrationId;
    protected ?WholesalerApiConfig $config = null;

    /**
     * Create a new job instance.
     * 
     * @param int $tourId Tour ID
     * @param string $externalId External ID from API
     * @param int $integrationId Integration ID (WholesalerApiConfig primary key, NOT wholesaler_id)
     */
    public function __construct(int $tourId, string $externalId, int $integrationId)
    {
        $this->tourId = $tourId;
        $this->externalId = $externalId;
        $this->integrationId = $integrationId;
        $this->onQueue('periods');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SyncPeriodsJob: Starting', [
            'tour_id' => $this->tourId,
            'external_id' => $this->externalId,
            'integration_id' => $this->integrationId,
        ]);

        $tour = Tour::find($this->tourId);
        if (!$tour) {
            Log::warning('SyncPeriodsJob: Tour not found', ['tour_id' => $this->tourId]);
            return;
        }

        // Use integration ID (primary key) to find config - supports multiple integrations per wholesaler
        $this->config = WholesalerApiConfig::find($this->integrationId);
        if (!$this->config) {
            Log::warning('SyncPeriodsJob: Config not found', ['integration_id' => $this->integrationId]);
            return;
        }
        
        // Get wholesaler_id from config for field mappings lookup
        $wholesalerId = $this->config->wholesaler_id;

        try {
            // Get periods endpoint from auth_credentials
            $credentials = $this->config->auth_credentials ?? [];
            $endpoints = $credentials['endpoints'] ?? [];
            $periodsEndpoint = $endpoints['periods'] ?? null;

            if (!$periodsEndpoint) {
                Log::warning('SyncPeriodsJob: No periods endpoint configured', [
                    'integration_id' => $this->integrationId,
                ]);
                return;
            }

            // Build URL - replace all placeholders dynamically from tour data
            $placeholders = [
                '{external_id}'          => $this->externalId,
                '{tour_id}'              => $this->externalId,
                '{id}'                   => $this->externalId,
                '{series_id}'            => $this->externalId,
                '{tour_code}'            => $tour->tour_code ?? '',
                '{wholesaler_tour_code}' => $tour->wholesaler_tour_code ?? '',
                '{code}'                 => $tour->wholesaler_tour_code ?? $tour->tour_code ?? '',
                '{slug}'                 => $tour->slug ?? '',
            ];
            $url = str_replace(array_keys($placeholders), array_values($placeholders), $periodsEndpoint);

            // Fetch periods from API - use wholesaler_id for adapter (adapter is per wholesaler)
            $adapter = AdapterFactory::create($wholesalerId);
            $result = $adapter->fetchPeriods($url);

            if (!$result->success) {
                Log::error('SyncPeriodsJob: Failed to fetch periods', [
                    'tour_id' => $this->tourId,
                    'error' => $result->error,
                ]);
                return;
            }

            // Get aggregation config for nested data structure
            $aggConfig = $this->config->aggregation_config ?? [];
            $dataStructure = $aggConfig['data_structure'] ?? [];
            $departuresPath = $dataStructure['departures']['path'] ?? null;

            // Flatten nested periods if custom path is configured
            $periods = $result->periods ?? [];
            if ($departuresPath && !empty($periods)) {
                $periods = $this->flattenNestedPeriods($periods, $departuresPath);
                Log::info('SyncPeriodsJob: Flattened nested periods', [
                    'tour_id' => $this->tourId,
                    'path' => $departuresPath,
                    'original_count' => count($result->periods ?? []),
                    'flattened_count' => count($periods),
                ]);
            }

            // Get field mappings for periods (mappings are per wholesaler_id, not integration_id)
            $mappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
                ->where('section_name', 'departure')
                ->where('is_active', true)
                ->get();

            // Process each period
            $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
            
            foreach ($periods as $rawPeriod) {
                try {
                    $periodData = $this->transformPeriodData($rawPeriod, $mappings, $departuresPath);
                    $this->syncPeriod($tour, $periodData, $stats);
                } catch (\Exception $e) {
                    Log::error('SyncPeriodsJob: Error processing period', [
                        'tour_id' => $this->tourId,
                        'error' => $e->getMessage(),
                    ]);
                    $stats['skipped']++;
                }
            }

            // Sync Itinerary (from same API response)
            $itinerariesPath = $dataStructure['itineraries']['path'] ?? null;
            // Use rawData (full API response) for itineraries/cities, not periods array
            $rawData = $result->rawData ?? [];
            $itineraryStats = $this->syncItineraries($tour, $rawData, $itinerariesPath, $this->config);

            // Sync Cities (from same API response)
            $citiesPath = $dataStructure['cities']['path'] ?? null;
            $cityStats = $this->syncCities($tour, $rawData, $citiesPath, $this->config);

            // Sync Transport (from tour_airline in API response)
            $transportStats = $this->syncTransport($tour, $rawData);

            // Recalculate tour's aggregated fields (price, discount, hotel_star, etc.)
            $tour->recalculateAggregates();

            Log::info('SyncPeriodsJob: Completed', [
                'tour_id' => $this->tourId,
                'stats' => $stats,
                'itineraries' => $itineraryStats,
                'cities' => $cityStats,
                'transport' => $transportStats,
            ]);

        } catch (\Exception $e) {
            Log::error('SyncPeriodsJob: Failed', [
                'tour_id' => $this->tourId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send notification
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->notifyIntegration($this->config->id, 'sync_error', [
                    'error' => $e->getMessage(),
                    'tour_id' => $this->tourId,
                    'external_id' => $this->externalId,
                    'sync_type' => 'periods',
                ]);
            } catch (\Exception $notifyError) {
                Log::warning('SyncPeriodsJob: Failed to send notification', [
                    'error' => $notifyError->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Flatten nested periods array based on custom path
     * e.g., if path is "periods[].tour_period[]", extract tour_period items from each period
     */
    protected function flattenNestedPeriods(array $data, string $path): array
    {
        // Parse path to get nested array key
        // e.g., "periods[].tour_period[]" -> need to extract "tour_period" from each period
        // But the API response for periods endpoint might already be at "periods" level
        // So we need to check for the nested part after "periods[]."
        $path = preg_replace('/^[Pp]eriods\[\]\./', '', $path);
        
        // If path is like "tour_period[]", extract that nested array
        if (preg_match('/^([a-zA-Z_]+)\[\]/', $path, $matches)) {
            $nestedKey = $matches[0]; // e.g., "tour_period[]"
            $nestedKey = str_replace('[]', '', $nestedKey); // e.g., "tour_period"
            
            $flattened = [];
            foreach ($data as $item) {
                if (isset($item[$nestedKey]) && is_array($item[$nestedKey])) {
                    foreach ($item[$nestedKey] as $nestedItem) {
                        $flattened[] = $nestedItem;
                    }
                } else {
                    // If no nested key, keep the item as is
                    $flattened[] = $item;
                }
            }
            return $flattened;
        }
        
        return $data;
    }

    /**
     * Transform raw period data using field mappings
     */
    protected function transformPeriodData(array $rawPeriod, $mappings, ?string $basePath = null): array
    {
        $result = [];

        foreach ($mappings as $mapping) {
            $fieldPath = $mapping->their_field_path ?? $mapping->their_field ?? null;
            if (empty($fieldPath)) {
                continue;
            }
            
            // Clean path if we have a base path
            if ($basePath) {
                $fieldPath = $this->cleanNestedPath($fieldPath, $basePath);
            }
            
            $value = $this->extractValue($rawPeriod, $fieldPath);
            
            if ($value !== null) {
                $value = $this->applyTransform($value, $mapping->transform_type, $mapping->transform_config);
            }

            $result[$mapping->our_field] = $value;
        }

        return $result;
    }

    /**
     * Clean field path by removing the base path prefix
     * e.g., "periods[].tour_period[].period_id" with base "periods[].tour_period[]" -> "period_id"
     */
    protected function cleanNestedPath(string $fullPath, string $basePath): string
    {
        // Remove base path prefix if it exists
        $basePathWithDot = $basePath . '.';
        if (str_starts_with($fullPath, $basePathWithDot)) {
            return substr($fullPath, strlen($basePathWithDot));
        }
        
        // Try without the last [] in base path
        $basePathClean = preg_replace('/\[\]$/', '', $basePath);
        $basePathCleanWithDot = $basePathClean . '[].';
        if (str_starts_with($fullPath, $basePathCleanWithDot)) {
            return substr($fullPath, strlen($basePathCleanWithDot));
        }
        
        return $fullPath;
    }

    /**
     * Extract value from raw data using field path
     * Supports nested array paths like "tour_period[].period_id"
     */
    protected function extractValue(array $data, string $fieldPath): mixed
    {
        // Strip common array prefixes since we're already iterating over the base array
        $fieldPath = preg_replace('/^[Pp]eriods\[\]\./', '', $fieldPath);
        $fieldPath = preg_replace('/^[Ss]chedules\[\]\./', '', $fieldPath);
        $fieldPath = preg_replace('/^[Dd]epartures\[\]\./', '', $fieldPath);
        
        // Handle nested array paths like "tour_period[].period_id"
        // Strip the nested array prefix if we're already in that context
        if (preg_match('/^([a-zA-Z_]+)\[\]\.(.+)$/', $fieldPath, $matches)) {
            // We're accessing a nested array, get first item
            $nestedArrayKey = $matches[1];
            $remainingPath = $matches[2];
            
            if (isset($data[$nestedArrayKey]) && is_array($data[$nestedArrayKey]) && !empty($data[$nestedArrayKey])) {
                // If the value is already the direct item (not wrapped in array)
                $firstItem = $data[$nestedArrayKey][0] ?? $data[$nestedArrayKey];
                return $this->extractValue($firstItem, $remainingPath);
            }
            return null;
        }
        
        $parts = explode('.', $fieldPath);
        $value = $data;

        foreach ($parts as $part) {
            // Handle array notation like "rate[]"
            if (str_ends_with($part, '[]')) {
                $key = substr($part, 0, -2);
                if (is_array($value) && isset($value[$key]) && is_array($value[$key]) && !empty($value[$key])) {
                    $value = $value[$key][0]; // Get first item
                } else {
                    return null;
                }
            } elseif (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Apply transformation to value
     */
    protected function applyTransform(mixed $value, ?string $type, ?array $config): mixed
    {
        if (!$type || $type === 'direct') {
            return $value;
        }

        switch ($type) {
            case 'date':
                return date('Y-m-d', strtotime($value));
            case 'datetime':
                return date('Y-m-d H:i:s', strtotime($value));
            case 'number':
                return is_numeric($value) ? (float) $value : 0;
            case 'integer':
                return (int) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'map':
                $mapping = $config['mapping'] ?? [];
                return $mapping[$value] ?? $value;
            default:
                return $value;
        }
    }

    /**
     * Sync a single period
     */
    protected function syncPeriod(Tour $tour, array $data, array &$stats): void
    {
        // Map departure_date to start_date if needed
        if (!empty($data['departure_date']) && empty($data['start_date'])) {
            $data['start_date'] = $data['departure_date'];
        }
        
        // Skip if no start date
        if (empty($data['start_date'])) {
            $stats['skipped']++;
            return;
        }
        
        // Get past period handling settings from integration config (priority) or global settings (fallback)
        $pastPeriodHandling = $this->config?->past_period_handling ?? 'skip'; // skip, close, keep
        $thresholdDays = (int) ($this->config?->past_period_threshold_days ?? 0);
        
        // Ensure threshold days is within valid range
        $thresholdDays = max(0, min(365, $thresholdDays));
        $thresholdDate = now()->subDays($thresholdDays)->toDateString();
        
        // Validate and parse start_date
        $startDate = $data['start_date'];
        try {
            $parsedStartDate = date('Y-m-d', strtotime($startDate));
            if ($parsedStartDate === '1970-01-01' && $startDate !== '1970-01-01') {
                Log::warning('SyncPeriodsJob: Invalid start_date format', [
                    'tour_id' => $tour->id,
                    'start_date' => $startDate,
                ]);
                $stats['skipped']++;
                return;
            }
            $startDate = $parsedStartDate;
            $data['start_date'] = $startDate;
        } catch (\Exception $e) {
            Log::warning('SyncPeriodsJob: Failed to parse start_date', [
                'tour_id' => $tour->id,
                'start_date' => $startDate,
                'error' => $e->getMessage(),
            ]);
            $stats['skipped']++;
            return;
        }
        
        // Check if this is a past period
        $isPastPeriod = $startDate < $thresholdDate;
        
        // Handle past periods based on config
        if ($isPastPeriod) {
            if ($pastPeriodHandling === 'skip') {
                // Skip: ข้าม period ที่เดินทางไปแล้ว ไม่บันทึกข้อมูล
                $stats['skipped']++;
                Log::debug('SyncPeriodsJob: Skipped past period', [
                    'tour_id' => $tour->id,
                    'start_date' => $startDate,
                    'threshold_date' => $thresholdDate,
                    'handling' => 'skip',
                ]);
                return;
            }
            // For 'close' or 'keep': continue processing, will set status later
        }

        // Generate period code if not provided
        $periodCode = $data['period_code'] ?? $data['external_id'] ?? null;
        if (!$periodCode) {
            $periodCode = $tour->tour_code . '-' . date('Ymd', strtotime($startDate));
        }

        // Find or create period
        $period = Period::where('tour_id', $tour->id)
            ->where(function($q) use ($periodCode, $startDate) {
                $q->where('period_code', $periodCode)
                  ->orWhere('start_date', $startDate);
            })
            ->first();

        $periodData = [
            'tour_id' => $tour->id,
            'external_id' => $data['external_id'] ?? null,
            'period_code' => $periodCode,
            'start_date' => $startDate,
            'end_date' => $data['end_date'] ?? $startDate,
            'capacity' => $data['capacity'] ?? 30,
            'booked' => $data['booked'] ?? 0,
            'available' => $data['available'] ?? ($data['capacity'] ?? 30) - ($data['booked'] ?? 0),
            'status' => $this->mapPeriodStatus($data['status'] ?? null),
            'is_visible' => $data['is_visible'] ?? true,
            'sale_status' => $data['sale_status'] ?? 'available',
        ];

        // Override status to 'closed' for past periods if handling = 'close'
        if ($isPastPeriod && $pastPeriodHandling === 'close') {
            $periodData['status'] = 'closed';
            $periodData['sale_status'] = 'closed';
            Log::debug('SyncPeriodsJob: Set past period to closed', [
                'tour_id' => $tour->id,
                'start_date' => $startDate,
                'handling' => 'close',
            ]);
        }

        if ($period) {
            $period->update($periodData);
            $stats['updated']++;
        } else {
            $period = Period::create($periodData);
            $stats['created']++;
        }

        // Sync offer (pricing)
        if (!empty($data['price_adult']) || !empty($data['price'])) {
            $this->syncOffer($period, $data);
        }
    }

    /**
     * Sync offer/pricing for period
     */
    protected function syncOffer(Period $period, array $data): void
    {
        $offerData = [
            'period_id' => $period->id,
            'tour_id' => $period->tour_id,
            'price_adult' => $data['price_adult'] ?? $data['price'] ?? 0,
            'price_child' => $data['price_child'] ?? null,
            'price_child_nobed' => $data['price_child_nobed'] ?? null,
            'price_single' => $data['price_single'] ?? null,
            'discount_adult' => $data['discount_adult'] ?? 0,
            'discount_child_bed' => $data['discount_child_bed'] ?? 0,
            'discount_child_nobed' => $data['discount_child_nobed'] ?? 0,
            'discount_single' => $data['discount_single'] ?? 0,
            'deposit' => $data['deposit'] ?? null,
        ];

        Offer::updateOrCreate(
            ['period_id' => $period->id],
            $offerData
        );
    }

    /**
     * Update tour's aggregated fields after syncing periods
     */
    protected function updateTourAggregates(Tour $tour): void
    {
        $periods = $tour->periods()->with('offer')->get();

        if ($periods->isEmpty()) {
            return;
        }

        $activePeriods = $periods->where('status', 'open');
        
        // Get min/max prices
        $prices = $activePeriods->map(fn($p) => $p->offer?->price_adult)->filter();
        $discountedPrices = $activePeriods->map(function($p) {
            if (!$p->offer) return null;
            $price = $p->offer->price_adult ?? 0;
            $discount = $p->offer->discount_adult ?? 0;
            return $price > 0 ? $price - $discount : null;
        })->filter();

        // Get dates
        $startDates = $activePeriods->pluck('start_date')->filter();

        $tour->update([
            'min_price' => $discountedPrices->min() ?? $prices->min(),
            'max_price' => $prices->max(),
            'display_price' => $discountedPrices->min() ?? $prices->min(),
            'next_departure_date' => $startDates->sort()->first(),
            'total_departures' => $activePeriods->count(),
            'available_seats' => $activePeriods->sum('available'),
            'has_promotion' => $activePeriods->some(fn($p) => ($p->offer?->discount_adult ?? 0) > 0),
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncPeriodsJob: Job failed permanently', [
            'tour_id' => $this->tourId,
            'external_id' => $this->externalId,
            'integration_id' => $this->integrationId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Map status string to Period status enum
     */
    protected function mapPeriodStatus(?string $status): string
    {
        if (!$status) {
            return Period::STATUS_OPEN;
        }

        $statusMap = [
            'open' => Period::STATUS_OPEN,
            'available' => Period::STATUS_OPEN,
            'active' => Period::STATUS_OPEN,
            'closed' => Period::STATUS_CLOSED,
            'inactive' => Period::STATUS_CLOSED,
            'sold_out' => Period::STATUS_SOLD_OUT,
            'full' => Period::STATUS_SOLD_OUT,
            'cancelled' => Period::STATUS_CANCELLED,
            'canceled' => Period::STATUS_CANCELLED,
        ];

        return $statusMap[strtolower($status)] ?? Period::STATUS_OPEN;
    }

    /**
     * Sync itineraries from API response
     * 
     * @param Tour $tour
     * @param array $rawData Raw periods data from API
     * @param string|null $itinerariesPath Path to itinerary data (e.g., "periods[].tour_daily[].day_list[]")
     * @param WholesalerApiConfig $config
     * @return array Stats
     */
    protected function syncItineraries(Tour $tour, array $rawData, ?string $itinerariesPath, WholesalerApiConfig $config): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        
        if (empty($rawData) || !$itinerariesPath) {
            return $stats;
        }

        // Get itinerary mappings (mappings are per wholesaler_id)
        $mappings = WholesalerFieldMapping::where('wholesaler_id', $this->config->wholesaler_id)
            ->where('section_name', 'itinerary')
            ->where('is_active', true)
            ->get();

        if ($mappings->isEmpty()) {
            Log::debug('SyncPeriodsJob: No itinerary mappings configured', [
                'tour_id' => $this->tourId,
            ]);
            return $stats;
        }

        // Flatten itinerary data from nested path
        // For GO365: periods[].tour_daily[].day_list[] -> flatten tour_daily from each period
        $itineraryItems = $this->flattenItineraryPath($rawData, $itinerariesPath);
        
        if (empty($itineraryItems)) {
            Log::debug('SyncPeriodsJob: No itinerary items found', [
                'tour_id' => $this->tourId,
                'path' => $itinerariesPath,
            ]);
            return $stats;
        }

        Log::info('SyncPeriodsJob: Syncing itineraries', [
            'tour_id' => $this->tourId,
            'items_count' => count($itineraryItems),
        ]);

        // Delete existing itineraries for this tour (from API source)
        TourItinerary::where('tour_id', $tour->id)
            ->where('data_source', 'api')
            ->delete();

        // Process each itinerary item
        $dayNumber = 1;
        foreach ($itineraryItems as $item) {
            try {
                $itinData = $this->transformItineraryData($item, $mappings, $itinerariesPath);
                
                // Set day number if not in data
                if (empty($itinData['day_number'])) {
                    $itinData['day_number'] = $dayNumber;
                }
                
                TourItinerary::create([
                    'tour_id' => $tour->id,
                    'data_source' => 'api',
                    'day_number' => $itinData['day_number'] ?? $dayNumber,
                    'title' => mb_substr($itinData['title'] ?? "Day {$dayNumber}", 0, 250),
                    'description' => is_array($itinData['description'] ?? null) 
                        ? json_encode($itinData['description'], JSON_UNESCAPED_UNICODE) 
                        : ($itinData['description'] ?? null),
                    'places' => is_array($itinData['places'] ?? null) 
                        ? json_encode($itinData['places'], JSON_UNESCAPED_UNICODE) 
                        : ($itinData['places'] ?? null),
                    'has_breakfast' => $this->parseMealFlag($itinData['has_breakfast'] ?? null, 'breakfast'),
                    'has_lunch' => $this->parseMealFlag($itinData['has_lunch'] ?? null, 'lunch'),
                    'has_dinner' => $this->parseMealFlag($itinData['has_dinner'] ?? null, 'dinner'),
                    'meals_note' => $itinData['meals_note'] ?? null,
                    'accommodation' => mb_substr($itinData['accommodation'] ?? '', 0, 250) ?: null,
                    'hotel_star' => $itinData['hotel_star'] ?? null,
                    'images' => is_array($itinData['images'] ?? null) 
                        ? json_encode($itinData['images'], JSON_UNESCAPED_UNICODE) 
                        : ($itinData['images'] ?? null),
                    'sort_order' => $dayNumber,
                ]);
                
                $stats['created']++;
                $dayNumber++;
            } catch (\Exception $e) {
                Log::error('SyncPeriodsJob: Error creating itinerary', [
                    'tour_id' => $this->tourId,
                    'day_number' => $dayNumber,
                    'error' => $e->getMessage(),
                ]);
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * Flatten itinerary path from nested structure
     * e.g., "tour_daily[]" -> get all tour_daily items
     * e.g., "periods[].tour_daily[].day_list[]" -> get all day_list items
     */
    protected function flattenItineraryPath(array $data, string $path): array
    {
        // Remove "periods[]." prefix if present (for backward compatibility)
        $path = preg_replace('/^[Pp]eriods\[\]\./', '', $path);
        
        // Split path by "[]." : "tour_daily[].day_list[]" -> ["tour_daily", "day_list"]
        $segments = preg_split('/\[\]\.?/', $path);
        $segments = array_filter($segments, fn($s) => !empty($s));
        
        if (empty($segments)) {
            return is_array($data) ? $data : [];
        }
        
        // Start with data wrapped in array if it's a single object
        $result = [$data];
        
        foreach ($segments as $segment) {
            $newResult = [];
            foreach ($result as $item) {
                if (isset($item[$segment]) && is_array($item[$segment])) {
                    // Check if it's an array of items or a single item
                    if (isset($item[$segment][0]) && is_array($item[$segment][0])) {
                        // Array of items - add each
                        foreach ($item[$segment] as $nested) {
                            if (is_array($nested)) {
                                $newResult[] = $nested;
                            }
                        }
                    } elseif (!isset($item[$segment][0])) {
                        // Associative array / single item - add as-is
                        $newResult[] = $item[$segment];
                    } else {
                        // Indexed array of scalar values - add as array
                        foreach ($item[$segment] as $nested) {
                            if (is_array($nested)) {
                                $newResult[] = $nested;
                            }
                        }
                    }
                }
            }
            $result = $newResult;
        }
        
        return $result;
    }

    /**
     * Transform raw itinerary data using field mappings
     */
    protected function transformItineraryData(array $rawItem, $mappings, ?string $basePath = null): array
    {
        $result = [];

        foreach ($mappings as $mapping) {
            $fieldPath = $mapping->their_field_path ?? $mapping->their_field ?? null;
            if (empty($fieldPath)) {
                continue;
            }
            
            // Clean path - remove base path prefix
            if ($basePath) {
                $fieldPath = $this->cleanNestedPath($fieldPath, $basePath);
            }
            
            $value = $this->extractValue($rawItem, $fieldPath);
            
            if ($value !== null) {
                $value = $this->applyTransform($value, $mapping->transform_type, $mapping->transform_config);
            }
            
            if ($value === null && !empty($mapping->default_value)) {
                $value = $mapping->default_value;
            }

            $result[$mapping->our_field] = $value;
        }

        return $result;
    }

    /**
     * Parse meal flag from various formats
     */
    protected function parseMealFlag($value, string $mealType): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (bool) $value;
        }
        
        if (is_string($value)) {
            // Check if string contains meal keywords
            $keywords = [
                'breakfast' => ['breakfast', 'เช้า', 'อาหารเช้า', 'B'],
                'lunch' => ['lunch', 'กลางวัน', 'อาหารกลางวัน', 'L'],
                'dinner' => ['dinner', 'เย็น', 'อาหารเย็น', 'D'],
            ];
            
            if (isset($keywords[$mealType])) {
                foreach ($keywords[$mealType] as $keyword) {
                    if (stripos($value, $keyword) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Sync transport from API response
     * 
     * @param Tour $tour
     * @param array $rawData Raw periods data from API
     * @return array Stats
     */
    protected function syncTransport(Tour $tour, array $rawData): array
    {
        $stats = ['updated' => false, 'airline' => null];
        
        if (empty($rawData)) {
            return $stats;
        }

        // Look for tour_airline in the first period (tour-level data)
        $firstPeriod = $rawData[0] ?? [];
        $airlineData = $firstPeriod['tour_airline'] ?? null;

        if (empty($airlineData)) {
            // Try to get from period_airline in tour_period
            $tourPeriods = $firstPeriod['tour_period'] ?? [];
            if (!empty($tourPeriods)) {
                $airlineData = $tourPeriods[0]['period_airline'] ?? null;
            }
        }

        if (empty($airlineData)) {
            return $stats;
        }

        // Get airline code (IATA)
        $airlineCode = $airlineData['airline_iata'] ?? null;
        $airlineName = $airlineData['airline_name'] ?? null;

        if (!$airlineCode && !$airlineName) {
            return $stats;
        }

        // Look up transport in database
        $transport = null;
        
        if ($airlineCode) {
            $transport = Transport::where('code', $airlineCode)->first();
        }
        
        if (!$transport && $airlineName) {
            $transport = Transport::where('name_en', 'LIKE', "%{$airlineName}%")
                ->orWhere('name_th', 'LIKE', "%{$airlineName}%")
                ->first();
        }

        if ($transport && $tour->transport_id !== $transport->id) {
            $tour->update(['transport_id' => $transport->id]);
            
            // Also sync to tour_transports table (used by UI)
            $this->syncTourTransport($tour, $transport->id);
            
            $stats['updated'] = true;
            $stats['airline'] = $airlineCode ?? $airlineName;
            
            Log::info('SyncPeriodsJob: Updated transport', [
                'tour_id' => $this->tourId,
                'transport_id' => $transport->id,
                'airline' => $stats['airline'],
            ]);
        } elseif ($transport) {
            // Make sure tour_transports table is also synced even if transport_id unchanged
            $this->syncTourTransport($tour, $transport->id);
        }

        return $stats;
    }

    /**
     * Sync transport to tour_transports pivot table
     */
    protected function syncTourTransport(Tour $tour, int $transportId): void
    {
        // Get transport info
        $transport = DB::table('transports')->where('id', $transportId)->first();
        
        // Check if already exists
        $exists = DB::table('tour_transports')
            ->where('tour_id', $tour->id)
            ->where('transport_id', $transportId)
            ->exists();
        
        if (!$exists) {
            DB::table('tour_transports')->insert([
                'tour_id' => $tour->id,
                'transport_id' => $transportId,
                'transport_code' => $transport->code ?? null,
                'transport_name' => $transport->name ?? null,
                'transport_type' => 'outbound',
                'sort_order' => 0,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Sync cities from API response
     * 
     * @param Tour $tour
     * @param array $rawData Full API response data
     * @param string|null $citiesPath Path to city data (e.g., "tour_city[]")
     * @param WholesalerApiConfig $config
     * @return array Stats
     */
    protected function syncCities(Tour $tour, array $rawData, ?string $citiesPath, WholesalerApiConfig $config): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        
        if (empty($rawData) || !$citiesPath) {
            return $stats;
        }

        // Get city mappings (mappings are per wholesaler_id)
        $mappings = WholesalerFieldMapping::where('wholesaler_id', $this->config->wholesaler_id)
            ->where('section_name', 'city')
            ->where('is_active', true)
            ->get();

        if ($mappings->isEmpty()) {
            Log::debug('SyncPeriodsJob: No city mappings configured', [
                'tour_id' => $this->tourId,
            ]);
            return $stats;
        }

        // Flatten city data from path
        $cityItems = $this->flattenItineraryPath($rawData, $citiesPath);
        
        if (empty($cityItems)) {
            Log::debug('SyncPeriodsJob: No city items found', [
                'tour_id' => $this->tourId,
                'path' => $citiesPath,
            ]);
            return $stats;
        }

        Log::info('SyncPeriodsJob: Syncing cities', [
            'tour_id' => $this->tourId,
            'items_count' => count($cityItems),
        ]);

        // Delete existing tour_cities for this tour (from API source)
        DB::table('tour_cities')
            ->where('tour_id', $tour->id)
            ->delete();

        // Process each city item
        $sortOrder = 1;
        foreach ($cityItems as $item) {
            try {
                $cityData = $this->transformCityData($item, $mappings, $citiesPath);
                
                // Try to find city in our cities table by name
                $cityId = $this->findCityId($cityData);
                
                // Skip if city not found in our database
                if (!$cityId) {
                    Log::debug('SyncPeriodsJob: City not found in database', [
                        'tour_id' => $this->tourId,
                        'city_data' => $cityData,
                    ]);
                    $stats['skipped']++;
                    continue;
                }
                
                // Get country_id from the city record (not from API)
                $city = DB::table('cities')->where('id', $cityId)->first();
                $countryId = $city->country_id ?? $tour->primary_country_id;
                
                DB::table('tour_cities')->insert([
                    'tour_id' => $tour->id,
                    'city_id' => $cityId,
                    'country_id' => $countryId,
                    'sort_order' => $sortOrder,
                    'days_in_city' => 1,
                    'created_at' => now(),
                ]);
                
                $stats['created']++;
                $sortOrder++;
            } catch (\Exception $e) {
                Log::error('SyncPeriodsJob: Error syncing city', [
                    'tour_id' => $this->tourId,
                    'error' => $e->getMessage(),
                ]);
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * Transform raw city data using field mappings
     */
    protected function transformCityData(array $rawItem, $mappings, ?string $basePath = null): array
    {
        $result = [];

        foreach ($mappings as $mapping) {
            $fieldPath = $mapping->their_field_path ?? $mapping->their_field ?? null;
            if (empty($fieldPath)) {
                continue;
            }
            
            // Clean path - remove base path prefix
            if ($basePath) {
                $fieldPath = $this->cleanNestedPath($fieldPath, $basePath);
            }
            
            $value = $this->extractValue($rawItem, $fieldPath);
            
            if ($value !== null) {
                $value = $this->applyTransform($value, $mapping->transform_type, $mapping->transform_config);
            }
            
            if ($value === null && !empty($mapping->default_value)) {
                $value = $mapping->default_value;
            }

            $result[$mapping->our_field] = $value;
        }

        return $result;
    }

    /**
     * Find city ID in cities table by name
     */
    protected function findCityId(array $cityData): ?int
    {
        $nameTh = $cityData['name_th'] ?? null;
        $nameEn = $cityData['name_en'] ?? null;
        
        if (!$nameTh && !$nameEn) {
            return null;
        }

        $query = DB::table('cities');
        
        if ($nameTh) {
            $query->where('name_th', 'LIKE', '%' . $nameTh . '%');
        }
        
        if ($nameEn && !$nameTh) {
            $query->where('name_en', 'LIKE', '%' . $nameEn . '%');
        }
        
        $city = $query->first();
        
        return $city?->id;
    }
}
