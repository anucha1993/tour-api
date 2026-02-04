<?php

namespace App\Jobs;

use App\Jobs\SyncPeriodsJob;
use App\Models\Offer;
use App\Models\Period;
use App\Models\SyncCursor;
use App\Models\SyncErrorLog;
use App\Models\SyncLog;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use App\Services\CityExtractorService;
use App\Services\CloudflareImagesService;
use App\Services\NotificationService;
use App\Services\PdfBrandingService;
use App\Services\WholesalerAdapters\AdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SyncToursJob - Sync tours from Wholesaler API
 * 
 * Supports two modes:
 * 1. Manual Sync: Frontend sends transformed_data → insert directly
 * 2. Auto Sync: Fetch from API → Map → Insert
 */
class SyncToursJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    protected int $wholesalerId;
    protected ?array $transformedData;
    protected string $syncType;
    protected ?int $limit;
    protected ?int $syncLogId = null;

    /**
     * Create a new job instance.
     * 
     * @param int $wholesalerId Wholesaler ID
     * @param array|null $transformedData Pre-transformed data from frontend (optional)
     * @param string $syncType 'manual', 'incremental', or 'full'
     * @param int|null $limit Maximum number of records to sync (null = unlimited)
     */
    public function __construct(
        int $wholesalerId,
        ?array $transformedData = null,
        string $syncType = 'manual',
        ?int $limit = null
    ) {
        $this->wholesalerId = $wholesalerId;
        $this->transformedData = $transformedData;
        $this->syncType = $syncType;
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SyncToursJob: Starting', [
            'wholesaler_id' => $this->wholesalerId,
            'sync_type' => $this->syncType,
            'has_transformed_data' => !empty($this->transformedData),
        ]);

        $config = WholesalerApiConfig::where('wholesaler_id', $this->wholesalerId)->first();
        
        if (!$config) {
            Log::error('SyncToursJob: Config not found', ['wholesaler_id' => $this->wholesalerId]);
            return;
        }

        // Create sync log
        $syncLog = $this->createSyncLog();
        
        try {
            // Get data to sync
            if ($this->transformedData) {
                // Mode 1: Manual - use pre-transformed data
                $toursData = $this->transformedData;
                Log::info('SyncToursJob: Using transformed data', ['count' => count($toursData)]);
            } else {
                // Mode 2: Auto - fetch and map
                Log::info('SyncToursJob: Fetching from API', ['base_url' => $config->api_base_url]);
                $toursData = $this->fetchAndMapTours($config);
                Log::info('SyncToursJob: Fetched tours', ['count' => count($toursData)]);
            }

            if (empty($toursData)) {
                Log::info('SyncToursJob: No tours to sync');
                $syncLog->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'duration_seconds' => now()->diffInSeconds($syncLog->started_at),
                ]);
                return;
            }

            // Apply limit: use parameter first, fallback to config setting
            $limit = $this->limit ?? $config->sync_limit;
            if ($limit !== null && $limit > 0) {
                $originalCount = count($toursData);
                $toursData = array_slice($toursData, 0, $limit);
                Log::info('SyncToursJob: Limited records', [
                    'wholesaler_id' => $this->wholesalerId,
                    'limit' => $limit,
                    'original_count' => $originalCount,
                    'limited_count' => count($toursData),
                ]);
            }

            // Process each tour
            $stats = $this->processTours($toursData, $config, $syncLog);

            // Update sync log
            $syncLog->update([
                'status' => $stats['errors'] > 0 ? 'partial' : 'completed',
                'completed_at' => now(),
                'duration_seconds' => now()->diffInSeconds($syncLog->started_at),
                'tours_received' => $stats['received'],
                'tours_created' => $stats['created'],
                'tours_updated' => $stats['updated'],
                'tours_skipped' => $stats['skipped'],
                'tours_failed' => $stats['errors'],
                'periods_received' => $stats['periods_received'],
                'periods_created' => $stats['periods_created'],
                'periods_updated' => $stats['periods_updated'],
                'error_count' => $stats['errors'],
            ]);

            Log::info('SyncToursJob: Completed', [
                'wholesaler_id' => $this->wholesalerId,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('SyncToursJob: Failed', [
                'wholesaler_id' => $this->wholesalerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $syncLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'duration_seconds' => now()->diffInSeconds($syncLog->started_at),
                'error_summary' => ['message' => $e->getMessage()],
            ]);

            // Send notification
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->notifyIntegration($config->id, 'sync_error', [
                    'error' => $e->getMessage(),
                    'sync_type' => $this->syncType,
                ]);
            } catch (\Exception $notifyError) {
                Log::warning('SyncToursJob: Failed to send notification', [
                    'error' => $notifyError->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Create sync log entry
     */
    protected function createSyncLog(): SyncLog
    {
        return SyncLog::create([
            'wholesaler_id' => $this->wholesalerId,
            'sync_type' => $this->syncType,
            'sync_id' => 'sync_' . date('Ymd_His') . '_' . uniqid(),
            'started_at' => now(),
            'status' => 'running',
        ]);
    }

    /**
     * Fetch tours from API and map using stored mappings
     */
    protected function fetchAndMapTours(WholesalerApiConfig $config): array
    {
        $adapter = AdapterFactory::create($this->wholesalerId);

        // Get cursor for incremental sync
        $cursor = SyncCursor::where('wholesaler_id', $this->wholesalerId)->first();
        $cursorValue = $this->syncType === 'full' ? null : $cursor?->cursor_value;

        // Fetch tours
        $result = $adapter->fetchTours($cursorValue);

        if (!$result->success) {
            throw new \Exception('Failed to fetch tours: ' . $result->errorMessage);
        }

        // Get mappings from database (using correct column names)
        $mappings = WholesalerFieldMapping::where('wholesaler_id', $this->wholesalerId)
            ->where('is_active', true)
            ->get()
            ->groupBy('section_name');

        // Parse aggregation_config for nested data structure paths
        // aggregation_config is already an array (cast by model), not a JSON string
        $dataStructure = $config->aggregation_config ?? [];
        
        // Map each tour using our transform logic
        $mappedTours = [];
        foreach ($result->tours as $rawTour) {
            $transformed = $this->transformTourData($rawTour, $mappings, $dataStructure);
            
            // Only include if has required fields
            $tourSection = $transformed['tour'] ?? [];
            if (!empty($tourSection['title']) || !empty($tourSection['tour_code']) || !empty($tourSection['wholesaler_tour_code'])) {
                $mappedTours[] = $transformed;
            }
        }

        Log::info('SyncToursJob: Mapped tours', [
            'raw_count' => count($result->tours),
            'mapped_count' => count($mappedTours),
        ]);

        // Update cursor
        if ($cursor && $result->nextCursor) {
            $cursor->update([
                'cursor_value' => $result->nextCursor,
                'last_synced_at' => now(),
                'last_batch_count' => count($result->tours),
                'total_received' => $cursor->total_received + count($result->tours),
            ]);
        }

        return $mappedTours;
    }

    /**
     * Transform raw tour data using mappings
     * Uses WholesalerFieldMapping columns: their_field, their_field_path, transform_type, transform_config
     * 
     * @param array $rawTour Raw tour data from API
     * @param mixed $mappings Field mappings grouped by section
     * @param array $dataStructure Optional nested path config from aggregation_config
     */
    protected function transformTourData(array $rawTour, $mappings, array $dataStructure = []): array
    {
        $result = [
            'tour' => [],
            'departure' => [],
            'itinerary' => [],
            'content' => [],
            'media' => [],
        ];

        // Helper to extract value from nested path
        $extractValue = function($data, $path) use (&$extractValue) {
            if (empty($path)) return null;
            
            // Handle fallback paths with | separator (e.g., "countries[].code|countries[].name")
            if (strpos($path, '|') !== false) {
                $paths = explode('|', $path);
                foreach ($paths as $singlePath) {
                    $value = $extractValue($data, trim($singlePath));
                    if ($value !== null && $value !== '') {
                        return $value;
                    }
                }
                return null;
            }
            
            // Handle array notation like "Periods[]" or "countries[].code"
            if (strpos($path, '[]') !== false) {
                $parts = explode('[].', $path);
                $arrayKey = $parts[0];
                $fieldPath = $parts[1] ?? null;
                
                if (!isset($data[$arrayKey]) || !is_array($data[$arrayKey])) return null;
                if (empty($data[$arrayKey])) return null;
                
                // Get first element from array
                $firstItem = $data[$arrayKey][0] ?? null;
                if (!$firstItem) return null;
                
                if ($fieldPath) {
                    // Recursively get nested field from first item
                    return $extractValue($firstItem, $fieldPath);
                }
                return $firstItem;
            }
            
            // Normal dot notation path
            $keys = explode('.', $path);
            $value = $data;
            
            foreach ($keys as $key) {
                if (!is_array($value) || !isset($value[$key])) return null;
                $value = $value[$key];
            }
            
            return $value;
        };

        // Helper to apply transforms
        $applyTransform = function($value, $mapping, $rawData) {
            if (empty($mapping->transform_type) || $mapping->transform_type === 'direct') {
                return $value;
            }
            
            $config = $mapping->transform_config ?? [];
            
            switch ($mapping->transform_type) {
                case 'lookup':
                    // Lookup by field in related table
                    if ($value === null || $value === '') return null;
                    
                    $lookupTable = $config['lookup_table'] ?? null;
                    $lookupBy = $config['lookup_by'] ?? 'id';
                    
                    // Auto-infer lookup_table from our_field if not specified
                    // e.g., transport_id → transports, primary_country_id → countries
                    if (!$lookupTable) {
                        $ourField = $mapping->our_field;
                        if (str_ends_with($ourField, '_id')) {
                            // Remove _id suffix and pluralize
                            $baseName = substr($ourField, 0, -3);
                            // Handle special cases like primary_country_id → countries
                            if (str_contains($baseName, '_')) {
                                $parts = explode('_', $baseName);
                                $baseName = end($parts); // Get last part: country
                            }
                            $lookupTable = str($baseName)->plural()->toString();
                        }
                    }
                    
                    if (!$lookupTable) {
                        Log::warning('SyncToursJob: lookup transform cannot determine lookup_table', [
                            'our_field' => $mapping->our_field,
                            'value' => $value,
                        ]);
                        return $value;
                    }
                    
                    // Build model class from table name
                    $modelClass = 'App\\Models\\' . str($lookupTable)->singular()->studly()->toString();
                    
                    if (!class_exists($modelClass)) {
                        Log::warning('SyncToursJob: lookup model not found', [
                            'lookup_table' => $lookupTable,
                            'model_class' => $modelClass,
                        ]);
                        return $value;
                    }
                    
                    // Try exact match first
                    $record = $modelClass::where($lookupBy, $value)->first();
                    
                    // If not found, try fuzzy matching for transport and country
                    if (!$record && in_array($lookupTable, ['transports', 'countries'])) {
                        $searchValue = trim((string) $value);
                        
                        // For transports - first try to extract code from parentheses like "CHINA SOUTHERN AIRLINE (CZ)"
                        if ($lookupTable === 'transports' && preg_match('/\(([A-Z0-9]{2,3})\)/', $searchValue, $matches)) {
                            $code = $matches[1];
                            $record = $modelClass::where('code', $code)
                                ->orWhere('code1', $code)
                                ->first();
                        }
                        
                        // If still not found, try LIKE match on name (without parentheses part)
                        if (!$record) {
                            // Remove parentheses and content for cleaner LIKE match
                            $cleanName = preg_replace('/\s*\([^)]+\)\s*/', '', $searchValue);
                            $cleanName = trim($cleanName);
                            
                            if (!empty($cleanName)) {
                                // Use correct column name for each table
                                if ($lookupTable === 'countries') {
                                    $record = $modelClass::where('name_en', 'LIKE', '%' . $cleanName . '%')
                                        ->orWhere('name_th', 'LIKE', '%' . $cleanName . '%')
                                        ->first();
                                } else {
                                    $record = $modelClass::where('name', 'LIKE', '%' . $cleanName . '%')->first();
                                }
                            }
                        }
                        
                        // For countries, also try ISO codes
                        if (!$record && $lookupTable === 'countries') {
                            $record = $modelClass::where('iso2', strtoupper($searchValue))
                                ->orWhere('iso3', strtoupper($searchValue))
                                ->orWhere('name_en', 'LIKE', '%' . $searchValue . '%')
                                ->orWhere('name_th', 'LIKE', '%' . $searchValue . '%')
                                ->first();
                        }
                    }
                    
                    return $record?->id;
                    
                case 'concat':
                    $stringTransform = $config['string_transform'] ?? [];
                    if (isset($stringTransform['template'])) {
                        $template = $stringTransform['template'];
                        return preg_replace_callback('/\{(\w+)\}/', function($matches) use ($rawData) {
                            return $rawData[$matches[1]] ?? '';
                        }, $template);
                    }
                    return $value;
                    
                case 'value_map':
                    // Support both formats:
                    // 1. {"map": {"Y": true, "N": false}}
                    // 2. {"value_map": [{"from": "Y", "to": "true"}, {"from": "__EMPTY__", "to": "false"}]}
                    $map = $config['map'] ?? null;
                    
                    if ($map === null && isset($config['value_map'])) {
                        // Convert array format to map
                        $map = [];
                        foreach ($config['value_map'] as $item) {
                            $fromVal = $item['from'] ?? null;
                            if ($fromVal === '__EMPTY__') {
                                $fromVal = '';
                            }
                            if ($fromVal !== null) {
                                $map[$fromVal] = $item['to'] ?? null;
                            }
                        }
                    }
                    
                    if ($map === null) return $value;
                    
                    // Handle empty string lookup
                    $lookupKey = ($value === '' || $value === null) ? '' : $value;
                    
                    // Check if value exists in map, if not use default_value or fallback to 0/null
                    $defaultValue = $config['default'] ?? null;
                    $mappedValue = $map[$lookupKey] ?? $defaultValue;
                    
                    // If still no mapped value after default, return null for unknown values
                    // This prevents invalid values from being inserted into numeric fields
                    if ($mappedValue === null && !array_key_exists($lookupKey, $map)) {
                        return null;
                    }
                    
                    // Convert string "true"/"false" to boolean for tinyint fields
                    if ($mappedValue === 'true') return 1;
                    if ($mappedValue === 'false') return 0;
                    
                    return $mappedValue;
                    
                case 'split':
                    // Split string into array
                    if (!$value || !is_string($value)) return [];
                    // Get delimiter from string_transform.splitBy or delimiter or default to space
                    $delimiter = $config['string_transform']['splitBy'] 
                        ?? $config['delimiter'] 
                        ?? ' ';
                    // If delimiter is null or empty, default to space
                    if (empty($delimiter)) $delimiter = ' ';
                    // Split and trim each item
                    $items = array_map('trim', explode($delimiter, $value));
                    // Remove empty items
                    return array_values(array_filter($items, fn($item) => $item !== ''));
                    
                case 'date_format':
                    if ($value) {
                        try {
                            $format = $config['output_format'] ?? 'Y-m-d';
                            return date($format, strtotime($value));
                        } catch (\Exception $e) {
                            return $value;
                        }
                    }
                    return $value;
                    
                default:
                    return $value;
            }
        };

        // Map single-value sections (tour, content, media, seo)
        foreach (['tour', 'content', 'media', 'seo'] as $section) {
            if (!isset($mappings[$section])) continue;
            
            foreach ($mappings[$section] as $mapping) {
                $fieldName = $mapping->our_field;
                $path = $mapping->their_field_path ?? $mapping->their_field;

                $value = $extractValue($rawTour, $path);
                
                // Debug log for key fields
                if (in_array($fieldName, ['primary_country_id', 'transport_id'])) {
                    Log::info('SyncToursJob: Transform field', [
                        'field' => $fieldName,
                        'path' => $path,
                        'raw_value' => $value,
                        'transform_type' => $mapping->transform_type,
                    ]);
                }
                
                $value = $applyTransform($value, $mapping, $rawTour);
                
                // Debug log after transform
                if (in_array($fieldName, ['primary_country_id', 'transport_id'])) {
                    Log::info('SyncToursJob: After transform', [
                        'field' => $fieldName,
                        'transformed_value' => $value,
                    ]);
                }
                
                if ($value === null && !empty($mapping->default_value)) {
                    $value = $mapping->default_value;
                }
                
                $result[$section][$fieldName] = $value;
            }
        }

        // Map departures - support nested paths from aggregation_config
        // Check if custom departures_path is defined in dataStructure
        $departuresPath = $dataStructure['data_structure']['departures']['path'] ?? null;
        
        if ($departuresPath) {
            // Use custom nested path (e.g., "periods[].tour_period[]" for GO365)
            $departureItems = $this->flattenNestedPath($rawTour, $departuresPath);
        } else {
            // Default: use standard periods/schedules/departures array
            $departureItems = $rawTour['Periods'] ?? $rawTour['periods'] ?? $rawTour['Schedules'] ?? $rawTour['schedules'] ?? $rawTour['Departures'] ?? $rawTour['departures'] ?? [];
        }
        
        if (isset($mappings['departure']) && !empty($departureItems)) {
            foreach ($departureItems as $departureItem) {
                $dep = [];
                foreach ($mappings['departure'] as $mapping) {
                    $fieldName = $mapping->our_field;
                    $path = $mapping->their_field_path ?? $mapping->their_field ?? '';
                    
                    // Skip if no path defined
                    if (empty($path)) {
                        continue;
                    }
                    
                    // Remove all known array prefixes to get the final field key
                    $cleanPath = $this->cleanNestedPath($path, $departuresPath);
                    
                    // Extract value - support nested fields within the item
                    $value = $this->extractNestedValue($departureItem, $cleanPath);
                    $value = $applyTransform($value, $mapping, $departureItem);
                    
                    if ($value === null && !empty($mapping->default_value)) {
                        $value = $mapping->default_value;
                    }
                    
                    $dep[$fieldName] = $value;
                }
                $result['departure'][] = $dep;
            }
        }

        // Map itinerary - support nested paths from aggregation_config
        $itinerariesPath = $dataStructure['data_structure']['itineraries']['path'] ?? null;
        
        if ($itinerariesPath) {
            // Use custom nested path (e.g., "periods[].tour_daily[].day_list[]" for GO365)
            $itineraryItems = $this->flattenNestedPath($rawTour, $itinerariesPath);
        } else {
            // Default: use standard itinerary arrays
            $itineraryItems = [];
            $itinCandidates = ['Itinerary', 'itinerary', 'Itineraries', 'itineraries', 'Days', 'days', 'Programs', 'programs', 'plans', 'Plans'];
            foreach ($itinCandidates as $key) {
                if (isset($rawTour[$key]) && is_array($rawTour[$key])) {
                    $itineraryItems = $rawTour[$key];
                    break;
                }
            }
        }
        
        if (isset($mappings['itinerary']) && !empty($itineraryItems)) {
            $dayIndex = 1; // Auto-increment day_number
            foreach ($itineraryItems as $itineraryItem) {
                $it = [];
                foreach ($mappings['itinerary'] as $mapping) {
                    $fieldName = $mapping->our_field;
                    $path = $mapping->their_field_path ?? $mapping->their_field ?? '';
                    
                    // Skip if no path defined
                    if (empty($path)) {
                        continue;
                    }
                    
                    // Remove all known array prefixes to get the final field key
                    $cleanPath = $this->cleanNestedPath($path, $itinerariesPath);
                    
                    // Extract value - support nested fields within the item
                    $value = $this->extractNestedValue($itineraryItem, $cleanPath);
                    $value = $applyTransform($value, $mapping, $itineraryItem);
                    
                    if ($value === null && !empty($mapping->default_value)) {
                        $value = $mapping->default_value;
                    }
                    
                    $it[$fieldName] = $value;
                }
                
                // Auto-generate day_number if not mapped
                if (empty($it['day_number'])) {
                    $it['day_number'] = $dayIndex;
                }
                $dayIndex++;
                
                $result['itinerary'][] = $it;
            }
        }

        return $result;
    }
    
    /**
     * Flatten nested array path into a single array of items
     * e.g., "periods[].tour_period[]" will iterate periods, then tour_period within each
     * 
     * @param array $data Source data
     * @param string $path Nested path like "periods[].tour_period[]"
     * @return array Flattened array of all nested items
     */
    protected function flattenNestedPath(array $data, string $path): array
    {
        // Remove trailing [] if present
        $path = rtrim($path, '[]');
        
        // Split by []. to get array segments
        $segments = preg_split('/\[\]\.?/', $path);
        $segments = array_filter($segments, fn($s) => !empty($s));
        
        // Start with the source data wrapped in array
        $result = [$data];
        
        foreach ($segments as $segment) {
            $newResult = [];
            foreach ($result as $item) {
                if (isset($item[$segment]) && is_array($item[$segment])) {
                    // Add all items from this array segment
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
     * Clean nested path by removing the base path prefix
     * e.g., "periods[].tour_period[].period_id" with base "periods[].tour_period[]"
     *       returns "period_id"
     * 
     * @param string|null $fullPath Full field path from mapping
     * @param string|null $basePath Base path from aggregation_config (null = use default cleaning)
     * @return string Cleaned path relative to the nested item
     */
    protected function cleanNestedPath(?string $fullPath, ?string $basePath): string
    {
        if (!$fullPath) {
            return '';
        }
        if ($basePath) {
            // Remove the base path prefix
            // e.g., "periods[].tour_period[].period_id" → "period_id"
            $basePattern = preg_quote(rtrim($basePath, '[]') . '.', '/');
            $cleanPath = preg_replace('/^' . $basePattern . '/', '', $fullPath);
            
            // Also try with [] at the end
            $basePatternWithBracket = preg_quote($basePath . '.', '/');
            $cleanPath = preg_replace('/^' . $basePatternWithBracket . '/', '', $cleanPath);
            
            return $cleanPath;
        }
        
        // Default: remove standard prefixes (backwards compatibility)
        $cleanPath = preg_replace('/^[Pp]eriods\[\]\./', '', $fullPath);
        $cleanPath = preg_replace('/^[Ss]chedules\[\]\./', '', $cleanPath);
        $cleanPath = preg_replace('/^[Dd]epartures\[\]\./', '', $cleanPath);
        $cleanPath = preg_replace('/^[Ff]lights\[\]\./', '', $cleanPath);
        $cleanPath = preg_replace('/^[Ii]tinerary\[\]\./', '', $cleanPath);
        $cleanPath = preg_replace('/^[Ii]tineraries\[\]\./', '', $cleanPath);
        $cleanPath = preg_replace('/^[Dd]ays\[\]\./', '', $cleanPath);
        $cleanPath = preg_replace('/^[Pp]rograms\[\]\./', '', $cleanPath);
        $cleanPath = preg_replace('/^[Pp]lans\[\]\./', '', $cleanPath);
        
        return $cleanPath;
    }
    
    /**
     * Extract value from nested path within an item
     * Supports dot notation and nested arrays
     * 
     * @param array $item Source item
     * @param string $path Field path (can include [] for arrays)
     * @return mixed Extracted value
     */
    protected function extractNestedValue(array $item, string $path)
    {
        if (empty($path)) return null;
        
        // If path contains [], it's a nested array - get first element's value
        if (strpos($path, '[]') !== false) {
            $parts = explode('[].', $path, 2);
            $arrayKey = $parts[0];
            $fieldPath = $parts[1] ?? null;
            
            if (!isset($item[$arrayKey]) || !is_array($item[$arrayKey])) return null;
            if (empty($item[$arrayKey])) return null;
            
            // Get first element from array
            $firstItem = $item[$arrayKey][0] ?? null;
            if (!$firstItem) return null;
            
            if ($fieldPath) {
                // Recursively get nested field
                return $this->extractNestedValue($firstItem, $fieldPath);
            }
            return $firstItem;
        }
        
        // Simple dot notation
        if (strpos($path, '.') !== false) {
            $keys = explode('.', $path);
            $value = $item;
            foreach ($keys as $key) {
                if (!is_array($value) || !isset($value[$key])) return null;
                $value = $value[$key];
            }
            return $value;
        }
        
        // Direct field access
        return $item[$path] ?? null;
    }

    /**
     * Process tours data
     */
    protected function processTours(array $toursData, WholesalerApiConfig $config, SyncLog $syncLog): array
    {
        $stats = [
            'received' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'periods_received' => 0,
            'periods_created' => 0,
            'periods_updated' => 0,
        ];

        // Initialize PDF branding service if configured
        $pdfBranding = null;
        $wholesalerCode = $config->wholesaler?->code ?? 'default';
        if ($config->pdf_header_image || $config->pdf_footer_image) {
            $pdfBranding = new PdfBrandingService();
            $pdfBranding->setHeader($config->pdf_header_image, $config->pdf_header_height);
            $pdfBranding->setFooter($config->pdf_footer_image, $config->pdf_footer_height);
        }

        // Check if this is a single tour object or array of sections
        // If it has 'tour' key at top level, it's a single tour
        if (isset($toursData['tour'])) {
            $toursData = [$toursData]; // Wrap in array
        }

        foreach ($toursData as $tourData) {
            $stats['received']++;

            try {
                DB::beginTransaction();

                // Remove emojis from all text fields
                $tourData = $this->removeEmojisFromArray($tourData);

                $result = $this->processSingleTour($tourData, $config, $pdfBranding, $wholesalerCode, $syncLog);
                
                if ($result['action'] === 'created') {
                    $stats['created']++;
                } elseif ($result['action'] === 'updated') {
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }

                $stats['periods_received'] += $result['periods_received'] ?? 0;
                $stats['periods_created'] += $result['periods_created'] ?? 0;
                $stats['periods_updated'] += $result['periods_updated'] ?? 0;

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $stats['errors']++;

                // Log error
                SyncErrorLog::create([
                    'sync_log_id' => $syncLog->id,
                    'wholesaler_id' => $this->wholesalerId,
                    'entity_type' => 'tour',
                    'entity_code' => $tourData['tour']['tour_code'] ?? 'unknown',
                    'error_type' => 'database', // enum: mapping, validation, lookup, type_cast, api, database, unknown
                    'error_message' => $e->getMessage(),
                    'raw_data' => $tourData,
                ]);

                Log::warning('SyncToursJob: Failed to process tour', [
                    'tour_code' => $tourData['tour']['tour_code'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Cleanup PDF branding service
        if ($pdfBranding) {
            $pdfBranding->cleanup();
        }

        return $stats;
    }

    /**
     * Process a single tour
     */
    protected function processSingleTour(
        array $tourData,
        WholesalerApiConfig $config,
        ?PdfBrandingService $pdfBranding,
        string $wholesalerCode,
        SyncLog $syncLog
    ): array {
        $result = [
            'action' => 'skipped',
            'periods_received' => 0,
            'periods_created' => 0,
            'periods_updated' => 0,
        ];

        $tourSection = $tourData['tour'] ?? [];
        $contentSection = $tourData['content'] ?? [];
        $mediaSection = $tourData['media'] ?? [];
        $seoSection = $tourData['seo'] ?? [];
        $departures = $tourData['departure'] ?? [];
        $itineraries = $tourData['itinerary'] ?? [];
        
        // Merge content, media, and seo sections into tour section
        // This allows fields like description, highlights, cover_image_url, pdf_url, meta_title to be filled
        $tourSection = array_merge($tourSection, $contentSection, $mediaSection, $seoSection);

        if (empty($tourSection['tour_code']) && empty($tourSection['title'])) {
            return $result;
        }

        // Process PDF if exists - upload to Cloudflare R2 (with branding if configured)
        $pdfUrl = $tourSection['pdf_url'] ?? null;
        if ($pdfUrl && str_starts_with($pdfUrl, 'http') && !str_contains($pdfUrl, env('R2_URL', ''))) {
            try {
                if ($pdfBranding) {
                    // Download, add header/footer, then upload to R2
                    $brandedPdfUrl = $pdfBranding->processAndUpload($pdfUrl, $wholesalerCode);
                    if ($brandedPdfUrl) {
                        $tourSection['pdf_url'] = $brandedPdfUrl;
                    }
                } else {
                    // No branding - still upload to R2
                    $filename = pathinfo(parse_url($pdfUrl, PHP_URL_PATH), PATHINFO_FILENAME);
                    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename) . '_' . uniqid() . '.pdf';
                    $r2Path = "pdfs/{$wholesalerCode}/" . date('Y/m') . "/{$filename}";
                    
                    // Download and upload to R2
                    $pdfContent = file_get_contents($pdfUrl);
                    if ($pdfContent) {
                        $disk = \Storage::disk('r2');
                        $disk->put($r2Path, $pdfContent, 'public');
                        $r2Url = env('R2_URL');
                        if ($r2Url) {
                            $tourSection['pdf_url'] = rtrim($r2Url, '/') . '/' . $r2Path;
                        } else {
                            $tourSection['pdf_url'] = $disk->url($r2Path);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('SyncToursJob: Failed to upload PDF to R2', [
                    'url' => $pdfUrl,
                    'error' => $e->getMessage(),
                ]);
                // Keep original URL if upload fails
            }
        }
        
        // Process cover image - upload to Cloudflare
        $coverImageUrl = $tourSection['cover_image_url'] ?? null;
        if ($coverImageUrl && str_starts_with($coverImageUrl, 'http') && !str_contains($coverImageUrl, 'imagedelivery.net')) {
            try {
                $cloudflare = app(CloudflareImagesService::class);
                if ($cloudflare->isConfigured()) {
                    $uploadResult = $cloudflare->uploadFromUrl($coverImageUrl, 'tour-cover-' . uniqid());
                    if ($uploadResult && isset($uploadResult['id'])) {
                        $tourSection['cover_image_url'] = $cloudflare->getDisplayUrl($uploadResult['id']);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('SyncToursJob: Failed to upload cover image', [
                    'url' => $coverImageUrl,
                    'error' => $e->getMessage(),
                ]);
                // Keep original URL if upload fails
            }
        }

        // ค้นหา tour โดยใช้ tour_code wholesaler_tour_code หรือ external_id
        $tourCode = $tourSection['tour_code'] ?? $tourSection['wholesaler_tour_code'] ?? $tourSection['external_id'] ?? null;
        
        $tour = Tour::where('wholesaler_id', $config->wholesaler_id)
            ->where(function ($q) use ($tourCode, $tourSection) {
                $q->where('wholesaler_tour_code', $tourCode)
                  ->orWhere('external_id', $tourSection['external_id'] ?? null);
            })
            ->first();

        $isNew = !$tour;

        if ($isNew) {
            $tour = new Tour();
            $tour->wholesaler_id = $config->wholesaler_id;
            $tour->wholesaler_tour_code = $tourCode;
            $tour->data_source = 'api';
            $tour->status = 'draft';
            // Auto-generate tour_code (always generate new code, don't use wholesaler_tour_code)
            $tour->tour_code = $this->generateTourCode($config->wholesaler_id);
            $result['action'] = 'created';
        } else {
            // Skip if tour was created manually (even if codes match)
            if ($tour->data_source === 'manual') {
                Log::info('SyncToursJob: Skipped manual tour', [
                    'tour_id' => $tour->id,
                    'tour_code' => $tour->tour_code,
                ]);
                $result['action'] = 'skipped';
                return $result; 
                ///// 
            }
            
            // Check if tour is locked for sync
            if ($tour->sync_locked) {
                Log::info('SyncToursJob: Skipped locked tour', [
                    'tour_id' => $tour->id,
                    'tour_code' => $tour->tour_code,
                ]);
                $result['action'] = 'skipped';
                return $result;
            }
            $result['action'] = 'updated';
        }

        // Fill tour fields from transformed data (no hardcode - use mapping result)
        // Filter only fillable fields and merge with existing values

        // Debug: Check what's in tourSection before processing
        Log::info('SyncToursJob: tourSection before fill', [
            'tour_code' => $tourSection['tour_code'] ?? $tourSection['wholesaler_tour_code'] ?? 'N/A',
            'has_primary_country_id' => array_key_exists('primary_country_id', $tourSection),
            'primary_country_id' => $tourSection['primary_country_id'] ?? 'NOT_SET',
            'has_transport_id' => array_key_exists('transport_id', $tourSection),
            'transport_id' => $tourSection['transport_id'] ?? 'NOT_SET',
            'tourSection_keys' => array_keys($tourSection),
        ]);

        $fillableFields = $tour->getFillable();
        $tourFields = []; //
        // Fields that should be null when empty (numeric fields)
        $numericFields = ['hotel_star', 'duration_days', 'duration_nights', 'primary_country_id', 'transport_id'];
        
        foreach ($tourSection as $field => $value) {
            // Skip null values to keep existing data
            if ($value === null) continue;
            //
            // Convert empty string to null for numeric fields
            if ($value === '' && in_array($field, $numericFields)) {
                continue; // Skip empty numeric fields
            }
            
            // Only fill if it's a fillable field
            if (in_array($field, $fillableFields) || empty($fillableFields)) {
                $tourFields[$field] = $value;
            }
        }
        
        // Auto-generate tour_code if not provided (always generate, don't use wholesaler code)
        if (empty($tour->tour_code)) {
            $tourFields['tour_code'] = $this->generateTourCode($config->wholesaler_id);
        }
        
        // Auto-calculate duration_nights from duration_days if not provided
        if (empty($tourFields['duration_nights']) && !empty($tourFields['duration_days'])) {
            $tourFields['duration_nights'] = max(0, (int)$tourFields['duration_days'] - 1);
        }
        
        // Set sync metadata (system fields, not from mapping)
        $tourFields['sync_status'] = 'active';
        $tourFields['last_synced_at'] = now();
        
        // Debug: Log tourFields before save
        Log::info('SyncToursJob: tourFields before save', [
            'tour_code' => $tourFields['tour_code'] ?? $tour->tour_code ?? 'N/A',
            'has_primary_country_id' => array_key_exists('primary_country_id', $tourFields),
            'primary_country_id' => $tourFields['primary_country_id'] ?? 'NOT_IN_FIELDS',
            'has_transport_id' => array_key_exists('transport_id', $tourFields),
            'transport_id' => $tourFields['transport_id'] ?? 'NOT_IN_FIELDS',
        ]);
        
        $tour->fill($tourFields);
        $tour->save();
        
        // Sync primary country to tour_countries pivot table
        if (!empty($tourFields['primary_country_id'])) {
            $countryId = $tourFields['primary_country_id'];
            
            // Remove old primary country if different
            $currentPrimary = $tour->countries()->wherePivot('is_primary', true)->first();
            if ($currentPrimary && $currentPrimary->id !== $countryId) {
                // Remove old primary (only if it was the only reason it was attached)
                $tour->countries()->detach($currentPrimary->id);
            }
            
            // Add new primary country if not exists
            $exists = $tour->countries()->where('country_id', $countryId)->exists();
            if (!$exists) {
                $tour->countries()->attach($countryId, ['is_primary' => true, 'sort_order' => 1]);
            } else {
                // Update existing to be primary
                $tour->countries()->updateExistingPivot($countryId, ['is_primary' => true]);
            }
        }
        
        // Extract cities from tour title if enabled
        $tourTitle = $tour->title ?? $tour->name ?? null;
        if ($config->extract_cities_from_name && !empty($tourTitle)) {
            $extractedCities = CityExtractorService::extract($tourTitle);
            
            if ($extractedCities->isNotEmpty()) {
                // Get existing cities for this tour (Many-to-Many through tour_cities)
                $existingCityIds = $tour->cities()->pluck('cities.id')->toArray();
                
                // Prepare cities to sync
                $citiesToSync = [];
                $sortOrder = $tour->cities()->max('tour_cities.sort_order') ?? 0;
                
                // Keep existing cities with their pivot data
                foreach ($tour->cities as $existingCity) {
                    $citiesToSync[$existingCity->id] = [
                        'country_id' => $existingCity->pivot->country_id ?? $existingCity->country_id,
                        'sort_order' => $existingCity->pivot->sort_order,
                    ];
                }
                
                // Add new extracted cities
                foreach ($extractedCities as $city) {
                    if (!in_array($city->id, $existingCityIds)) {
                        $sortOrder++;
                        $citiesToSync[$city->id] = [
                            'country_id' => $city->country_id,
                            'sort_order' => $sortOrder,
                        ];
                    }
                }
                
                // Sync cities (Many-to-Many)
                $tour->cities()->sync($citiesToSync);
                
                Log::info('SyncToursJob: Extracted cities from tour name', [
                    'tour_id' => $tour->id,
                    'tour_title' => $tourTitle,
                    'cities_found' => $extractedCities->pluck('name_th')->toArray(),
                ]);
            }
        }
        
        // Sync transport to tour_transports table
        if (!empty($tourFields['transport_id'])) {
            $transportId = $tourFields['transport_id'];
            $transport = \App\Models\Transport::find($transportId);
            if ($transport) {
                // Check if already exists
                $exists = $tour->transports()->where('transport_id', $transportId)->exists();
                if (!$exists) {
                    $tour->transports()->create([
                        'transport_id' => $transportId,
                        'transport_code' => $transport->code ?? '',
                        'transport_name' => $transport->name ?? '',
                        'transport_type' => 'outbound', // enum: outbound, inbound, domestic
                        'sort_order' => 1,
                    ]);
                }
            }
        }

        // Process departures/periods - check sync_mode
        $syncMode = $config->sync_mode ?? 'single';
        
        if ($syncMode === 'two_phase') {
            // Two-Phase Sync: dispatch SyncPeriodsJob to fetch periods separately
            $externalId = $tour->external_id ?? $tour->wholesaler_tour_code;
            
            if ($externalId) {
                SyncPeriodsJob::dispatch(
                    $tour->id,
                    $externalId,
                    $config->wholesaler_id,
                    $syncLog->id
                )->onQueue('periods');
                
                Log::info('SyncToursJob: Dispatched SyncPeriodsJob for two-phase sync', [
                    'tour_id' => $tour->id,
                    'external_id' => $externalId,
                ]);
            }
            
            // Mark stats as deferred (will be updated by SyncPeriodsJob)
            $result['periods_received'] = 0;
            $result['periods_created'] = 0;
            $result['periods_updated'] = 0;
        } else {
            // Single-Phase (default): process periods from same API response
            $result['periods_received'] = count($departures);
            foreach ($departures as $dep) {
                $periodResult = $this->processPeriod($tour, $dep);
                if ($periodResult === 'created') {
                    $result['periods_created']++;
                } elseif ($periodResult === 'updated') {
                    $result['periods_updated']++;
                }
            }
        }

        // Process itineraries - check sync_mode
        if ($syncMode === 'two_phase') {
            // Two-Phase Sync: fetch itineraries from separate API endpoint
            $credentials = $config->auth_credentials ?? [];
            $endpoints = $credentials['endpoints'] ?? [];
            $itinerariesEndpoint = $endpoints['itineraries'] ?? null;
            
            if ($itinerariesEndpoint && ($tour->external_id || $tour->wholesaler_tour_code)) {
                $this->fetchAndSyncItineraries($tour, $config, $itinerariesEndpoint);
            }
        } else {
            // Single-Phase: process itineraries from same API response
            foreach ($itineraries as $itin) {
                $this->processItinerary($tour, $itin);
            }
        }

        // Recalculate aggregates (min_price, max_price, hotel_star_min/max, etc.)
        $tour->recalculateAggregates();

        return $result;
    }

    /**
     * Process a single period/departure
     */
    protected function processPeriod(Tour $tour, array $depData): string
    {
        // Map departure_date → start_date if needed
        if (!empty($depData['departure_date']) && empty($depData['start_date'])) {
            $depData['start_date'] = $depData['departure_date'];
        }
        
        // Map return_date → end_date if needed
        if (!empty($depData['return_date']) && empty($depData['end_date'])) {
            $depData['end_date'] = $depData['return_date'];
        }
        
        $externalId = $depData['external_id'] ?? null;
        $departureDate = $depData['start_date'] ?? $depData['departure_date'] ?? null;

        if (!$departureDate) {
            return 'skipped';
        }

        // Find existing period
        $period = Period::where('tour_id', $tour->id)
            ->where(function ($q) use ($externalId, $departureDate) {
                if ($externalId) {
                    $q->where('external_id', $externalId);
                } else {
                    $q->where('start_date', $departureDate);
                }
            })
            ->first();

        $isNew = !$period;

        if ($isNew) {
            $period = new Period();
            $period->tour_id = $tour->id;
        }

        // Fill period fields from transformed data (no hardcode)
        $fillableFields = $period->getFillable();
        $periodFields = [];
        
        foreach ($depData as $field => $value) {
            if ($value === null) continue;
            if (in_array($field, $fillableFields) || empty($fillableFields)) {
                $periodFields[$field] = $value;
            }
        }
        
        // Auto-generate period_code if not provided (auto-running allowed)
        if (empty($periodFields['period_code']) && empty($period->period_code) && $departureDate) {
            $periodFields['period_code'] = 'P' . date('ymd', strtotime($departureDate));
        }
        
        // Map status if provided (system transform)
        if (isset($depData['status'])) {
            $periodFields['status'] = $this->mapPeriodStatus($depData['status']);
        }
        
        // Sanitize: available/capacity cannot be negative (UNSIGNED columns)
        if (isset($periodFields['available']) && $periodFields['available'] < 0) {
            $periodFields['available'] = 0;
        }
        if (isset($periodFields['capacity']) && $periodFields['capacity'] < 0) {
            $periodFields['capacity'] = 0;
        }
        
        $period->fill($periodFields);
        $period->save();

        // Create/update offer for pricing
        if (isset($depData['price_adult'])) {
            $this->processOffer($period, $depData);
        }

        return $isNew ? 'created' : 'updated';
    }

    /**
     * Process offer/pricing for a period
     */
    protected function processOffer(Period $period, array $depData): void
    {
        $offer = Offer::firstOrNew(['period_id' => $period->id]);
        
        // Fill offer fields from transformed data (no hardcode)
        $fillableFields = $offer->getFillable();
        $offerFields = [];
        
        foreach ($depData as $field => $value) {
            if ($value === null) continue;
            if (in_array($field, $fillableFields) || empty($fillableFields)) {
                $offerFields[$field] = $value;
            }
        }
        
        // Default currency if not provided
        if (empty($offerFields['currency']) && empty($offer->currency)) {
            $offerFields['currency'] = 'THB';
        }
        
        $offer->fill($offerFields);

        $offer->save();
    }

    /**
     * Process a single itinerary
     */
    protected function processItinerary(Tour $tour, array $itinData): void
    {
        $dayNumber = $itinData['day_number'] ?? null;
        $externalId = $itinData['external_id'] ?? null;

        if (!$dayNumber && !$externalId) {
            return;
        }

        // Find existing itinerary
        $itinerary = TourItinerary::where('tour_id', $tour->id)
            ->where(function ($q) use ($dayNumber, $externalId) {
                if ($externalId) {
                    $q->where('external_id', $externalId);
                } else {
                    $q->where('day_number', $dayNumber);
                }
            })
            ->first();

        if (!$itinerary) {
            $itinerary = new TourItinerary();
            $itinerary->tour_id = $tour->id;
        }

        // Fill itinerary fields from transformed data (no hardcode)
        $fillableFields = $itinerary->getFillable();
        $itinFields = [];
        
        // Fields that should be null when empty (numeric fields)
        $numericFields = ['hotel_star', 'day_number', 'sort_order'];
        
        foreach ($itinData as $field => $value) {
            if ($value === null) continue;
            // Convert empty string to null for numeric fields
            if ($value === '' && in_array($field, $numericFields)) {
                continue; // Skip empty numeric fields
            }
            if (in_array($field, $fillableFields) || empty($fillableFields)) {
                $itinFields[$field] = $value;
            }
        }
        
        // Set data_source (system field)
        $itinFields['data_source'] = 'api';
        
        // Auto-set sort_order from day_number if not provided
        if (empty($itinFields['sort_order']) && !empty($itinFields['day_number'])) {
            $itinFields['sort_order'] = $itinFields['day_number'];
        }
        
        // Ensure description has a value (required field, NOT NULL)
        if (empty($itinFields['description']) && empty($itinerary->description)) {
            $itinFields['description'] = $itinFields['title'] ?? 'Day ' . ($itinFields['day_number'] ?? '');
        }
        
        $itinerary->fill($itinFields);
        $itinerary->save();
    }

    /**
     * Map status string to Period status
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
     * Generate unique tour code
     * Format: NT+YYMM+XXX (e.g., NT202601001)
     */
    protected function generateTourCode(int $wholesalerId): string
    {
        $prefix = 'NT';
        $yearMonth = now()->format('Ym'); // e.g., 202601
        
        // Find last tour code with same prefix and year-month
        $lastTour = Tour::where('tour_code', 'like', "{$prefix}{$yearMonth}%")
            ->orderBy('tour_code', 'desc')
            ->first();
        
        if ($lastTour && preg_match('/NT\d{6}(\d{3})$/', $lastTour->tour_code, $matches)) {
            $seq = intval($matches[1]) + 1;
        } else {
            $seq = 1;
        }
        
        // NT + YYMM + 3-digit sequence (e.g., NT202601001)
        return $prefix . $yearMonth . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Fetch and sync itineraries from separate API endpoint (Two-Phase Sync)
     */
    protected function fetchAndSyncItineraries(Tour $tour, WholesalerApiConfig $config, string $itinerariesEndpoint): void
    {
        // Build URL - replace placeholders
        $url = str_replace(
            ['{external_id}', '{tour_code}', '{wholesaler_tour_code}'],
            [$tour->external_id ?? '', $tour->tour_code ?? '', $tour->wholesaler_tour_code ?? ''],
            $itinerariesEndpoint
        );

        try {
            $adapter = AdapterFactory::create($config->wholesaler_id);
            $fetchResult = $adapter->fetchItineraries($url);

            if (!$fetchResult->success) {
                Log::warning('SyncToursJob: Failed to fetch itineraries', [
                    'tour_id' => $tour->id,
                    'url' => $url,
                    'error' => $fetchResult->error ?? 'Unknown error',
                ]);
                return;
            }

            Log::info('SyncToursJob: Fetched itineraries', [
                'tour_id' => $tour->id,
                'count' => count($fetchResult->itineraries ?? []),
            ]);

            // Get field mappings for itineraries
            $mappings = WholesalerFieldMapping::where('wholesaler_id', $config->wholesaler_id)
                ->where('section_name', 'itinerary')
                ->where('is_active', true)
                ->get();

            // Process each itinerary
            foreach ($fetchResult->itineraries ?? [] as $rawItinerary) {
                $itinData = $this->transformItineraryData($rawItinerary, $mappings);
                $this->processItinerary($tour, $itinData);
            }

        } catch (\Exception $e) {
            Log::error('SyncToursJob: Exception fetching itineraries', [
                'tour_id' => $tour->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Transform raw itinerary data using mappings
     */
    protected function transformItineraryData(array $rawItinerary, $mappings): array
    {
        $itinData = [];
        
        foreach ($mappings as $mapping) {
            $fieldPath = $mapping->their_field_path ?? $mapping->their_field ?? null;
            if (empty($fieldPath)) {
                continue;
            }
            
            // Strip array prefix since we're already iterating over the array
            $cleanPath = preg_replace('/^[Ii]tinerary\[\]\./', '', $fieldPath);
            $cleanPath = preg_replace('/^[Ii]tineraries\[\]\./', '', $cleanPath);
            $cleanPath = preg_replace('/^[Dd]ays\[\]\./', '', $cleanPath);
            $cleanPath = preg_replace('/^[Pp]rograms\[\]\./', '', $cleanPath);
            
            $value = $this->extractValue($rawItinerary, $cleanPath);
            
            if ($value !== null && $mapping->transform_type) {
                $value = $this->applyTransformValue($value, $mapping);
            }
            
            if ($value === null && !empty($mapping->default_value)) {
                $value = $mapping->default_value;
            }
            
            $itinData[$mapping->our_field] = $value;
        }
        
        return $itinData;
    }

    /**
     * Extract value from nested array using dot notation
     */
    protected function extractValue(array $data, string $path): mixed
    {
        $parts = explode('.', $path);
        $value = $data;

        foreach ($parts as $part) {
            if (!is_array($value) || !isset($value[$part])) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Apply transform to value based on mapping config
     */
    protected function applyTransformValue(mixed $value, WholesalerFieldMapping $mapping): mixed
    {
        $type = $mapping->transform_type;
        $config = $mapping->transform_config ?? [];

        if (!$type || $type === 'direct') {
            return $value;
        }

        switch ($type) {
            case 'value_map':
                $map = $config['map'] ?? [];
                if (isset($config['value_map'])) {
                    foreach ($config['value_map'] as $item) {
                        $fromVal = $item['from'] ?? null;
                        if ($fromVal === '__EMPTY__') $fromVal = '';
                        if ($fromVal !== null) {
                            $map[$fromVal] = $item['to'] ?? null;
                        }
                    }
                }
                $lookupKey = ($value === '' || $value === null) ? '' : $value;
                $mappedValue = $map[$lookupKey] ?? $config['default'] ?? null;
                if ($mappedValue === 'true') return 1;
                if ($mappedValue === 'false') return 0;
                return $mappedValue ?? $value;

            case 'date_format':
                if ($value) {
                    try {
                        $format = $config['output_format'] ?? 'Y-m-d';
                        return \Carbon\Carbon::parse($value)->format($format);
                    } catch (\Exception $e) {
                        return $value;
                    }
                }
                return $value;

            default:
                return $value;
        }
    }

    /**
     * Clean text: remove HTML tags and emojis
     */
    protected function cleanText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Step 1: Convert <br>, <br/>, <br /> to newline first
        $cleaned = preg_replace('/<br\s*\/?>/i', "\n", $text);
        
        // Step 2: Remove all HTML tags (including malformed ones)
        $cleaned = preg_replace('/<[^>]*>/', '', $cleaned);
        $cleaned = strip_tags($cleaned);
        
        // Step 3: Remove any remaining HTML fragments like /> or <
        $cleaned = preg_replace('/\s*\/?>/', '', $cleaned);
        $cleaned = str_replace(['<', '>'], '', $cleaned);
        
        // Step 4: Decode HTML entities
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Step 5: Remove emojis - Comprehensive emoji removal pattern
        $emojiPatterns = [
            // Emoji presentation sequences (emoji + variation selector)
            '/[\x{1F000}-\x{1FFFF}]/u',
            // Miscellaneous Symbols and Pictographs
            '/[\x{2600}-\x{27BF}]/u',
            // Supplemental Symbols
            '/[\x{1F900}-\x{1F9FF}]/u',
            // Transport and Map Symbols  
            '/[\x{1F680}-\x{1F6FF}]/u',
            // Emoticons
            '/[\x{1F600}-\x{1F64F}]/u',
            // Misc Symbols
            '/[\x{2300}-\x{23FF}]/u',
            // Dingbats
            '/[\x{2700}-\x{27BF}]/u',
            // Regional Indicator Symbols (flags)
            '/[\x{1F1E0}-\x{1F1FF}]/u',
            // Variation Selectors
            '/[\x{FE00}-\x{FE0F}]/u',
            // Zero Width Joiner
            '/[\x{200D}]/u',
            // Geometric shapes
            '/[\x{25A0}-\x{25FF}]/u',
            // Arrows
            '/[\x{2190}-\x{21FF}]/u',
            // Enclosed Alphanumerics
            '/[\x{2460}-\x{24FF}]/u',
            // Box Drawing and Block Elements
            '/[\x{2500}-\x{259F}]/u',
            // CJK Symbols
            '/[\x{3000}-\x{303F}]/u',
            // Enclosed CJK Letters
            '/[\x{3200}-\x{32FF}]/u',
            // Red/black triangles and similar symbols
            '/[\x{25B2}-\x{25BC}]/u',
            '/[\x{25C6}-\x{25CF}]/u',
            '/[\x{25EF}]/u',
            // Tags block (E0000-E007F)
            '/[\x{E0000}-\x{E007F}]/u',
            // Skin tone modifiers
            '/[\x{1F3FB}-\x{1F3FF}]/u',
        ];

        foreach ($emojiPatterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }
        
        // Step 6: Remove \r\n and normalize whitespace
        $cleaned = str_replace(["\r\n", "\r"], "\n", $cleaned);
        
        // Step 7: Clean up multiple newlines (keep max 2)
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned);
        
        // Step 8: Trim each line and remove leading spaces after emoji removal
        $lines = explode("\n", $cleaned);
        $lines = array_map('trim', $lines);
        
        // Step 9: Remove empty lines at start and end, but keep one empty line between paragraphs
        $lines = array_filter($lines, fn($line, $index) => 
            $line !== '' || ($index > 0 && $index < count($lines) - 1), 
            ARRAY_FILTER_USE_BOTH
        );
        
        $cleaned = implode("\n", $lines);
        
        // Step 10: Clean up multiple spaces within text
        $cleaned = preg_replace('/[ \t]+/', ' ', $cleaned);
        
        return trim($cleaned);
    }

    /**
     * Remove emojis from a string (alias for backward compatibility)
     */
    protected function removeEmojis(?string $text): ?string
    {
        return $this->cleanText($text);
    }

    /**
     * Clean all string values in an array recursively (remove HTML and emojis)
     * Skip certain fields that should keep their original formatting
     */
    protected function removeEmojisFromArray(array $data, array $skipFields = []): array
    {
        // Fields to skip cleaning (keep original HTML formatting)
        // Currently empty - all fields will be cleaned
        $defaultSkipFields = [];
        
        // Fields that are arrays of strings - need to filter empty/short items after cleaning
        $arrayStringFields = [
            'highlights',
            'shopping_highlights',
            'food_highlights',
            'themes',
            'suitable_for',
            'keywords',
            'hashtags',
            'places',
            'gallery',
            'images',
        ];
        
        $skipFields = array_merge($defaultSkipFields, $skipFields);
        
        foreach ($data as $key => $value) {
            // Skip specified fields - keep original value
            if (in_array($key, $skipFields)) {
                continue;
            }
            
            if (is_string($value)) {
                $data[$key] = $this->cleanText($value);
            } elseif (is_array($value)) {
                // Check if this is an array of strings that needs filtering
                if (in_array($key, $arrayStringFields)) {
                    // Clean each string in array
                    $cleaned = [];
                    foreach ($value as $item) {
                        if (is_string($item)) {
                            $cleanedItem = $this->cleanText($item);
                            // Only keep items with meaningful content (more than 5 chars or contains Thai)
                            if ($cleanedItem !== null && $cleanedItem !== '') {
                                $trimmed = trim($cleanedItem);
                                // Keep if: has Thai text OR length > 5 chars OR is a URL
                                $hasThai = preg_match('/[\x{0E00}-\x{0E7F}]/u', $trimmed);
                                $isUrl = preg_match('/^https?:\/\//i', $trimmed);
                                $isLongEnough = mb_strlen($trimmed) > 5;
                                
                                if ($hasThai || $isUrl || $isLongEnough) {
                                    $cleaned[] = $trimmed;
                                }
                            }
                        } elseif (is_array($item)) {
                            // Nested array
                            $cleaned[] = $this->removeEmojisFromArray($item, $skipFields);
                        } else {
                            $cleaned[] = $item;
                        }
                    }
                    $data[$key] = $cleaned;
                } else {
                    $data[$key] = $this->removeEmojisFromArray($value, $skipFields);
                }
            }
        }
        return $data;
    }
}
