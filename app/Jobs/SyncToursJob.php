<?php

namespace App\Jobs;

use App\Jobs\ProcessTourMediaJob;
use App\Jobs\SyncPeriodsJob;
use App\Models\Offer;
use App\Models\Period;
use App\Models\SyncCursor;
use App\Models\SyncErrorLog;
use App\Models\SyncLog;
use App\Models\SystemSetting;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use App\Services\CityExtractorService;
use App\Services\CountryExtractorService;
use App\Services\CloudflareImagesService;
use App\Services\NotificationService;
use App\Services\PdfBrandingService;
use App\Services\WholesalerAdapters\AdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
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

    public int $tries = 3;         // retry 3 ครั้ง สำหรับ transient errors (network, DB connection)
    public int $timeout = 600;     // 10 minutes
    public int $maxExceptions = 3; // ให้โอกาส retry ก่อน fail
    public bool $failOnTimeout = true; // timeout แล้วให้ fail ทันที
    
    /**
     * Backoff intervals between retries (in seconds)
     * Retry ครั้งที่ 1: รอ 30 วินาที, ครั้งที่ 2: รอ 60 วินาที, ครั้งที่ 3: รอ 120 วินาที
     */
    public array $backoff = [30, 60, 120];

    protected int $wholesalerId;
    protected ?array $transformedData;
    protected string $syncType;
    protected ?int $limit;
    protected ?int $syncLogId = null;
    protected ?string $syncLockKey = null;
    protected bool $processPeriodsInline = false;

    /**
     * In-memory cache for lookup transforms to avoid repeated DB queries.
     * Structure: ['table:column:value' => result_id]
     */
    protected array $lookupCache = [];

    /**
     * Cached SystemSetting sync settings (query once, reuse for all tours)
     */
    protected ?array $cachedSyncSettings = null;

    /**
     * Max retries for DB operations
     */
    protected int $dbMaxRetries = 3;
    
    /**
     * Delay between DB retries (seconds)
     */
    protected int $dbRetryDelay = 2;

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
     * When set to true, SyncPeriodsJob will be executed inline (synchronously)
     * instead of being dispatched to the queue. Useful for CLI sync commands.
     */
    public function setProcessPeriodsInline(bool $inline = true): self
    {
        $this->processPeriodsInline = $inline;
        return $this;
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

        // Check if sync is disabled (skip only for auto/incremental syncs, not manual)
        if (!$config->sync_enabled && !$this->transformedData && $this->syncType !== 'full') {
            Log::info('SyncToursJob: Sync disabled for this integration, skipping', [
                'wholesaler_id' => $this->wholesalerId,
                'sync_type' => $this->syncType,
            ]);
            return;
        }

        // Prevent concurrent syncs for the same wholesaler (auto/incremental only)
        if (!$this->transformedData) {
            $lockKey = "sync_lock:wholesaler:{$this->wholesalerId}";
            
            // FIX: Auto-heal stuck syncs — ใช้ heartbeat timeout 5 นาที (ลดจาก 15)
            // ถ้า heartbeat ไม่อัพเดทเกิน 5 นาที = worker ตายแล้ว → force clear
            $stuckSyncs = SyncLog::where('wholesaler_id', $this->wholesalerId)
                ->where('status', 'running')
                ->where(function ($q) {
                    // Heartbeat หยุดเกิน 5 นาที
                    $q->where(function ($q2) {
                        $q2->whereNotNull('last_heartbeat_at')
                            ->where('last_heartbeat_at', '<', now()->subMinutes(5));
                    })
                    // หรือไม่เคย heartbeat เลย และ started เกิน 5 นาที
                    ->orWhere(function ($q2) {
                        $q2->whereNull('last_heartbeat_at')
                            ->where('started_at', '<', now()->subMinutes(5));
                    });
                })
                ->get();
            
            if ($stuckSyncs->isNotEmpty()) {
                foreach ($stuckSyncs as $stuck) {
                    $stuck->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                        'error_summary' => ['message' => 'Auto-healed: worker heartbeat stopped for 5+ minutes'],
                    ]);
                    Log::warning('SyncToursJob: Auto-healed stuck sync', [
                        'sync_log_id' => $stuck->id,
                        'wholesaler_id' => $this->wholesalerId,
                        'last_heartbeat' => $stuck->last_heartbeat_at,
                        'started_at' => $stuck->started_at,
                    ]);
                }
                // FIX: Force release cache lock ด้วย (สำคัญ! ถ้าไม่ทำ lock จะค้างตลอด)
                Cache::lock($lockKey)->forceRelease();
            }
            
            // FIX: ใช้ lock timeout 660 วินาที (11 นาที) = job timeout 600 + buffer 60
            // ถ้า job timeout ไป lock จะ expire เองใน 1 นาทีหลังจากนั้น
            if (!Cache::lock($lockKey, 660)->get()) {
                // Double-check: ถ้าไม่มี running SyncLog จริง → force release lock (orphan lock)
                $actuallyRunning = SyncLog::where('wholesaler_id', $this->wholesalerId)
                    ->where('status', 'running')
                    ->exists();
                
                if (!$actuallyRunning) {
                    Log::warning('SyncToursJob: Orphan lock detected, force releasing', [
                        'wholesaler_id' => $this->wholesalerId,
                    ]);
                    Cache::lock($lockKey)->forceRelease();
                    
                    // Try again after releasing orphan lock
                    if (!Cache::lock($lockKey, 660)->get()) {
                        Log::error('SyncToursJob: Still cannot acquire lock after orphan cleanup', [
                            'wholesaler_id' => $this->wholesalerId,
                        ]);
                        return;
                    }
                } else {
                    Log::warning('SyncToursJob: Another sync genuinely running, skipping', [
                        'wholesaler_id' => $this->wholesalerId,
                        'sync_type' => $this->syncType,
                    ]);
                    return;
                }
            }
            
            // Store lock key to release later
            $this->syncLockKey = $lockKey;
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
                    'duration_seconds' => abs(now()->diffInSeconds($syncLog->started_at)),
                    'progress_percent' => 100,
                ]);
                return;
            }

            // Apply limit: use parameter first, fallback to config setting
            $totalFetched = count($toursData);
            $limit = $this->limit ?? $config->sync_limit;
            if ($limit !== null && $limit > 0) {
                $toursData = array_slice($toursData, 0, $limit);
                Log::info('SyncToursJob: Limited records', [
                    'wholesaler_id' => $this->wholesalerId,
                    'limit' => $limit,
                    'original_count' => $totalFetched,
                    'limited_count' => count($toursData),
                ]);
            }

            // Initialize progress tracking
            $chunkSize = 50;
            $totalItems = count($toursData);
            $syncLog->update([
                'total_items' => $totalItems,
                'tours_received' => $totalFetched, // Total fetched from API (before limit)
                'processed_items' => 0,
                'progress_percent' => 0,
                'chunk_size' => $chunkSize,
                'total_chunks' => ceil($totalItems / $chunkSize),
                'current_chunk' => 0,
                'last_heartbeat_at' => now(),
            ]);
            
            Log::info('SyncToursJob: Starting processTours', [
                'wholesaler_id' => $this->wholesalerId,
                'total_items' => $totalItems,
            ]);

            // Process tours in chunks
            $stats = $this->processTours($toursData, $config, $syncLog);

            // Determine final status
            $finalStatus = 'completed';
            if ($syncLog->cancel_requested) {
                $finalStatus = 'cancelled';
            } elseif ($stats['errors'] > 0 && $stats['created'] === 0 && $stats['updated'] === 0) {
                $finalStatus = 'failed';
            } elseif ($stats['errors'] > 0) {
                $finalStatus = 'partial';
            }

            // Update sync log with retry for connection issues
            $this->safeDbOperation(function() use ($syncLog, $finalStatus, $stats, $totalFetched) {
                $syncLog->update([
                    'status' => $finalStatus,
                    'completed_at' => now(),
                    'duration_seconds' => abs(now()->diffInSeconds($syncLog->started_at)),
                    'tours_received' => $totalFetched,
                    'tours_created' => $stats['created'],
                    'tours_updated' => $stats['updated'],
                    'tours_skipped' => $stats['skipped'],
                    'tours_failed' => $stats['errors'],
                    'periods_received' => $stats['periods_received'],
                    'periods_created' => $stats['periods_created'],
                    'periods_updated' => $stats['periods_updated'],
                    'error_count' => $stats['errors'],
                    'progress_percent' => 100,
                    'cancelled_at' => $syncLog->cancel_requested ? now() : null,
                    'cancel_reason' => $syncLog->cancel_requested ? 'User requested cancellation' : null,
                ]);
            }, 'final sync log update');

            Log::info('SyncToursJob: Completed', [
                'wholesaler_id' => $this->wholesalerId,
                'status' => $finalStatus,
                'total_fetched' => $totalFetched,
                'stats' => $stats,
            ]);

            // Update health check status based on sync result
            $this->safeDbOperation(function() use ($config, $finalStatus) {
                $config->update([
                    'last_health_check_at' => now(),
                    'last_health_check_status' => in_array($finalStatus, ['completed', 'partial']),
                ]);
            }, 'health status update');

        } catch (\Exception $e) {
            Log::error('SyncToursJob: Failed', [
                'wholesaler_id' => $this->wholesalerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Try to update sync log with error (may fail if DB connection is lost)
            try {
                $this->ensureDbConnection();
                $syncLog->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'duration_seconds' => abs(now()->diffInSeconds($syncLog->started_at)),
                    'error_summary' => ['message' => $e->getMessage()],
                ]);
            } catch (\Exception $dbError) {
                Log::error('SyncToursJob: Could not update sync log on failure', [
                    'sync_log_id' => $syncLog->id ?? null,
                    'original_error' => $e->getMessage(),
                    'db_error' => $dbError->getMessage(),
                ]);
            }

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
        } finally {
            // Always release sync lock
            $this->releaseSyncLock();
            
            // FIX: คืน DB connection เพื่อป้องกัน max_user_connections
            try {
                DB::disconnect();
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }

    /**
     * Release sync lock
     */
    protected function releaseSyncLock(): void
    {
        if ($this->syncLockKey) {
            try {
                Cache::lock($this->syncLockKey)->forceRelease();
            } catch (\Exception $e) {
                Log::warning('SyncToursJob: Failed to release lock', [
                    'lock_key' => $this->syncLockKey,
                    'error' => $e->getMessage(),
                ]);
            }
            $this->syncLockKey = null;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        // Release lock on failure
        $this->releaseSyncLock();
        
        // Mark any running sync logs as failed (try with connection recovery)
        try {
            $this->ensureDbConnection();
            SyncLog::where('wholesaler_id', $this->wholesalerId)
                ->where('status', 'running')
                ->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_summary' => ['message' => $exception?->getMessage() ?? 'Job failed'],
                ]);
        } catch (\Exception $e) {
            Log::error('SyncToursJob: Failed to update sync log on job failure', [
                'wholesaler_id' => $this->wholesalerId,
                'original_error' => $exception?->getMessage(),
                'db_error' => $e->getMessage(),
            ]);
        }
        
        Log::error('SyncToursJob: Job failed permanently', [
            'wholesaler_id' => $this->wholesalerId,
            'error' => $exception?->getMessage(),
        ]);
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
     * Get cached sync settings to avoid repeated DB queries
     */
    protected function getSyncSettings(): array
    {
        if ($this->cachedSyncSettings === null) {
            $this->cachedSyncSettings = SystemSetting::getSyncSettings();
        }
        return $this->cachedSyncSettings;
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

        // For headcode adapters with a limit, pass a special cursor so the adapter
        // can stop Phase 2 early instead of fetching all N tours first.
        // This avoids the post-fetch slice that still runs all API calls.
        if ($this->limit && $config->integration_type === 'headcode') {
            $cursorValue = "__limit:{$this->limit}__";
        }

        // Get mappings from database (using correct column names)
        $mappings = WholesalerFieldMapping::where('wholesaler_id', $this->wholesalerId)
            ->where('is_active', true)
            ->get()
            ->groupBy('section_name');

        // Parse aggregation_config for nested data structure paths
        $dataStructure = $config->aggregation_config ?? [];

        // Pagination loop: fetch all pages until no more data
        $paginationConfig = $config->auth_credentials['pagination'] ?? [];
        Log::info('SyncToursJob: Starting pagination fetch', [
            'wholesaler_id' => $this->wholesalerId,
            'pagination_type' => $paginationConfig['type'] ?? 'none',
            'initial_cursor' => $cursorValue,
            'sync_type' => $this->syncType,
        ]);
        
        $mappedTours = [];
        $totalRaw = 0;
        $page = 1;
        $maxPages = 100; // Safety limit to prevent infinite loops

        do {
            $result = $adapter->fetchTours($cursorValue);

            if (!$result->success) {
                throw new \Exception('Failed to fetch tours (page ' . $page . '): ' . $result->errorMessage);
            }

            $pageCount = count($result->tours);
            $totalRaw += $pageCount;

            Log::info('SyncToursJob: Fetched page', [
                'wholesaler_id' => $this->wholesalerId,
                'page' => $page,
                'tours_in_page' => $pageCount,
                'has_more' => $result->hasMore,
                'next_cursor' => $result->nextCursor,
                'current_page' => $result->currentPage,
                'last_page' => $result->lastPage,
            ]);

            // Map each tour
            foreach ($result->tours as $rawTour) {
                // Headcode adapters may return pre-mapped data (has 'tour' key already)
                // In that case skip transformTourData and use the structure directly
                if (isset($rawTour['tour'])) {
                    $transformed = $rawTour;
                } else {
                    $transformed = $this->transformTourData($rawTour, $mappings, $dataStructure);
                }

                $tourSection = $transformed['tour'] ?? [];
                if (!empty($tourSection['title']) || !empty($tourSection['tour_code']) || !empty($tourSection['wholesaler_tour_code'])) {
                    $mappedTours[] = $transformed;
                }
            }

            // Advance cursor for next page
            $cursorValue = $result->nextCursor;
            $page++;

            // Stop if no tours returned (safety: prevent empty-page loops)
            if ($pageCount === 0) {
                break;
            }

        } while ($result->shouldContinue() && $page <= $maxPages);

        if ($page > $maxPages) {
            Log::warning('SyncToursJob: Reached max pages limit', [
                'wholesaler_id' => $this->wholesalerId,
                'max_pages' => $maxPages,
                'total_raw' => $totalRaw,
            ]);
        }

        Log::info('SyncToursJob: All pages fetched', [
            'wholesaler_id' => $this->wholesalerId,
            'total_pages' => $page - 1,
            'raw_count' => $totalRaw,
            'mapped_count' => count($mappedTours),
        ]);

        // Update cursor with the last position
        if ($cursor) {
            $cursor->update([
                'cursor_value' => $cursorValue,
                'last_synced_at' => now(),
                'last_batch_count' => $totalRaw,
                'total_received' => $cursor->total_received + $totalRaw,
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
            if (!is_array($data)) return null;
            
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
            
            // Handle array notation like "periods[].tour_period[].period_flight[].flight_airline_name"
            if (strpos($path, '[]') !== false) {
                // Split only at the FIRST []. to handle nested arrays properly
                $pos = strpos($path, '[].');
                if ($pos !== false) {
                    $arrayKey = substr($path, 0, $pos);
                    $remainingPath = substr($path, $pos + 3); // Skip "[]."
                    
                    if (!isset($data[$arrayKey]) || !is_array($data[$arrayKey])) return null;
                    if (empty($data[$arrayKey])) return null;
                    
                    // Get first element from array
                    $firstItem = $data[$arrayKey][0] ?? null;
                    if (!$firstItem || !is_array($firstItem)) return null;
                    
                    // Recursively extract from remaining path
                    return $extractValue($firstItem, $remainingPath);
                } else {
                    // Path ends with [] (e.g., "periods[]") - return entire array
                    $arrayKey = rtrim($path, '[]');
                    return $data[$arrayKey] ?? null;
                }
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
                    
                    // If value is an array, skip lookup (can't lookup array values)
                    if (is_array($value)) {
                        Log::debug('SyncToursJob: lookup skipped - value is array', [
                            'our_field' => $mapping->our_field,
                        ]);
                        return null;
                    }
                    
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
                    
                    // FIX: ใช้ in-memory cache เพื่อไม่ต้อง query ซ้ำสำหรับค่าเดียวกัน
                    $cacheKey = "{$lookupTable}:{$lookupBy}:" . (string) $value;
                    if (array_key_exists($cacheKey, $this->lookupCache)) {
                        return $this->lookupCache[$cacheKey];
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
                        
                        // For transports - try code match first if value looks like an airline code (2-3 chars)
                        if ($lookupTable === 'transports') {
                            // If value is short (2-3 chars), it's likely an airline code
                            if (strlen($searchValue) <= 3 && preg_match('/^[A-Z0-9]{2,3}$/i', $searchValue)) {
                                $record = $modelClass::where('code', strtoupper($searchValue))
                                    ->orWhere('code1', strtoupper($searchValue))
                                    ->first();
                            }
                            
                            // Try to extract code from parentheses like "CHINA SOUTHERN AIRLINE (CZ)"
                            if (!$record && preg_match('/\(([A-Z0-9]{2,3})\)/', $searchValue, $matches)) {
                                $code = $matches[1];
                                $record = $modelClass::where('code', $code)
                                    ->orWhere('code1', $code)
                                    ->first();
                            }
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
                    
                    // FIX: เก็บ result ลง cache (รวมถึง null เพื่อไม่ query ซ้ำ)
                    $resultId = $record?->id;
                    $this->lookupCache[$cacheKey] = $resultId;
                    
                    return $resultId;
                    
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
                    
                case 'formula':
                    // Formula: คำนวณจากหลาย fields เช่น '{Price} - {Price_End}'
                    $stringTransform = $config['string_transform'] ?? [];
                    $expression = $stringTransform['formulaExpression'] ?? null;
                    if (!$expression) return $value;
                    $skipZero = ($stringTransform['formulaSkipZero'] ?? true) !== false;
                    
                    return $this->evaluateFormulaExpression($expression, $rawData, $skipZero);
                    
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
                
                // FIX: เปลี่ยน debug log จาก info → debug (ทุก tour ทุก field จะ log เยอะมาก)
                if (in_array($fieldName, ['primary_country_id', 'transport_id'])) {
                    Log::debug('SyncToursJob: Transform field', [
                        'field' => $fieldName,
                        'path' => $path,
                        'raw_value' => $value,
                        'transform_type' => $mapping->transform_type,
                    ]);
                }
                
                $value = $applyTransform($value, $mapping, $rawTour);
                
                if (in_array($fieldName, ['primary_country_id', 'transport_id'])) {
                    Log::debug('SyncToursJob: After transform', [
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
            $departureItems = $rawTour['Periods'] ?? $rawTour['periods'] ?? $rawTour['period'] ?? $rawTour['Period'] ?? $rawTour['Schedules'] ?? $rawTour['schedules'] ?? $rawTour['Departures'] ?? $rawTour['departures'] ?? [];
        }
        
        if (isset($mappings['departure']) && !empty($departureItems)) {
            foreach ($departureItems as $departureItem) {
                $dep = [];
                foreach ($mappings['departure'] as $mapping) {
                    $fieldName = $mapping->our_field;
                    $path = $mapping->their_field_path ?? $mapping->their_field ?? '';
                    
                    // Handle formula transform first - doesn't need fieldPath
                    if ($mapping->transform_type === 'formula') {
                        $config = $mapping->transform_config ?? [];
                        $stringTransform = ($config['string_transform'] ?? []);
                        $expression = $stringTransform['formulaExpression'] ?? null;
                        if ($expression) {
                            $skipZero = ($stringTransform['formulaSkipZero'] ?? true) !== false;
                            $formulaResult = $this->evaluateFormulaExpression($expression, $departureItem, $skipZero);
                            // Use 0 instead of null — if formula can't evaluate (skipZero), write 0 to overwrite old wrong values
                            $dep[$fieldName] = $formulaResult ?? 0;
                        }
                        continue;
                    }
                    
                    // Skip if no path defined (non-formula fields need a path)
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
            // Use custom nested path (e.g., "periods[].tour_daily[]")
            $itineraryItems = $this->flattenNestedPath($rawTour, $itinerariesPath);
            
            // Deduplicate: when path crosses multiple periods (e.g., periods[].tour_daily[]),
            // the same day appears once per period. Keep only unique days by day_num/day_number.
            if (!empty($itineraryItems) && count($itineraryItems) > 1) {
                $dayNumKey = null;
                // Auto-detect which key holds the day number from mappings
                if (isset($mappings['itinerary'])) {
                    foreach ($mappings['itinerary'] as $mapping) {
                        if ($mapping->our_field === 'day_number') {
                            $cleanPath = $this->cleanNestedPath($mapping->their_field_path ?? $mapping->their_field ?? '', $itinerariesPath);
                            $dayNumKey = $cleanPath;
                            break;
                        }
                    }
                }
                // Fallback keys for day number
                if (!$dayNumKey) {
                    foreach (['day_num', 'day_number', 'DayNumber', 'order', 'day_order'] as $candidate) {
                        if (isset($itineraryItems[0][$candidate])) {
                            $dayNumKey = $candidate;
                            break;
                        }
                    }
                }
                if ($dayNumKey) {
                    $seen = [];
                    $unique = [];
                    foreach ($itineraryItems as $item) {
                        $key = $item[$dayNumKey] ?? null;
                        if ($key !== null && isset($seen[$key])) {
                            continue; // Skip duplicate day
                        }
                        if ($key !== null) {
                            $seen[$key] = true;
                        }
                        $unique[] = $item;
                    }
                    $itineraryItems = $unique;
                }
            }
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
            
            // Auto-detect from mapping paths if standard candidates didn't match
            // e.g., mapping path "detail[].day_order" → extract key "detail" from rawTour
            if (empty($itineraryItems) && isset($mappings['itinerary'])) {
                foreach ($mappings['itinerary'] as $mapping) {
                    $path = $mapping->their_field_path ?? $mapping->their_field ?? '';
                    if (preg_match('/^(\w+)\[\]/', $path, $m)) {
                        $autoKey = $m[1];
                        if (isset($rawTour[$autoKey]) && is_array($rawTour[$autoKey])) {
                            $itineraryItems = $rawTour[$autoKey];
                            $itinerariesPath = $autoKey . '[]';
                            break;
                        }
                    }
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
                    
                    // Handle formula transform first - doesn't need fieldPath
                    if ($mapping->transform_type === 'formula') {
                        $config = $mapping->transform_config ?? [];
                        $stringTransform = ($config['string_transform'] ?? []);
                        $expression = $stringTransform['formulaExpression'] ?? null;
                        if ($expression) {
                            $skipZero = ($stringTransform['formulaSkipZero'] ?? true) !== false;
                            $formulaResult = $this->evaluateFormulaExpression($expression, $itineraryItem, $skipZero);
                            // Use 0 instead of null — if formula can't evaluate (skipZero), write 0 to overwrite old wrong values
                            $it[$fieldName] = $formulaResult ?? 0;
                        }
                        continue;
                    }
                    
                    // Skip if no path defined (non-formula fields need a path)
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
     * Also handles partial matches:
     * e.g., "periods[].tour_period[].period_id" with base "tour_period[]"
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
            // Method 1: Remove the full base path prefix
            // e.g., "periods[].tour_period[].period_id" with base "periods[].tour_period[]" → "period_id"
            $basePattern = preg_quote(rtrim($basePath, '[]') . '.', '/');
            $cleanPath = preg_replace('/^' . $basePattern . '/', '', $fullPath);
            
            // Also try with [] at the end
            $basePatternWithBracket = preg_quote($basePath . '.', '/');
            $cleanPath = preg_replace('/^' . $basePatternWithBracket . '/', '', $cleanPath);
            
            // Method 2: If base path is a partial match (e.g., "tour_period[]" vs full "periods[].tour_period[]")
            // Extract final segment and remove everything up to and including it
            // e.g., base = "tour_period[]", full = "periods[].tour_period[].period_id"
            //       We need to find "tour_period[]." in full path and remove everything before and including it
            if (strpos($fullPath, $basePath) !== false) {
                $pattern = '/^.*?' . preg_quote($basePath . '.', '/') . '/';
                $cleanPath = preg_replace($pattern, '', $fullPath);
            } elseif (strpos($fullPath, rtrim($basePath, '[]')) !== false) {
                // Try without the brackets
                $baseWithoutBrackets = rtrim($basePath, '[]');
                $pattern = '/^.*?' . preg_quote($baseWithoutBrackets, '/') . '\[\]\./';
                $cleanPath = preg_replace($pattern, '', $fullPath);
            }
            
            return $cleanPath;
        }
        
        // Default: remove standard prefixes (backwards compatibility)
        $cleanPath = preg_replace('/^[Pp]eriods?\[\]\./', '', $fullPath);
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
        Log::info('SyncToursJob::processTours: Starting', [
            'wholesaler_id' => $this->wholesalerId,
            'tours_count' => count($toursData),
        ]);
        
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

        // FIX: PdfBrandingService จะถูกสร้างใน ProcessTourMediaJob แทน (async)
        // ไม่ต้องสร้างตรงนี้แล้ว เก็บแค่ config ไว้ส่งต่อ
        $pdfBranding = null;
        $wholesalerCode = $config->wholesaler?->code ?? 'default';

        // Check if this is a single tour object or array of sections
        // If it has 'tour' key at top level, it's a single tour
        if (isset($toursData['tour'])) {
            $toursData = [$toursData]; // Wrap in array
        }

        // Process in chunks for better progress tracking
        $chunkSize = $syncLog->chunk_size ?? 50;
        $chunks = array_chunk($toursData, $chunkSize);
        $chunkIndex = 0;
        $processedCount = 0;
        $lastHeartbeat = time();

        // Pre-fetch bulk periods for two_phase mode with bulk endpoint (no placeholder)
        $bulkPeriodsMap = $this->prefetchBulkPeriods($config, $toursData);

        foreach ($chunks as $chunk) {
            $chunkIndex++;
            
            // FIX: Ensure DB connection is alive before each chunk
            $this->ensureDbConnection();
            
            // Update heartbeat with retry
            $this->safeDbOperation(function() use ($syncLog) {
                $syncLog->update(['last_heartbeat_at' => now()]);
            }, 'heartbeat update');
            
            // Check cancellation ทุก 5 chunks แทนทุก chunk (ลด DB reads)
            if ($chunkIndex % 5 === 1 || $chunkIndex === 1) {
                $this->safeDbOperation(function() use ($syncLog) {
                    $syncLog->refresh();
                }, 'refresh sync log');
                if ($syncLog->cancel_requested) {
                    Log::info('SyncToursJob: Cancellation requested, stopping', [
                        'sync_log_id' => $syncLog->id,
                        'processed' => $processedCount,
                        'total' => count($toursData),
                    ]);
                    break;
                }
            }

            foreach ($chunk as $tourData) {
                $stats['received']++;
                $processedCount++;

                // Get tour code for progress display
                $currentTourCode = $tourData['tour']['tour_code'] 
                    ?? $tourData['tour']['wholesaler_tour_code'] 
                    ?? $tourData['tour']['external_id'] 
                    ?? "item-{$processedCount}";

                try {
                    // Remove emojis from all text fields
                    $tourData = $this->removeEmojisFromArray($tourData);

                    // FIX: Mark media for async processing (no HTTP calls here)
                    $tourData = $this->processMediaBeforeTransaction($tourData, $config, $pdfBranding, $wholesalerCode);

                    // Inject bulk periods if available (two_phase + bulk endpoint)
                    if (!empty($bulkPeriodsMap)) {
                        $tourData = $this->injectBulkPeriods($tourData, $bulkPeriodsMap, $config);

                        // Skip tours with no matching periods in bulk mode
                        if (empty($tourData['departure'])) {
                            Log::debug('SyncToursJob: Skipping tour with no bulk periods', [
                                'tour_code' => $currentTourCode,
                            ]);
                            $stats['skipped']++;
                            continue;
                        }
                    }

                    DB::beginTransaction();

                    $result = $this->processSingleTour($tourData, $config, $pdfBranding, $wholesalerCode, $syncLog);
                    
                    // If newly created tour ended up with 0 active periods, rollback and skip.
                    // Cases covered:
                    // 1. API returned periods but all were past/skipped (hasDepartures=true, periodsCreated=0)
                    // 2. API returned 0 periods for this tour (hasDepartures=false) — single-phase only
                    // 3. two_phase inline mode processed but got 0 active periods
                    // Excluded: two_phase with queue dispatch (periods fetched later by SyncPeriodsJob)
                    $periodsReceived = $result['periods_received'] ?? 0;
                    $periodsCreated = $result['periods_created'] ?? 0;
                    $periodsUpdated = $result['periods_updated'] ?? 0;
                    $hasDepartures = !empty($tourData['departure']) || !empty($result['two_phase_inline']);
                    $syncMode = $config->sync_mode ?? 'single';
                    $isTwoPhaseQueued = $syncMode === 'two_phase' && empty($result['two_phase_inline']);
                    
                    if ($result['action'] === 'created' && $periodsCreated === 0 && $periodsUpdated === 0
                        && !$isTwoPhaseQueued) {
                        DB::rollBack();
                        Log::debug('SyncToursJob: Rollback new tour with no active periods', [
                            'tour_code' => $currentTourCode,
                            'periods_received' => $periodsReceived,
                            'has_departures' => $hasDepartures,
                            'reason' => $hasDepartures ? 'all_past_or_skipped' : 'api_returned_zero_periods',
                        ]);
                        $stats['skipped']++;
                        continue;
                    }

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

                    // FIX: Dispatch async media job AFTER successful DB commit
                    $pendingMedia = $tourData['_pending_media'] ?? [];
                    $tourId = $result['tour_id'] ?? null;
                    if ($tourId && ($pendingMedia['pdf_url'] || $pendingMedia['cover_image_url'])) {
                        ProcessTourMediaJob::dispatch(
                            $tourId,
                            $pendingMedia['pdf_url'],
                            $pendingMedia['cover_image_url'],
                            $wholesalerCode,
                            $config->pdf_header_image,
                            $config->pdf_header_height,
                            $config->pdf_footer_image,
                            $config->pdf_footer_height,
                            $pendingMedia['old_pdf_url'] ?? null,
                            $pendingMedia['old_cover_image_url'] ?? null,
                        );
                    }

                } catch (\Exception $e) {
                    DB::rollBack();
                    $stats['errors']++;

                    // Log error with proper type detection
                    $errorType = $this->categorizeError($e);
                    SyncErrorLog::create([
                        'sync_log_id' => $syncLog->id,
                        'wholesaler_id' => $this->wholesalerId,
                        'entity_type' => 'tour',
                        'entity_code' => $currentTourCode,
                        'error_type' => $errorType,
                        'error_message' => mb_substr($e->getMessage(), 0, 1000),
                        'raw_data' => $tourData,
                    ]);

                    Log::warning('SyncToursJob: Failed to process tour', [
                        'tour_code' => $currentTourCode,
                        'error_type' => $errorType,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Update progress after each item
                $totalItems = count($toursData);
                $percent = $totalItems > 0 ? round(($processedCount / $totalItems) * 100) : 0;
                
                // FIX: Heartbeat ทุก 30 วินาที หรือ ทุก 10 items (ค่าเดิม 60/50 ทำให้ auto-heal เข้าใจผิด)
                // Auto-heal threshold = 5 นาที → heartbeat ต้องถี่พอให้ไม่โดน false positive
                if (time() - $lastHeartbeat >= 30 || $processedCount % 10 === 0) {
                    $this->safeDbOperation(function() use ($syncLog, $processedCount, $percent, $currentTourCode) {
                        $syncLog->update([
                            'processed_items' => $processedCount,
                            'progress_percent' => $percent,
                            'current_item_code' => $currentTourCode,
                            'last_heartbeat_at' => now(),
                        ]);
                    }, 'progress update');
                    $lastHeartbeat = time();
                    
                    // Renew cache lock TTL เพื่อป้องกัน lock expire ก่อน job จบ
                    if ($this->syncLockKey) {
                        try {
                            Cache::lock($this->syncLockKey, 660)->forceRelease();
                            Cache::lock($this->syncLockKey, 660)->get();
                        } catch (\Exception $e) {
                            Log::warning('SyncToursJob: Lock renewal failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
            }
        }

        // Final heartbeat update
        $totalItems = count($toursData);
        $this->safeDbOperation(function() use ($syncLog, $processedCount, $totalItems) {
            $syncLog->update([
                'processed_items' => $processedCount,
                'progress_percent' => $totalItems > 0 ? round(($processedCount / $totalItems) * 100) : 100,
                'last_heartbeat_at' => now(),
            ]);
        }, 'final progress update');

        // FIX: PdfBranding cleanup ย้ายไป ProcessTourMediaJob แล้ว (async)

        return $stats;
    }

    /**
     * Process media (PDF + cover image) BEFORE database transaction
     * 
     * FIX: เปลี่ยนจาก synchronous upload เป็น deferred mode
     * - เก็บ original URLs ไว้ใน tour data เพื่อ save ลง DB ก่อน
     * - Dispatch ProcessTourMediaJob async หลัง DB commit เพื่อ upload ทีหลัง
     * - ลด blocking time จาก ~2-5 วินาทีต่อ tour เหลือ ~0 วินาที
     */
    protected function processMediaBeforeTransaction(
        array $tourData,
        WholesalerApiConfig $config,
        ?PdfBrandingService $pdfBranding,
        string $wholesalerCode
    ): array {
        $tourSection = $tourData['tour'] ?? [];
        $mediaSection = $tourData['media'] ?? [];
        
        // Merge media into tour section for URL access
        $merged = array_merge($tourSection, $mediaSection);

        // FIX: เก็บ original external URLs ไว้เพื่อ dispatch async job ทีหลัง
        // ตอนนี้ไม่ upload ตรงนี้แล้ว → ProcessTourMediaJob จะทำแทน
        $tourData['_pending_media'] = [
            'pdf_url' => null,
            'cover_image_url' => null,
            'old_pdf_url' => null,
            'old_cover_image_url' => null,
        ];

        // FIX: อ่าน old URLs จาก DB ก่อนที่จะถูกเขียนทับ เพื่อส่งให้ ProcessTourMediaJob ลบ
        $tourCode = $tourSection['tour_code'] ?? $tourSection['wholesaler_tour_code'] ?? $tourSection['external_id'] ?? null;
        if ($tourCode) {
            $existingTour = \App\Models\Tour::where('wholesaler_id', $config->wholesaler_id)
                ->where(function ($q) use ($tourCode, $tourSection) {
                    $q->where('wholesaler_tour_code', $tourCode)
                      ->orWhere('external_id', $tourSection['external_id'] ?? null);
                })
                ->first(['pdf_url', 'cover_image_url']);
            if ($existingTour) {
                $tourData['_pending_media']['old_pdf_url'] = $existingTour->pdf_url;
                $tourData['_pending_media']['old_cover_image_url'] = $existingTour->cover_image_url;
            }
        }

        $pdfUrl = $merged['pdf_url'] ?? null;
        if ($pdfUrl && str_starts_with($pdfUrl, 'http') && !str_contains($pdfUrl, env('R2_URL', ''))) {
            // Only dispatch media job if tour doesn't already have an R2 URL
            // (prevents re-uploading every sync when wholesaler sends same external URL)
            $existingPdf = $tourData['_pending_media']['old_pdf_url'] ?? null;
            $alreadyOnR2 = $existingPdf && str_contains($existingPdf, env('R2_URL', 'r2.dev'));
            if (!$alreadyOnR2) {
                $tourData['_pending_media']['pdf_url'] = $pdfUrl;
            }
        }

        $coverImageUrl = $merged['cover_image_url'] ?? null;
        if ($coverImageUrl && str_starts_with($coverImageUrl, 'http') && !str_contains($coverImageUrl, 'imagedelivery.net')) {
            // Only dispatch media job if tour doesn't already have a Cloudflare URL
            $existingCover = $tourData['_pending_media']['old_cover_image_url'] ?? null;
            $alreadyOnCf = $existingCover && str_contains($existingCover, 'imagedelivery.net');
            if (!$alreadyOnCf) {
                $tourData['_pending_media']['cover_image_url'] = $coverImageUrl;
            }
        }

        // Write back URLs — keep existing R2/Cloudflare URLs if media job was not dispatched
        $existingPdf = $tourData['_pending_media']['old_pdf_url'] ?? null;
        $existingCover = $tourData['_pending_media']['old_cover_image_url'] ?? null;
        $keepPdf = $existingPdf && str_contains($existingPdf, env('R2_URL', 'r2.dev')) && !$tourData['_pending_media']['pdf_url'];
        $keepCover = $existingCover && str_contains($existingCover, 'imagedelivery.net') && !$tourData['_pending_media']['cover_image_url'];

        $finalPdfUrl = $keepPdf ? $existingPdf : ($merged['pdf_url'] ?? null);
        $finalCoverUrl = $keepCover ? $existingCover : ($merged['cover_image_url'] ?? null);

        // FIX: ถ้า URL ยาวเกิน 500 chars (เช่น signed URL จาก iTravel)
        // ไม่ใส่ลง tour/media section → ป้องกัน "Data too long" error
        // ProcessTourMediaJob จะดาวน์โหลดแล้วเซ็ต URL ใหม่ (R2/Cloudflare) ที่สั้นกว่าทีหลัง
        $maxUrlLength = 500;

        if ($finalPdfUrl && strlen($finalPdfUrl) > $maxUrlLength) {
            Log::debug('SyncToursJob: pdf_url too long, will be set by ProcessTourMediaJob', [
                'tour_code' => $tourCode ?? 'unknown',
                'url_length' => strlen($finalPdfUrl),
            ]);
            // Ensure it's still in _pending_media for async download
            if (empty($tourData['_pending_media']['pdf_url'])) {
                $tourData['_pending_media']['pdf_url'] = $finalPdfUrl;
            }
            $finalPdfUrl = null; // Don't write to DB now
        }

        if ($finalCoverUrl && strlen($finalCoverUrl) > $maxUrlLength) {
            Log::debug('SyncToursJob: cover_image_url too long, will be set by ProcessTourMediaJob', [
                'tour_code' => $tourCode ?? 'unknown',
                'url_length' => strlen($finalCoverUrl),
            ]);
            if (empty($tourData['_pending_media']['cover_image_url'])) {
                $tourData['_pending_media']['cover_image_url'] = $finalCoverUrl;
            }
            $finalCoverUrl = null;
        }

        if (isset($tourData['tour'])) {
            if ($finalPdfUrl !== null) {
                $tourData['tour']['pdf_url'] = $finalPdfUrl;
            } else {
                // Remove long external URL from tour section to prevent DB error
                unset($tourData['tour']['pdf_url']);
            }
            if ($finalCoverUrl !== null) {
                $tourData['tour']['cover_image_url'] = $finalCoverUrl;
            } else {
                unset($tourData['tour']['cover_image_url']);
            }
        }
        if (isset($tourData['media'])) {
            if ($finalPdfUrl !== null) {
                $tourData['media']['pdf_url'] = $finalPdfUrl;
            } else {
                unset($tourData['media']['pdf_url']);
            }
            if ($finalCoverUrl !== null) {
                $tourData['media']['cover_image_url'] = $finalCoverUrl;
            } else {
                unset($tourData['media']['cover_image_url']);
            }
        }

        return $tourData;
    }

    /**
     * Pre-fetch all periods from bulk endpoint for two_phase mode.
     * Returns a map grouped by the match key, or empty array if not applicable.
     *
     * @return array<string, array> Map of matchKeyValue => periods[]
     */
    protected function prefetchBulkPeriods(WholesalerApiConfig $config, array $toursData): array
    {
        $syncMode = $config->sync_mode ?? 'single';
        if ($syncMode !== 'two_phase') {
            return [];
        }

        $credentials = $config->auth_credentials ?? [];
        $endpoints = $credentials['endpoints'] ?? [];
        $periodsEndpoint = $endpoints['periods'] ?? null;

        if (!$periodsEndpoint) {
            return [];
        }

        // Detect bulk endpoint: no {placeholder} in URL
        if (preg_match('/\{[^}]+\}/', $periodsEndpoint)) {
            return []; // Per-tour endpoint — handled by SyncPeriodsJob
        }

        $periodsMatchKey = $credentials['periods_match_key'] ?? null;
        if (!$periodsMatchKey) {
            Log::warning('SyncToursJob: Bulk periods endpoint detected but no periods_match_key configured', [
                'wholesaler_id' => $this->wholesalerId,
                'endpoint' => $periodsEndpoint,
            ]);
            return [];
        }

        Log::info('SyncToursJob: Fetching bulk periods', [
            'wholesaler_id' => $this->wholesalerId,
            'endpoint' => $periodsEndpoint,
            'match_key' => $periodsMatchKey,
        ]);

        try {
            $adapter = AdapterFactory::create($this->wholesalerId);
            $result = $adapter->fetchPeriods($periodsEndpoint);

            if (!$result->success || empty($result->periods)) {
                Log::warning('SyncToursJob: Bulk periods fetch failed or empty', [
                    'wholesaler_id' => $this->wholesalerId,
                    'error' => $result->error ?? 'empty',
                ]);
                return [];
            }

            // Group periods by match key value
            $grouped = collect($result->periods)->groupBy($periodsMatchKey)->toArray();

            Log::info('SyncToursJob: Bulk periods fetched', [
                'wholesaler_id' => $this->wholesalerId,
                'total_periods' => count($result->periods),
                'unique_keys' => count($grouped),
            ]);

            return $grouped;
        } catch (\Exception $e) {
            Log::error('SyncToursJob: Error fetching bulk periods', [
                'wholesaler_id' => $this->wholesalerId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Inject bulk periods into a tour's data by matching the tour's identifier
     * with the pre-fetched grouped periods.
     */
    protected function injectBulkPeriods(array $tourData, array $bulkPeriodsMap, WholesalerApiConfig $config): array
    {
        $credentials = $config->auth_credentials ?? [];
        $periodsMatchKey = $credentials['periods_match_key'] ?? null;
        $periodsTourKey = $credentials['periods_tour_key'] ?? null;

        if (!$periodsMatchKey) {
            return $tourData;
        }

        // Determine the tour's value to match against
        // Priority: periods_tour_key from config → common tour identifiers
        $tourSection = $tourData['tour'] ?? [];
        $matchValue = null;

        if ($periodsTourKey) {
            $matchValue = $tourSection[$periodsTourKey] ?? null;
        }

        // Fallback: try common identifiers
        if (!$matchValue) {
            $matchValue = $tourSection['external_id']
                ?? $tourSection['wholesaler_tour_code']
                ?? $tourSection['tour_code']
                ?? null;
        }

        if ($matchValue && isset($bulkPeriodsMap[$matchValue])) {
            // Transform raw periods using field mappings
            $rawPeriods = $bulkPeriodsMap[$matchValue];
            $wholesalerId = $config->wholesaler_id;

            $mappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
                ->where('section_name', 'departure')
                ->where('is_active', true)
                ->get();

            $aggConfig = $config->aggregation_config ?? [];
            $departuresPath = $aggConfig['data_structure']['departures']['path'] ?? null;

            $transformedPeriods = [];
            foreach ($rawPeriods as $rawPeriod) {
                $dep = [];
                foreach ($mappings as $mapping) {
                    $fieldName = $mapping->our_field;
                    $path = $mapping->their_field_path ?? $mapping->their_field ?? '';

                    if (empty($path)) continue;

                    // Clean the path (remove array prefix like periods[]. )
                    $cleanPath = $this->cleanNestedPath($path, $departuresPath);
                    $value = $this->extractNestedValue($rawPeriod, $cleanPath);

                    if ($value === null && !empty($mapping->default_value)) {
                        $value = $mapping->default_value;
                    }

                    $dep[$fieldName] = $value;
                }
                if (!empty($dep)) {
                    $transformedPeriods[] = $dep;
                }
            }

            if (!empty($transformedPeriods)) {
                $tourData['departure'] = $transformedPeriods;

                Log::debug('SyncToursJob: Injected bulk periods', [
                    'match_value' => $matchValue,
                    'periods_count' => count($transformedPeriods),
                ]);
            }
        }

        return $tourData;
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
            'tour_id' => null,
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

        // Media already processed in processMediaBeforeTransaction()
        // URLs in tourSection are already updated with R2/Cloudflare URLs

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
            
            // Skip disabled/closed tours if configured (from global settings)
            $globalSyncSettings = $this->getSyncSettings();
            if ($globalSyncSettings['skip_disabled_tours'] ?? true) {
                $skipStatuses = ['disabled', 'closed', 'inactive'];
                if (in_array($tour->status, $skipStatuses)) {
                    Log::info('SyncToursJob: Skipped disabled/closed tour', [
                        'tour_id' => $tour->id,
                        'tour_code' => $tour->tour_code,
                        'status' => $tour->status,
                    ]);
                    $result['action'] = 'skipped';
                    return $result;
                }
            }
            
            $result['action'] = 'updated';
        }

        // Fill tour fields from transformed data (no hardcode - use mapping result)
        // Filter only fillable fields and merge with existing values

        // FIX: เปลี่ยน debug log จาก info → debug (ไม่ต้อง log ทุก tour ใน production)
        Log::debug('SyncToursJob: tourSection before fill', [
            'tour_code' => $tourSection['tour_code'] ?? $tourSection['wholesaler_tour_code'] ?? 'N/A',
            'has_primary_country_id' => array_key_exists('primary_country_id', $tourSection),
            'primary_country_id' => $tourSection['primary_country_id'] ?? 'NOT_SET',
            'has_transport_id' => array_key_exists('transport_id', $tourSection),
            'transport_id' => $tourSection['transport_id'] ?? 'NOT_SET',
        ]);

        // Get Smart Sync settings from global SystemSetting (not per-integration)
        $syncSettings = $this->getSyncSettings();
        $respectManualOverrides = $syncSettings['respect_manual_overrides'] ?? true;
        $alwaysSyncFields = $syncSettings['always_sync_fields'] ?? ['cover_image_url', 'pdf_url', 'og_image_url', 'docx_url'];
        $neverSyncFields = $syncSettings['never_sync_fields'] ?? ['status'];
        
        // Get manually overridden fields from existing tour
        $manualOverrides = !$isNew ? ($tour->manual_override_fields ?? []) : [];
        
        $fillableFields = $tour->getFillable();
        $tourFields = [];
        $skippedFields = []; // Track skipped fields for logging
        
        // Fields that should be null when empty (numeric fields)
        $numericFields = ['hotel_star', 'duration_days', 'duration_nights', 'primary_country_id', 'transport_id'];
        
        // Fields that should be skipped if empty (will be auto-generated)
        $autoGeneratedFields = ['tour_code'];
        
        foreach ($tourSection as $field => $value) {
            // Skip null values to keep existing data
            if ($value === null) continue;
            
            // Skip empty auto-generated fields (tour_code will be generated below)
            if (empty($value) && in_array($field, $autoGeneratedFields)) {
                continue;
            }
            
            // NEVER sync these fields (e.g., status)
            if (in_array($field, $neverSyncFields)) {
                $skippedFields[$field] = 'never_sync';
                continue;
            }
            
            // Check if field is manually overridden (unless it's in always_sync_fields)
            if ($respectManualOverrides && !$isNew && !in_array($field, $alwaysSyncFields)) {
                if (isset($manualOverrides[$field])) {
                    $skippedFields[$field] = 'manual_override';
                    continue;
                }
            }
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
        if (empty($tour->tour_code) && empty($tourFields['tour_code'])) {
            $tourFields['tour_code'] = $this->generateTourCode($config->wholesaler_id);
        }
        
        // Parse Thai duration text like "10 วัน 8 คืน" → duration_days=10, duration_nights=8
        if (!empty($tourFields['duration_days']) && is_string($tourFields['duration_days']) && !is_numeric($tourFields['duration_days'])) {
            $durationText = $tourFields['duration_days'];
            $tourFields['duration_days'] = preg_match('/(\d+)\s*วัน/', $durationText, $dm) ? (int) $dm[1] : 0;
            if (preg_match('/(\d+)\s*คืน/', $durationText, $nm)) {
                $tourFields['duration_nights'] = (int) $nm[1];
            }
        }
        
        // Auto-calculate duration_nights from duration_days if not provided
        if (empty($tourFields['duration_nights']) && !empty($tourFields['duration_days'])) {
            $tourFields['duration_nights'] = max(0, (int)$tourFields['duration_days'] - 1);
        }
        
        // Set default duration_days for new tours (required by DB, no default value)
        if ($isNew && empty($tourFields['duration_days'])) {
            // Try to calculate from duration_nights, otherwise default to 0
            if (!empty($tourFields['duration_nights'])) {
                $tourFields['duration_days'] = (int)$tourFields['duration_nights'] + 1;
            } else {
                $tourFields['duration_days'] = 0;
            }
        }
        
        // Ensure duration_nights has a value for new tours
        if ($isNew && !isset($tourFields['duration_nights'])) {
            $tourFields['duration_nights'] = 0;
        }
        
        // Convert array fields to JSON string (highlights, hashtags, etc.)
        $jsonFields = ['highlights', 'hashtags', 'themes', 'suitable_for', 'departure_airports'];
        foreach ($jsonFields as $jsonField) {
            if (isset($tourFields[$jsonField]) && is_array($tourFields[$jsonField])) {
                $tourFields[$jsonField] = json_encode($tourFields[$jsonField], JSON_UNESCAPED_UNICODE);
            }
        }
        
        // Set sync metadata (system fields, not from mapping)
        $tourFields['sync_status'] = 'active';
        $tourFields['last_synced_at'] = now();
        
        // Truncate title to fit varchar(255)
        if (!empty($tourFields['title']) && mb_strlen($tourFields['title']) > 250) {
            $tourFields['title'] = mb_substr($tourFields['title'], 0, 247) . '...';
        }
        
        // Truncate SEO fields to fit varchar(255)
        foreach (['meta_title' => 200] as $seoField => $maxLen) {
            if (!empty($tourFields[$seoField]) && is_string($tourFields[$seoField]) && mb_strlen($tourFields[$seoField]) > $maxLen) {
                $tourFields[$seoField] = mb_substr($tourFields[$seoField], 0, $maxLen - 3) . '...';
            }
        }
        
        // FIX: เปลี่ยน debug log จาก info → debug
        Log::debug('SyncToursJob: tourFields before save', [
            'tour_code' => $tourFields['tour_code'] ?? $tour->tour_code ?? 'N/A',
            'has_external_id' => array_key_exists('external_id', $tourFields),
            'external_id' => $tourFields['external_id'] ?? 'NOT_IN_FIELDS',
            'has_primary_country_id' => array_key_exists('primary_country_id', $tourFields),
            'primary_country_id' => $tourFields['primary_country_id'] ?? 'NOT_IN_FIELDS',
            'has_transport_id' => array_key_exists('transport_id', $tourFields),
            'transport_id' => $tourFields['transport_id'] ?? 'NOT_IN_FIELDS',
            'skipped_fields' => $skippedFields,
            'is_new' => $isNew,
            'all_field_keys' => array_keys($tourFields),
        ]);
        
        // FIX: ถ้า tour ใหม่แต่ยังไม่มี external_id ให้ใช้ wholesaler_tour_code แทน
        if ($isNew && empty($tourFields['external_id']) && !empty($tour->wholesaler_tour_code)) {
            $tourFields['external_id'] = $tour->wholesaler_tour_code;
            Log::info('SyncToursJob: Auto-set external_id from wholesaler_tour_code', [
                'tour_code' => $tourFields['tour_code'] ?? $tour->tour_code ?? 'N/A',
                'external_id' => $tourFields['external_id'],
            ]);
        }
        
        // FIX: Validate tour_type against allowed enum values
        $allowedTourTypes = ['join', 'incentive', 'collective'];
        if (isset($tourFields['tour_type']) && !in_array($tourFields['tour_type'], $allowedTourTypes, true)) {
            unset($tourFields['tour_type']); // Let MySQL use default 'join'
        }
        
        $tour->fill($tourFields);
        
        // Retry on duplicate tour_code collision (concurrent sync jobs)
        $saved = false;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $tour->save();
                $saved = true;
                break;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($attempt < 4 && str_contains($e->getMessage(), 'tours_tour_code_unique')) {
                    $tour->tour_code = $this->generateTourCode($config->wholesaler_id);
                    continue;
                }
                throw $e;
            }
        }
        
        $result['tour_id'] = $tour->id;
        
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
        
        // Extract countries from tour title if enabled
        $tourTitle = $tour->title ?? $tour->name ?? null;
        if ($config->extract_countries_from_name && !empty($tourTitle)) {
            $extractedCountries = CountryExtractorService::extract($tourTitle);

            if ($extractedCountries->isNotEmpty()) {
                // Set primary_country_id from first extracted country if not already set
                if (empty($tour->primary_country_id)) {
                    $tour->update(['primary_country_id' => $extractedCountries->first()->id]);
                }

                // Sync to tour_countries pivot (keep existing, add new)
                $existingCountryIds = $tour->countries()->pluck('countries.id')->toArray();
                $sortOrder = $tour->countries()->max('tour_countries.sort_order') ?? 0;

                foreach ($extractedCountries as $country) {
                    if (!in_array($country->id, $existingCountryIds)) {
                        $sortOrder++;
                        $isPrimary = empty($tour->primary_country_id) || $tour->primary_country_id === $country->id;
                        $tour->countries()->attach($country->id, [
                            'is_primary' => $isPrimary,
                            'sort_order' => $sortOrder,
                        ]);
                    }
                }

                Log::info('SyncToursJob: Extracted countries from tour name', [
                    'tour_id' => $tour->id,
                    'tour_title' => $tourTitle,
                    'countries_found' => $extractedCountries->pluck('name_th')->toArray(),
                ]);
            }
        }

        // Extract cities from tour title if enabled
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
        
        // For two_phase mode: if we already have departures data (from Mass Sync detail fetch),
        // process them directly instead of dispatching SyncPeriodsJob
        $hasDeparturesData = !empty($departures);
        
        if ($syncMode === 'two_phase' && !$hasDeparturesData) {
            // Check if bulk periods mode is active — if so, skip SyncPeriodsJob dispatch
            // (no matching periods means this tour simply has no periods)
            $credentials = $config->auth_credentials ?? [];
            $periodsEndpoint = $credentials['endpoints']['periods'] ?? null;
            $isBulkPeriods = $periodsEndpoint && !preg_match('/\{[^}]+\}/', $periodsEndpoint) && !empty($credentials['periods_match_key']);

            if ($isBulkPeriods) {
                // Bulk mode: no matching periods found — skip, don't dispatch per-tour job
                Log::debug('SyncToursJob: No matching bulk periods for tour', [
                    'tour_id' => $tour->id,
                    'external_id' => $tour->external_id,
                ]);
                $result['periods_received'] = 0;
                $result['periods_created'] = 0;
                $result['periods_updated'] = 0;
            } else {
            // Two-Phase Sync: fetch periods separately per tour
            $externalId = $tour->external_id ?? $tour->wholesaler_tour_code;
            
            if ($externalId) {
                if ($this->processPeriodsInline) {
                    // Run inline (synchronously) — no queue worker needed
                    $isNewTour = $result['action'] === 'created';
                    try {
                        $periodsJob = new SyncPeriodsJob($tour->id, $externalId, $config->id, null, $isNewTour);
                        $periodsJob->runningInline = true;
                        $periodsJob->handle();
                        Log::info('SyncToursJob: Processed SyncPeriodsJob inline', [
                            'tour_id' => $tour->id,
                            'external_id' => $externalId,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('SyncToursJob: Inline SyncPeriodsJob failed', [
                            'tour_id' => $tour->id,
                            'external_id' => $externalId,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // After inline SyncPeriodsJob, check if tour was deleted (no active future periods)
                    // SyncPeriodsJob handles cleanup via DB::purge() separate connection,
                    // so we must check if tour still exists
                    $tourStillExists = Tour::where('id', $tour->id)->exists();

                    if (!$tourStillExists) {
                        // Tour was deleted by SyncPeriodsJob (all periods past/skipped)
                        $result['action'] = 'skipped';
                        $result['periods_received'] = 0;
                        $result['periods_created'] = 0;
                        $result['periods_updated'] = 0;
                        $result['two_phase_inline'] = false;
                    } else {
                        // Query actual period counts from DB
                        $activePeriodCount = Period::where('tour_id', $tour->id)
                            ->where('start_date', '>=', now()->toDateString())
                            ->whereIn('status', [Period::STATUS_OPEN, 'waitlist', 'sold_out'])
                            ->count();
                        $totalPeriodCount = Period::where('tour_id', $tour->id)->count();

                        $result['periods_received'] = $totalPeriodCount;
                        $result['periods_created'] = $activePeriodCount;
                        $result['periods_updated'] = 0;
                        // Mark as having departures so rollback check can trigger for two_phase
                        $result['two_phase_inline'] = true;
                    }
                } else {
                    // Dispatch to queue — SyncPeriodsJob will handle cleanup itself
                    SyncPeriodsJob::dispatch(
                        $tour->id,
                        $externalId,
                        $config->id,
                        $syncLog->id,
                        $result['action'] === 'created' // isNewTour flag
                    )->onQueue('periods');
                    
                    Log::info('SyncToursJob: Dispatched SyncPeriodsJob to queue', [
                        'tour_id' => $tour->id,
                        'external_id' => $externalId,
                    ]);

                    $result['periods_received'] = 0;
                    $result['periods_created'] = 0;
                    $result['periods_updated'] = 0;
                }
            } else {
                $result['periods_received'] = 0;
                $result['periods_created'] = 0;
                $result['periods_updated'] = 0;
            }
            } // end else (non-bulk per-tour dispatch)
        } else {
            // Single-Phase (default): process periods from same API response
            $result['periods_received'] = count($departures);
            $syncedExternalIds = [];
            
            foreach ($departures as $dep) {
                $periodResult = $this->processPeriod($tour, $dep, $config);
                if ($periodResult === 'created') {
                    $result['periods_created']++;
                } elseif ($periodResult === 'updated') {
                    $result['periods_updated']++;
                }
                
                // Track synced external_ids for orphan cleanup
                $extId = $dep['external_id'] ?? null;
                if ($extId) {
                    $syncedExternalIds[] = (string) $extId;
                }
            }
            
            // Cleanup orphan periods: close periods that exist in DB but not in API anymore
            // Only if we received periods from API (don't cleanup on empty response)
            if (!empty($syncedExternalIds) && count($departures) > 0) {
                $this->cleanupOrphanPeriods($tour, $syncedExternalIds, $config);
            }
        }

        // Process itineraries - check sync_mode
        // For two_phase mode: if we already have itineraries data (from Mass Sync detail fetch),
        // process them directly
        $hasItinerariesData = !empty($itineraries);
        
        if ($syncMode === 'two_phase' && !$hasItinerariesData) {
            // Two-Phase Sync: fetch itineraries from separate API endpoint
            $credentials = $config->auth_credentials ?? [];
            $endpoints = $credentials['endpoints'] ?? [];
            $itinerariesEndpoint = $endpoints['itineraries'] ?? null;
            
            if ($itinerariesEndpoint && ($tour->external_id || $tour->wholesaler_tour_code)) {
                $this->fetchAndSyncItineraries($tour, $config, $itinerariesEndpoint);
            }
        } else {
            // Single-Phase or has data: process itineraries from API response
            foreach ($itineraries as $itin) {
                $this->processItinerary($tour, $itin);
            }
        }

        // Recalculate aggregates (min_price, max_price, hotel_star_min/max, etc.)
        $tour->recalculateAggregates();

        return $result;
    }

    /**
     * Cleanup orphan periods: close/remove periods that exist in DB but are no longer in API
     * 
     * This handles the case where wholesaler removes periods from their system
     * but our DB still has them from a previous sync.
     * 
     * NOTE: This is different from "past_period_handling" which controls what happens
     * to periods whose departure date has passed. Orphan cleanup always runs because
     * these periods were actively removed by the wholesaler.
     */
    protected function cleanupOrphanPeriods(Tour $tour, array $syncedExternalIds, ?WholesalerApiConfig $config = null): void
    {
        // Find periods in DB that have external_id but were NOT in this sync batch
        $orphanPeriods = Period::where('tour_id', $tour->id)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->whereNotIn('external_id', $syncedExternalIds)
            ->where('status', '!=', Period::STATUS_CLOSED)
            ->where('status', '!=', Period::STATUS_CANCELLED)
            ->get();
        
        if ($orphanPeriods->isEmpty()) {
            return;
        }
        
        foreach ($orphanPeriods as $orphan) {
            $orphan->update([
                'status' => Period::STATUS_CLOSED,
            ]);
        }
        
        Log::info('SyncToursJob: Closed orphan periods not in API', [
            'tour_id' => $tour->id,
            'tour_code' => $tour->tour_code,
            'closed_count' => $orphanPeriods->count(),
            'closed_external_ids' => $orphanPeriods->pluck('external_id')->toArray(),
        ]);
    }

    /**
     * Process a single period/departure
     */
    protected function processPeriod(Tour $tour, array $depData, ?WholesalerApiConfig $config = null): string
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
        
        // Past period handling: use per-integration config (priority), fallback to global
        $pastPeriodHandling = $config?->past_period_handling ?? 'skip';
        $thresholdDays = (int) ($config?->past_period_threshold_days ?? 0);
        $thresholdDays = max(0, min(365, $thresholdDays));
        $thresholdDate = now()->subDays($thresholdDays)->toDateString();

        $isPastPeriod = date('Y-m-d', strtotime($departureDate)) < $thresholdDate;
        
        if ($isPastPeriod && $pastPeriodHandling === 'skip') {
            Log::debug('SyncToursJob: Skipped past period', [
                'tour_id' => $tour->id,
                'departure_date' => $departureDate,
                'threshold_date' => $thresholdDate,
                'handling' => 'skip',
            ]);
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
        
        // Numeric period fields that must be cast to integer (API may return strings like "26+1" or "ปิดกรุ๊ป")
        $numericPeriodFields = ['capacity', 'available', 'booked', 'price_adult', 'price_child', 'price_child_nobed', 'price_infant', 'price_single', 'price_joinland', 'commission_agent', 'commission_sale'];
        
        foreach ($depData as $field => $value) {
            if ($value === null) continue;
            
            // Cast numeric fields: handle strings like "26+1", "ปิดกรุ๊ป", "เต็ม" → int
            if (in_array($field, $numericPeriodFields) && !is_numeric($value)) {
                $value = (int) $value; // PHP casts "26+1"→26, "ปิดกรุ๊ป"→0, "เต็ม"→0
            }
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
        
        // Force status to 'closed' for past periods when handling = 'close'
        if ($isPastPeriod && $pastPeriodHandling === 'close') {
            $periodFields['status'] = Period::STATUS_CLOSED;
        }
        
        // Derive booked from capacity - available_from_api when booked is not mapped.
        // This ensures Period::boot() recalculates available correctly:
        //   available = capacity - booked = capacity - (capacity - available_api) = available_api
        // Without this, booked stays 0 and boot() would set available = capacity (wrong).
        if (!isset($periodFields['booked']) && isset($periodFields['capacity']) && isset($periodFields['available'])) {
            $periodFields['booked'] = max(0, (int) $periodFields['capacity'] - (int) $periodFields['available']);
        }
        
        // Capacity cannot be negative
        if (isset($periodFields['capacity']) && $periodFields['capacity'] < 0) {
            $periodFields['capacity'] = 0;
        }
        
        // Booked cannot be negative
        if (isset($periodFields['booked']) && $periodFields['booked'] < 0) {
            $periodFields['booked'] = 0;
        }
        
        // Available cannot be negative (DB column is unsigned)
        // Let Period::boot() recalculate from capacity - booked, but clamp the input too
        if (isset($periodFields['available']) && $periodFields['available'] < 0) {
            $periodFields['available'] = 0;
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

        // Handle nested array fields - convert to string
        // e.g., day_list: [{day_title, day_description}, ...] → concatenated string
        if (isset($itinData['description']) && is_array($itinData['description'])) {
            $descParts = [];
            foreach ($itinData['description'] as $item) {
                if (is_array($item)) {
                    // Extract day_description or day_title from nested structure
                    $text = $item['day_description'] ?? $item['description'] ?? $item['day_title'] ?? '';
                    if (!empty($text)) {
                        $descParts[] = $text;
                    }
                } elseif (is_string($item)) {
                    $descParts[] = $item;
                }
            }
            $itinData['description'] = implode("\n", $descParts);
        }

        // Find existing itinerary
        // Use both external_id + day_number when available to handle cases where
        // external_id is a tour-level code (same for all days, e.g. tour_code)
        $itinerary = TourItinerary::where('tour_id', $tour->id)
            ->where(function ($q) use ($dayNumber, $externalId) {
                if ($externalId && $dayNumber) {
                    $q->where('external_id', $externalId)->where('day_number', $dayNumber);
                } elseif ($externalId) {
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
        
        // Truncate title to fit varchar(255)
        if (!empty($itinFields['title']) && mb_strlen($itinFields['title']) > 250) {
            $itinFields['title'] = mb_substr($itinFields['title'], 0, 247) . '...';
        }
        
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
     * Categorize error to determine error type
     */
    protected function categorizeError(\Exception $e): string
    {
        $message = strtolower($e->getMessage());
        $class = get_class($e);

        // Database errors
        if (str_contains($class, 'QueryException') || str_contains($class, 'PDOException')) {
            return 'database';
        }

        // HTTP/API errors
        if (str_contains($class, 'RequestException') || str_contains($class, 'ConnectionException')) {
            return 'api';
        }

        // Rate limiting
        if (str_contains($message, 'rate limit') || str_contains($message, 'too many requests') || str_contains($message, '429')) {
            return 'rate_limit';
        }

        // Timeout
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'timeout';
        }

        // Validation
        if (str_contains($class, 'ValidationException') || str_contains($message, 'validation')) {
            return 'validation';
        }

        // Type casting
        if (str_contains($message, 'type') && (str_contains($message, 'cast') || str_contains($message, 'convert'))) {
            return 'type_cast';
        }

        return 'unknown';
    }

    /**
     * Generate unique tour code
     * Format: NT+YYYYMM+XXXX (e.g., NT2026010001)
     */
    protected function generateTourCode(int $wholesalerId): string
    {
        $prefix = 'NT';
        $yearMonth = now()->format('Ym'); // e.g., 202601
        $basePattern = $prefix . $yearMonth;
        $prefixLen = strlen($basePattern);
        
        // Use numeric MAX to find actual highest sequence
        // (string ORDER BY fails when sequence goes from 3 to 4+ digits)
        $maxSeq = (int) Tour::where('tour_code', 'like', "{$basePattern}%")
            ->selectRaw("MAX(CAST(SUBSTRING(tour_code, ?) AS UNSIGNED)) as max_seq", [$prefixLen + 1])
            ->value('max_seq');
        
        $nextSeq = $maxSeq + 1;
        $code = $basePattern . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        
        // Concurrency guard: if code already exists, skip ahead
        while (Tour::where('tour_code', $code)->exists()) {
            $nextSeq++;
            $code = $basePattern . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        }
        
        return $code;
    }

    /**
     * Fetch and sync itineraries from separate API endpoint (Two-Phase Sync)
     */
    protected function fetchAndSyncItineraries(Tour $tour, WholesalerApiConfig $config, string $itinerariesEndpoint): void
    {
        // Build URL - replace all placeholders dynamically from tour data
        $placeholders = [
            '{external_id}'          => $tour->external_id ?? '',
            '{tour_id}'              => $tour->external_id ?? '',
            '{id}'                   => $tour->external_id ?? '',
            '{series_id}'            => $tour->external_id ?? '',
            '{tour_code}'            => $tour->tour_code ?? '',
            '{wholesaler_tour_code}' => $tour->wholesaler_tour_code ?? '',
            '{code}'                 => $tour->wholesaler_tour_code ?? $tour->tour_code ?? '',
            '{slug}'                 => $tour->slug ?? '',
        ];
        $url = str_replace(array_keys($placeholders), array_values($placeholders), $itinerariesEndpoint);

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
     * Evaluate a formula expression like '{Price} - {Price_End}'
     * Replaces {FieldName} with actual values from data, then evaluates the math expression safely.
     * 
     * @param string $expression e.g. '{Price} - {Price_End}', '{Price} * 1.07'
     * @param array $data Raw data containing the field values
     * @return float|null Calculated result or null if evaluation fails
     */
    protected function evaluateFormulaExpression(string $expression, array $data, bool $skipZero = true): ?float
    {
        try {
            $expr = $expression;
            
            // Replace {FieldName} or {nested.field} with numeric values
            $expr = preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($data, $skipZero) {
                $fieldPath = $matches[1];
                $value = $this->extractValue($data, $fieldPath);
                // Also try direct key access for flat data
                if ($value === null && isset($data[$fieldPath])) {
                    $value = $data[$fieldPath];
                }
                if ($value === null || !is_numeric($value)) {
                    throw new \RuntimeException("Non-numeric value for field: {$fieldPath}");
                }
                $numericValue = (float) $value;
                // Skip if value is 0 and skipZero is enabled
                if ($skipZero && $numericValue == 0) {
                    throw new \RuntimeException("Field {$fieldPath} is 0, skipping formula (skipZero enabled)");
                }
                return (string) $numericValue;
            }, $expr);
            
            // Process max() and min() functions
            $expr = preg_replace_callback('/\b(max|min)\s*\(([^)]+)\)/i', function ($m) {
                $func = strtolower($m[1]);
                $nums = array_map('trim', explode(',', $m[2]));
                foreach ($nums as $n) {
                    if (!is_numeric($n)) return $m[0];
                }
                $nums = array_map('floatval', $nums);
                return (string) ($func === 'max' ? max($nums) : min($nums));
            }, $expr);
            
            // Security: only allow numbers, operators, parentheses, spaces, decimal points
            if (!preg_match('/^[\d\s+\-*\/().]+$/', trim($expr))) {
                Log::warning('evaluateFormulaExpression: Invalid expression after substitution', [
                    'original' => $expression,
                    'resolved' => $expr,
                ]);
                return null;
            }
            
            // Evaluate the expression
            $result = eval("return ({$expr});");
            
            if (!is_numeric($result) || !is_finite((float) $result)) {
                return null;
            }
            
            return round((float) $result, 2);
        } catch (\Exception $e) {
            Log::debug('evaluateFormulaExpression: Failed', [
                'expression' => $expression,
                'error' => $e->getMessage(),
            ]);
            return null;
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

    /**
     * Ensure database connection is alive, reconnect if needed
     * Handles "MySQL server has gone away" errors
     */
    protected function ensureDbConnection(): void
    {
        for ($attempt = 1; $attempt <= $this->dbMaxRetries; $attempt++) {
            try {
                // Test connection with simple query
                DB::connection()->getPdo();
                
                // Connection is alive
                return;
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $isConnectionError = str_contains($errorMessage, 'gone away') 
                    || str_contains($errorMessage, 'Lost connection')
                    || str_contains($errorMessage, 'actively refused')
                    || str_contains($errorMessage, 'Connection refused')
                    || str_contains($errorMessage, '2006')  // MySQL gone away
                    || str_contains($errorMessage, '2002'); // Connection refused
                
                if (!$isConnectionError) {
                    throw $e; // Unknown error, don't retry
                }
                
                Log::warning('SyncToursJob: DB connection lost, reconnecting', [
                    'attempt' => $attempt,
                    'max_retries' => $this->dbMaxRetries,
                    'error' => $errorMessage,
                ]);
                
                if ($attempt < $this->dbMaxRetries) {
                    sleep($this->dbRetryDelay * $attempt); // Progressive delay
                    
                    try {
                        // FIX: ใช้ purge() แทน disconnect()+reconnect() ป้องกัน connection leak
                        DB::purge();
                    } catch (\Exception $reconnectError) {
                        Log::error('SyncToursJob: Reconnect failed', [
                            'error' => $reconnectError->getMessage(),
                        ]);
                    }
                } else {
                    throw new \Exception("Failed to reconnect to database after {$this->dbMaxRetries} attempts: {$errorMessage}");
                }
            }
        }
    }

    /**
     * Execute a database operation with retry logic
     * 
     * @param callable $operation The database operation to execute
     * @param string $operationName Name for logging
     * @return mixed Result of the operation
     */
    protected function safeDbOperation(callable $operation, string $operationName = 'db operation')
    {
        for ($attempt = 1; $attempt <= $this->dbMaxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $isConnectionError = str_contains($errorMessage, 'gone away') 
                    || str_contains($errorMessage, 'Lost connection')
                    || str_contains($errorMessage, 'actively refused')
                    || str_contains($errorMessage, 'Connection refused')
                    || str_contains($errorMessage, '2006')
                    || str_contains($errorMessage, '2002');
                
                if (!$isConnectionError) {
                    throw $e; // Not a connection error, don't retry
                }
                
                Log::warning("SyncToursJob: {$operationName} failed, retrying", [
                    'attempt' => $attempt,
                    'max_retries' => $this->dbMaxRetries,
                    'error' => $errorMessage,
                ]);
                
                if ($attempt < $this->dbMaxRetries) {
                    sleep($this->dbRetryDelay * $attempt);
                    $this->ensureDbConnection();
                } else {
                    throw new \Exception("Failed {$operationName} after {$this->dbMaxRetries} attempts: {$errorMessage}");
                }
            }
        }
        
        return null;
    }
}
