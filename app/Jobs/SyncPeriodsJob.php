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
    protected int $wholesalerId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $tourId, string $externalId, int $wholesalerId)
    {
        $this->tourId = $tourId;
        $this->externalId = $externalId;
        $this->wholesalerId = $wholesalerId;
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
            'wholesaler_id' => $this->wholesalerId,
        ]);

        $tour = Tour::find($this->tourId);
        if (!$tour) {
            Log::warning('SyncPeriodsJob: Tour not found', ['tour_id' => $this->tourId]);
            return;
        }

        $config = WholesalerApiConfig::where('wholesaler_id', $this->wholesalerId)->first();
        if (!$config) {
            Log::warning('SyncPeriodsJob: Config not found', ['wholesaler_id' => $this->wholesalerId]);
            return;
        }

        try {
            // Get periods endpoint from auth_credentials
            $credentials = $config->auth_credentials ?? [];
            $endpoints = $credentials['endpoints'] ?? [];
            $periodsEndpoint = $endpoints['periods'] ?? null;

            if (!$periodsEndpoint) {
                Log::warning('SyncPeriodsJob: No periods endpoint configured', [
                    'wholesaler_id' => $this->wholesalerId,
                ]);
                return;
            }

            // Build URL - replace placeholders
            $url = str_replace(
                ['{external_id}', '{tour_code}', '{wholesaler_tour_code}'],
                [$this->externalId, $tour->tour_code, $tour->wholesaler_tour_code ?? ''],
                $periodsEndpoint
            );

            // Fetch periods from API
            $adapter = AdapterFactory::create($this->wholesalerId);
            $result = $adapter->fetchPeriods($url);

            if (!$result->success) {
                Log::error('SyncPeriodsJob: Failed to fetch periods', [
                    'tour_id' => $this->tourId,
                    'error' => $result->errorMessage,
                ]);
                return;
            }

            // Get aggregation config for nested data structure
            $aggConfig = $config->aggregation_config ?? [];
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

            // Get field mappings for periods
            $mappings = WholesalerFieldMapping::where('wholesaler_id', $this->wholesalerId)
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
            $rawData = $result->periods ?? [];
            $itineraryStats = $this->syncItineraries($tour, $rawData, $itinerariesPath, $config);

            // Sync Transport (from tour_airline in API response)
            $transportStats = $this->syncTransport($tour, $rawData);

            // Update tour's aggregated fields
            $this->updateTourAggregates($tour);

            Log::info('SyncPeriodsJob: Completed', [
                'tour_id' => $this->tourId,
                'stats' => $stats,
                'itineraries' => $itineraryStats,
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
                $notificationService->notifyIntegration($config->id, 'sync_error', [
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
        
        // Get sync settings and check for past periods
        $syncSettings = Setting::get('sync_settings', [
            'skip_past_periods' => true,
            'past_period_threshold_days' => 0,
        ]);
        
        $skipPastPeriods = $syncSettings['skip_past_periods'] ?? true;
        $thresholdDays = $syncSettings['past_period_threshold_days'] ?? 0;
        $thresholdDate = now()->subDays($thresholdDays)->toDateString();
        
        // Skip past periods if enabled
        if ($skipPastPeriods && $data['start_date'] < $thresholdDate) {
            $stats['skipped']++;
            Log::debug('SyncPeriodsJob: Skipped past period', [
                'tour_id' => $tour->id,
                'start_date' => $data['start_date'],
                'threshold_date' => $thresholdDate,
            ]);
            return;
        }

        // Generate period code if not provided
        $periodCode = $data['period_code'] ?? $data['external_id'] ?? null;
        if (!$periodCode) {
            $periodCode = $tour->tour_code . '-' . date('Ymd', strtotime($data['start_date']));
        }

        // Find or create period
        $period = Period::where('tour_id', $tour->id)
            ->where(function($q) use ($periodCode, $data) {
                $q->where('period_code', $periodCode)
                  ->orWhere('start_date', $data['start_date']);
            })
            ->first();

        $periodData = [
            'tour_id' => $tour->id,
            'external_id' => $data['external_id'] ?? null,
            'period_code' => $periodCode,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? $data['start_date'],
            'capacity' => $data['capacity'] ?? 30,
            'booked' => $data['booked'] ?? 0,
            'available' => $data['available'] ?? ($data['capacity'] ?? 30) - ($data['booked'] ?? 0),
            'status' => $this->mapPeriodStatus($data['status'] ?? null),
            'is_visible' => $data['is_visible'] ?? true,
            'sale_status' => $data['sale_status'] ?? 'available',
        ];

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
            'wholesaler_id' => $this->wholesalerId,
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

        // Get itinerary mappings
        $mappings = WholesalerFieldMapping::where('wholesaler_id', $this->wholesalerId)
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
                    'title' => $itinData['title'] ?? "Day {$dayNumber}",
                    'description' => $itinData['description'] ?? null,
                    'places' => $itinData['places'] ?? null,
                    'has_breakfast' => $this->parseMealFlag($itinData['has_breakfast'] ?? null, 'breakfast'),
                    'has_lunch' => $this->parseMealFlag($itinData['has_lunch'] ?? null, 'lunch'),
                    'has_dinner' => $this->parseMealFlag($itinData['has_dinner'] ?? null, 'dinner'),
                    'meals_note' => $itinData['meals_note'] ?? null,
                    'accommodation' => $itinData['accommodation'] ?? null,
                    'hotel_star' => $itinData['hotel_star'] ?? null,
                    'images' => $itinData['images'] ?? null,
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
     * e.g., "periods[].tour_daily[].day_list[]" -> get all day_list items
     */
    protected function flattenItineraryPath(array $data, string $path): array
    {
        // Remove "periods[]." prefix since we're already at periods level
        $path = preg_replace('/^[Pp]eriods\[\]\./', '', $path);
        
        // Split remaining path: "tour_daily[].day_list[]"
        $segments = preg_split('/\[\]\.?/', $path);
        $segments = array_filter($segments, fn($s) => !empty($s));
        
        // Start with data
        $result = $data;
        
        foreach ($segments as $segment) {
            $newResult = [];
            foreach ($result as $item) {
                if (isset($item[$segment]) && is_array($item[$segment])) {
                    foreach ($item[$segment] as $nested) {
                        if (is_array($nested)) {
                            $newResult[] = $nested;
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
            $stats['updated'] = true;
            $stats['airline'] = $airlineCode ?? $airlineName;
            
            Log::info('SyncPeriodsJob: Updated transport', [
                'tour_id' => $this->tourId,
                'transport_id' => $transport->id,
                'airline' => $stats['airline'],
            ]);
        }

        return $stats;
    }
}
