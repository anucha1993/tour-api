<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SectionDefinition;
use App\Models\SyncCursor;
use App\Models\SyncLog;
use App\Models\Wholesaler;
use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use App\Services\WholesalerAdapters\AdapterFactory;
use App\Services\WholesalerAdapters\Mapper\SectionMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class IntegrationController extends Controller
{
    /**
     * List all integrations (wholesalers with API configs)
     */
    public function index(Request $request): JsonResponse
    {
        $query = WholesalerApiConfig::with('wholesaler')
            ->orderBy('created_at', 'desc');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $configs = $query->get();

        $integrations = $configs->map(function ($config) {
            $lastSync = SyncLog::where('wholesaler_id', $config->wholesaler_id)
                ->latest('started_at')
                ->first();

            $errorCount = SyncLog::where('wholesaler_id', $config->wholesaler_id)
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            return [
                'id' => $config->id,
                'wholesaler_id' => $config->wholesaler_id,
                'wholesaler_name' => $config->wholesaler?->name,
                'wholesaler_code' => $config->wholesaler?->code,
                'wholesaler_logo' => $config->wholesaler?->logo_url,
                'api_base_url' => $config->api_base_url,
                'api_format' => $config->api_format,
                'auth_type' => $config->auth_type,
                'is_active' => $config->is_active,
                'sync_enabled' => $config->sync_enabled,
                'sync_schedule' => $config->sync_schedule,
                'last_synced_at' => $lastSync?->started_at,
                'last_sync_status' => $lastSync?->status,
                'tours_count' => $lastSync?->tours_received ?? 0,
                'errors_count' => $errorCount,
                'health_status' => $config->health_status,
                'features' => [
                    'availability_check' => $config->supports_availability_check,
                    'hold_booking' => $config->supports_hold_booking,
                    'modify_booking' => $config->supports_modify_booking,
                ],
                'created_at' => $config->created_at,
                'updated_at' => $config->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $integrations,
        ]);
    }

    /**
     * Get single integration detail
     */
    public function show(int $id): JsonResponse
    {
        $config = WholesalerApiConfig::with('wholesaler')->findOrFail($id);
        $wholesalerId = $config->wholesaler_id;

        $mappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
            ->orderBy('section_name')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('section_name');

        $syncLogs = SyncLog::where('wholesaler_id', $wholesalerId)
            ->latest('started_at')
            ->limit(10)
            ->get();

        $cursor = SyncCursor::where('wholesaler_id', $wholesalerId)->first();

        // Get tour counts
        $toursCount = \App\Models\Tour::where('wholesaler_id', $wholesalerId)->count();
        
        // Get periods count through tours (Period doesn't have wholesaler_id directly)
        $tourIds = \App\Models\Tour::where('wholesaler_id', $wholesalerId)->pluck('id');
        $periodsCount = \App\Models\Period::whereIn('tour_id', $tourIds)->count();

        // Get last sync info
        $lastSync = SyncLog::where('wholesaler_id', $wholesalerId)
            ->where('status', '!=', 'running')
            ->latest('completed_at')
            ->first();

        // Calculate next sync time based on schedule (simple cron parser for common patterns)
        $nextSync = null;
        if ($config->sync_enabled && $config->sync_schedule && $lastSync) {
            // Simple parsing for common patterns like "0 */2 * * *" (every 2 hours)
            if (preg_match('/\*\/(\d+)/', $config->sync_schedule, $matches)) {
                $interval = (int) $matches[1];
                $nextSync = $lastSync->completed_at?->copy()->addHours($interval);
            }
        }

        // Get stats
        $stats = $this->calculateSyncStats($wholesalerId);

        // Get recent tours
        $recentTours = \App\Models\Tour::where('wholesaler_id', $wholesalerId)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get(['id', 'tour_code', 'title', 'sync_status', 'updated_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'config' => $config,
                'wholesaler' => $config->wholesaler,
                'mappings' => $mappings,
                'sync_logs' => $syncLogs,
                'cursor' => $cursor,
                // New fields
                'tours_count' => $toursCount,
                'periods_count' => $periodsCount,
                'last_sync' => $lastSync?->completed_at?->toDateTimeString(),
                'last_sync_status' => $lastSync?->status,
                'last_sync_duration' => $lastSync?->duration_seconds,
                'next_sync' => $nextSync?->toDateTimeString(),
                'stats' => $stats,
                'recent_tours' => $recentTours,
            ],
        ]);
    }

    /**
     * Calculate sync statistics for different time periods
     */
    private function calculateSyncStats(int $wholesalerId): array
    {
        $todayStart = now()->startOfDay();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        return [
            'today' => $this->getSyncStatsForPeriod($wholesalerId, $todayStart),
            'week' => $this->getSyncStatsForPeriod($wholesalerId, $weekStart),
            'month' => $this->getSyncStatsForPeriod($wholesalerId, $monthStart),
        ];
    }

    /**
     * Get sync stats for a specific period
     */
    private function getSyncStatsForPeriod(int $wholesalerId, $startDate): array
    {
        $logs = SyncLog::where('wholesaler_id', $wholesalerId)
            ->where('started_at', '>=', $startDate)
            ->get();

        return [
            'syncs' => $logs->count(),
            'tours_added' => $logs->sum('tours_created'),
            'tours_updated' => $logs->sum('tours_updated'),
            'errors' => $logs->sum('error_count'),
        ];
    }

    /**
     * Create new integration (API config for wholesaler)
     */
    public function store(Request $request): JsonResponse
    {
        // Debug: Log incoming data
        Log::info('Integration store request:', [
            'auth_type' => $request->auth_type,
            'auth_credentials' => $request->auth_credentials,
        ]);
        
        $validator = Validator::make($request->all(), [
            'wholesaler_id' => 'required|exists:wholesalers,id|unique:wholesaler_api_configs,wholesaler_id',
            'api_base_url' => 'required|url|max:500',
            'api_version' => 'nullable|string|max:20',
            'api_format' => 'nullable|in:rest,soap,graphql',
            'auth_type' => 'required|in:api_key,oauth2,basic,bearer,custom',
            'auth_credentials' => 'nullable|array',
            'auth_credentials.api_key' => 'required_if:auth_type,api_key',
            'auth_credentials.token' => 'required_if:auth_type,bearer',
            'auth_credentials.username' => 'required_if:auth_type,basic',
            'auth_credentials.password' => 'required_if:auth_type,basic',
            'auth_credentials.headers' => 'nullable|array', // For custom auth type
            'auth_credentials.headers.*' => 'nullable|string',
            'auth_credentials.token_url' => 'nullable|string|max:500', // For OAuth2
            'auth_credentials.oauth_fields' => 'nullable|array',
            'auth_credentials.response_token_field' => 'nullable|string|max:100',
            'auth_credentials.two_phase_sync' => 'nullable|boolean',
            'auth_credentials.periods_endpoint' => 'nullable|string|max:500',
            'auth_credentials.tour_id_field' => 'nullable|string|max:100',
            'auth_credentials.periods_field_name' => 'nullable|string|max:100',
            'rate_limit_per_minute' => 'nullable|integer|min:1|max:1000',
            'request_timeout_seconds' => 'nullable|integer|min:5|max:120',
            'sync_enabled' => 'nullable|boolean',
            'sync_method' => 'nullable|in:cursor,ack_callback,last_modified',
            'sync_schedule' => 'nullable|string|max:100',
            'sync_limit' => 'nullable|integer|min:1|max:1000',
            'supports_availability_check' => 'nullable|boolean',
            'supports_hold_booking' => 'nullable|boolean',
            'supports_modify_booking' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $config = WholesalerApiConfig::create($validator->validated());

            // Create default sync cursor
            SyncCursor::create([
                'wholesaler_id' => $config->wholesaler_id,
                'sync_type' => 'all',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Integration created successfully',
                'data' => $config->load('wholesaler'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create integration: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create integration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update integration
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'api_base_url' => 'nullable|url|max:500',
            'api_version' => 'nullable|string|max:20',
            'api_format' => 'nullable|in:rest,soap,graphql',
            'auth_type' => 'nullable|in:api_key,oauth2,basic,bearer,custom',
            'auth_credentials' => 'nullable|array',
            'auth_header_name' => 'nullable|string|max:100',
            'rate_limit_per_minute' => 'nullable|integer|min:1|max:1000',
            'rate_limit_per_day' => 'nullable|integer|min:1|max:100000',
            'connect_timeout_seconds' => 'nullable|integer|min:1|max:60',
            'request_timeout_seconds' => 'nullable|integer|min:5|max:120',
            'retry_attempts' => 'nullable|integer|min:0|max:10',
            'sync_enabled' => 'nullable|boolean',
            'sync_method' => 'nullable|in:cursor,ack_callback,last_modified',
            'sync_mode' => 'nullable|in:single,two_phase',
            'sync_schedule' => 'nullable|string|max:100',
            'sync_limit' => 'nullable|integer|min:1|max:1000',
            'full_sync_schedule' => 'nullable|string|max:100',
            'webhook_enabled' => 'nullable|boolean',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:255',
            'supports_availability_check' => 'nullable|boolean',
            'supports_hold_booking' => 'nullable|boolean',
            'supports_modify_booking' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            // Notification settings
            'notifications_enabled' => 'nullable|boolean',
            'notification_emails' => 'nullable|array',
            'notification_emails.*' => 'email',
            'notification_types' => 'nullable|array',
            // City extraction
            'extract_cities_from_name' => 'nullable|boolean',
            // Data structure config for nested arrays
            'aggregation_config' => 'nullable|array',
            'aggregation_config.data_structure' => 'nullable|array',
            'aggregation_config.data_structure.departures' => 'nullable|array',
            'aggregation_config.data_structure.departures.path' => 'nullable|string|max:255',
            'aggregation_config.data_structure.itineraries' => 'nullable|array',
            'aggregation_config.data_structure.itineraries.path' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $config->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Integration updated successfully',
            'data' => $config->fresh()->load('wholesaler'),
        ]);
    }

    /**
     * Delete integration
     */
    public function destroy(int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);
        
        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'Integration deleted successfully',
        ]);
    }

    /**
     * Test notification for an integration
     */
    public function testNotification(int $id): JsonResponse
    {
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $result = $notificationService->sendTestNotification($id);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            Log::error('testNotification error', [
                'integration_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'api_base_url' => 'required|url',
            'auth_type' => 'required|in:api_key,oauth2,basic,bearer,custom',
            'auth_credentials' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Create temporary config for testing
            $tempConfig = new WholesalerApiConfig([
                'wholesaler_id' => 0,
                'api_base_url' => $request->api_base_url,
                'api_format' => $request->api_format ?? 'rest',
                'auth_type' => $request->auth_type,
                'auth_credentials' => $request->auth_credentials,
                'connect_timeout_seconds' => 10,
                'request_timeout_seconds' => 30,
            ]);

            // Try to make a request
            $startTime = microtime(true);
            $client = \Illuminate\Support\Facades\Http::timeout(30)->connectTimeout(10);

            // Apply auth
            $credentials = $request->auth_credentials;
            
            // Handle OAuth2 - get token first
            if ($request->auth_type === 'oauth2') {
                $tokenUrl = $credentials['token_url'] ?? null;
                if (!$tokenUrl) {
                    return response()->json([
                        'success' => false,
                        'message' => 'OAuth2 token_url is required',
                    ], 422);
                }
                
                // Build OAuth2 request body from oauth_fields
                $oauthBody = [];
                $oauthFields = $credentials['oauth_fields'] ?? [];
                
                // Log incoming data for debugging
                Log::info('OAuth2 oauth_fields received:', ['oauth_fields' => $oauthFields, 'type' => gettype($oauthFields)]);
                
                // If oauth_fields is an array of {key, value} objects
                if (is_array($oauthFields)) {
                    foreach ($oauthFields as $field) {
                        if (is_array($field) && isset($field['key']) && isset($field['value'])) {
                            $oauthBody[$field['key']] = $field['value'];
                        }
                    }
                }
                
                // Fallback to old format
                if (empty($oauthBody)) {
                    $oauthBody = [
                        'grant_type' => $credentials['grant_type'] ?? 'client_credentials',
                        'client_id' => $credentials['client_id'] ?? '',
                        'client_secret' => $credentials['client_secret'] ?? '',
                    ];
                }
                
                Log::info('OAuth2 oauthBody built:', ['oauthBody' => $oauthBody]);
                
                try {
                    // Get token request headers if provided
                    $tokenHeaders = $credentials['token_headers'] ?? [];
                    
                    // Determine content type from headers (default to JSON)
                    $contentType = $tokenHeaders['Content-Type'] ?? $tokenHeaders['content-type'] ?? 'application/json';
                    $isFormData = stripos($contentType, 'form-urlencoded') !== false;
                    
                    // Build the HTTP client with custom headers
                    $tokenClient = \Illuminate\Support\Facades\Http::timeout(30);
                    
                    if (!empty($tokenHeaders)) {
                        $tokenClient = $tokenClient->withHeaders($tokenHeaders);
                    }
                    
                    // Send as JSON or form-data based on Content-Type
                    if ($isFormData) {
                        $tokenResponse = $tokenClient->asForm()->post($tokenUrl, $oauthBody);
                    } else {
                        $tokenResponse = $tokenClient->asJson()->post($tokenUrl, $oauthBody);
                    }
                    
                    // If first attempt fails with 4xx, try the other format
                    if ($tokenResponse->clientError()) {
                        $tokenClient2 = \Illuminate\Support\Facades\Http::timeout(30);
                        if (!empty($tokenHeaders)) {
                            $tokenClient2 = $tokenClient2->withHeaders($tokenHeaders);
                        }
                        
                        if ($isFormData) {
                            $tokenResponse = $tokenClient2->asJson()->post($tokenUrl, $oauthBody);
                        } else {
                            $tokenResponse = $tokenClient2->asForm()->post($tokenUrl, $oauthBody);
                        }
                    }
                    
                    if (!$tokenResponse->successful()) {
                        $timeMs = (int) ((microtime(true) - $startTime) * 1000);
                        return response()->json([
                            'success' => false,
                            'message' => 'OAuth2 token request failed',
                            'data' => [
                                'status_code' => $tokenResponse->status(),
                                'response_time_ms' => $timeMs,
                                'error' => $tokenResponse->body(),
                                'token_url' => $tokenUrl,
                            ],
                        ]);
                    }
                    
                    $tokenData = $tokenResponse->json();
                    
                    // Extract token from response (try common field names)
                    $responseTokenField = $credentials['response_token_field'] ?? 'access_token';
                    $accessToken = $tokenData[$responseTokenField] 
                        ?? $tokenData['access_token'] 
                        ?? $tokenData['token'] 
                        ?? $tokenData['accessToken']
                        ?? null;
                    
                    if (!$accessToken) {
                        $timeMs = (int) ((microtime(true) - $startTime) * 1000);
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot find access token in OAuth2 response',
                            'data' => [
                                'status_code' => $tokenResponse->status(),
                                'response_time_ms' => $timeMs,
                                'error' => 'Token field "' . $responseTokenField . '" not found in response',
                                'response_keys' => array_keys($tokenData),
                            ],
                        ]);
                    }
                    
                    // Use the token for API request
                    $client = $client->withToken($accessToken);
                    
                    // Add additional API headers if provided (user-defined, no defaults)
                    $apiHeaders = $credentials['api_headers'] ?? [];
                    if (!empty($apiHeaders)) {
                        $client = $client->withHeaders($apiHeaders);
                    }
                    
                } catch (\Exception $e) {
                    $timeMs = (int) ((microtime(true) - $startTime) * 1000);
                    return response()->json([
                        'success' => false,
                        'message' => 'OAuth2 token request error: ' . $e->getMessage(),
                        'data' => [
                            'response_time_ms' => $timeMs,
                            'token_url' => $tokenUrl,
                        ],
                    ]);
                }
            } else {
                // Non-OAuth2 auth types
                $client = match ($request->auth_type) {
                    'api_key' => $client->withHeaders(['Authorization' => $credentials['api_key'] ?? '']),
                    'bearer' => $client->withToken($credentials['token'] ?? ''),
                    'basic' => $client->withBasicAuth($credentials['username'] ?? '', $credentials['password'] ?? ''),
                    'custom' => $client->withHeaders($credentials['headers'] ?? []),
                    default => $client,
                };
            }

            // Make API request to the base URL directly (not to /health endpoints)
            $response = null;
            $testedUrl = $request->api_base_url;

            try {
                $response = $client->get($testedUrl);
            } catch (\Exception $e) {
                $timeMs = (int) ((microtime(true) - $startTime) * 1000);
                return response()->json([
                    'success' => false,
                    'message' => 'Connection error: ' . $e->getMessage(),
                    'data' => [
                        'response_time_ms' => $timeMs,
                    ],
                ]);
            }

            $timeMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response && $response->successful()) {
                $body = $response->json();
                $toursCount = count($body['data'] ?? $body['tours'] ?? $body['items'] ?? []);

                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful!',
                    'data' => [
                        'status_code' => $response->status(),
                        'response_time_ms' => $timeMs,
                        'tested_url' => $testedUrl,
                        'tours_found' => $toursCount,
                        'sample_response' => is_array($body) ? array_slice($body, 0, 5) : $body,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Connection failed',
                'data' => [
                    'status_code' => $response?->status() ?? 0,
                    'response_time_ms' => $timeMs,
                    'error' => $response?->body() ?? 'No response',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get section definitions (for mapping UI)
     */
    public function getSectionDefinitions(): JsonResponse
    {
        $sections = SectionDefinition::orderBy('section_name')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('section_name');

        return response()->json([
            'success' => true,
            'data' => $sections,
        ]);
    }

    /**
     * Get field mappings for an integration (by integration id, not wholesaler id)
     */
    public function getFieldMappings(int $wholesalerId): JsonResponse
    {
        // Check if this is integration ID or wholesaler ID
        // First try to find WholesalerApiConfig with this ID
        $config = WholesalerApiConfig::find($wholesalerId);
        $actualWholesalerId = $config ? $config->wholesaler_id : $wholesalerId;

        $mappings = WholesalerFieldMapping::where('wholesaler_id', $actualWholesalerId)
            ->orderBy('section_name')
            ->orderBy('sort_order')
            ->get();

        // Get enabled fields from config
        $enabledFields = $config?->enabled_fields ?? [];

        // Format mappings for frontend
        $formattedMappings = $mappings->map(function ($m) {
            // Determine source_type based on which field has value
            $sourceType = 'api'; // default
            if ($m->default_value !== null && $m->default_value !== '') {
                $sourceType = 'fixed';
            } elseif ($m->their_field || $m->their_field_path) {
                $sourceType = 'api';
            }
            
            return [
                'section' => $m->section_name,
                'our_field' => $m->our_field,
                'source_type' => $sourceType,
                'api_field' => $m->their_field_path ?? $m->their_field,
                'fixed_value' => $m->default_value,
                'lookup_by' => $m->transform_config['lookup_by'] ?? null,
                'value_map' => $m->transform_config['value_map'] ?? null,
                'string_transform' => $m->transform_config['string_transform'] ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'mappings' => $formattedMappings,
                'enabled_fields' => $enabledFields,
            ],
        ]);
    }

    /**
     * Save field mappings for an integration (by integration id)
     */
    public function saveFieldMappings(Request $request, int $wholesalerId): JsonResponse
    {
        // Check if this is integration ID or wholesaler ID
        $config = WholesalerApiConfig::find($wholesalerId);
        $actualWholesalerId = $config ? $config->wholesaler_id : $wholesalerId;

        $validator = Validator::make($request->all(), [
            'mappings' => 'required|array',
            'mappings.*.section' => 'required|string',
            'mappings.*.our_field' => 'required|string',
            'mappings.*.source_type' => 'required|in:api,fixed',
            'mappings.*.api_field' => 'nullable|string',
            'mappings.*.fixed_value' => 'nullable|string',
            'mappings.*.lookup_by' => 'nullable|string',
            'mappings.*.value_map' => 'nullable|array',
            'mappings.*.value_map.*.from' => 'required_with:mappings.*.value_map|string',
            'mappings.*.value_map.*.to' => 'required_with:mappings.*.value_map|string',
            'enabled_fields' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Delete existing mappings for this wholesaler
            WholesalerFieldMapping::where('wholesaler_id', $actualWholesalerId)->delete();

            // Create new mappings
            $sortOrder = 0;
            foreach ($request->mappings as $mapping) {
                $transformConfig = [];
                if (!empty($mapping['lookup_by'])) {
                    $transformConfig['lookup_by'] = $mapping['lookup_by'];
                }
                // เก็บ value_map สำหรับ transform enum/status values
                if (!empty($mapping['value_map'])) {
                    $transformConfig['value_map'] = $mapping['value_map'];
                }
                // เก็บ string_transform สำหรับ split/join/template
                if (!empty($mapping['string_transform'])) {
                    $transformConfig['string_transform'] = $mapping['string_transform'];
                }

                // Determine transform type - map to valid enum values:
                // 'direct', 'value_map', 'formula', 'split', 'concat', 'lookup', 'custom'
                $transformType = 'direct';
                if (!empty($mapping['string_transform']) && $mapping['string_transform']['type'] !== 'none') {
                    $stringTransformType = $mapping['string_transform']['type'];
                    // Map frontend types to database enum values
                    $typeMapping = [
                        'split' => 'split',      // split - direct match
                        'template' => 'concat',  // template uses concat
                        'replace' => 'custom',   // replace uses custom
                        'join' => 'split',       // join is part of split operation
                    ];
                    $transformType = $typeMapping[$stringTransformType] ?? 'custom';
                } elseif (!empty($mapping['value_map'])) {
                    $transformType = 'value_map';
                } elseif (!empty($mapping['lookup_by'])) {
                    $transformType = 'lookup';
                }

                WholesalerFieldMapping::create([
                    'wholesaler_id' => $actualWholesalerId,
                    'section_name' => $mapping['section'],
                    'our_field' => $mapping['our_field'],
                    'their_field' => $mapping['source_type'] === 'api' ? $mapping['api_field'] : null,
                    'their_field_path' => $mapping['source_type'] === 'api' ? $mapping['api_field'] : null,
                    'transform_type' => $transformType,
                    'transform_config' => !empty($transformConfig) ? $transformConfig : null,
                    'default_value' => $mapping['source_type'] === 'fixed' ? $mapping['fixed_value'] : null,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ]);
            }

            // Save enabled fields to config
            if ($config && $request->has('enabled_fields')) {
                $config->update([
                    'enabled_fields' => $request->enabled_fields,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Field mappings saved successfully',
                'data' => [
                    'count' => count($request->mappings),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save field mappings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save field mappings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview mapping with sample data
     */
    public function previewMapping(Request $request, int $wholesalerId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sample_data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $mapper = new SectionMapper();
            $result = $mapper->preview($request->sample_data, $wholesalerId);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Preview failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check tour count from wholesaler API
     * Fetches data from API and counts available tours
     * Supports both Single-Phase and Two-Phase sync modes
     * 
     * @param int $id Integration ID (WholesalerApiConfig primary key)
     */
    public function checkTourCount(int $id): JsonResponse
    {
        try {
            // Get wholesaler config by integration ID (primary key)
            $config = WholesalerApiConfig::find($id);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบการตั้งค่า API สำหรับ Integration นี้',
                ], 404);
            }

            // Get wholesaler_id from config
            $wholesalerId = $config->wholesaler_id;

            $wholesaler = Wholesaler::find($wholesalerId);
            if (!$wholesaler) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบ Wholesaler',
                ], 404);
            }

            // Create adapter and fetch data
            $adapter = AdapterFactory::create($wholesalerId);
            
            $startTime = microtime(true);
            $syncResult = $adapter->fetchTours(null);
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$syncResult->success) {
                return response()->json([
                    'success' => false,
                    'message' => 'เชื่อมต่อ API ไม่สำเร็จ: ' . ($syncResult->errorMessage ?? 'Unknown error'),
                    'data' => [
                        'response_time_ms' => $responseTimeMs,
                    ],
                ], 400);
            }

            $tours = $syncResult->tours;
            $tourCount = count($tours);

            // Check if Two-Phase sync is enabled
            $credentials = $config->auth_credentials ?? [];
            $isTwoPhase = ($config->sync_mode === 'two_phase') || !empty($credentials['two_phase_sync']);
            $periodsEndpoint = $credentials['endpoints']['periods'] 
                ?? $credentials['periods_endpoint'] 
                ?? null;

            // ดึง mapping เพื่อหา field names ที่ใช้
            $mappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
                ->where('is_active', true)
                ->get();
            
            // หา field path สำหรับ departure และ country จาก mapping
            $departureArrayPath = null;
            $countryFieldPath = null;
            
            foreach ($mappings as $mapping) {
                $path = $mapping->their_field_path ?? $mapping->their_field;
                
                // หา departure array path (เช่น "Periods[]" หรือ "departures[]")
                if ($mapping->section_name === 'departure' && $path && strpos($path, '[]') !== false) {
                    // Extract array name before []
                    $parts = explode('[]', $path);
                    if (!$departureArrayPath && !empty($parts[0])) {
                        $departureArrayPath = $parts[0];
                    }
                }
                
                // หา country field path (เช่น "CountryName" หรือ "country")
                if ($mapping->section_name === 'tour' && $mapping->our_field === 'primary_country_id' && $path) {
                    $countryFieldPath = $path;
                }
            }

            // นับทัวร์ที่มี departures
            $toursWithDepartures = 0;
            $totalDepartures = 0;
            $countries = [];
            $samplePeriodsCount = 0; // สำหรับ Two-Phase
            $twoPhaseNote = null;

            // Helper function: Extract value from nested path
            $extractValue = function($data, $path) {
                if (empty($path)) return null;
                $keys = explode('.', $path);
                $value = $data;
                foreach ($keys as $key) {
                    if (!is_array($value) || !isset($value[$key])) return null;
                    $value = $value[$key];
                }
                return $value;
            };

            // Two-Phase Sync: ดึง periods จาก API แยกสำหรับ sample tours
            if ($isTwoPhase && $periodsEndpoint && $adapter instanceof \App\Services\WholesalerAdapters\Adapters\GenericRestAdapter) {
                // Sample 3 tours เพื่อประมาณจำนวน periods
                $sampleTours = array_slice($tours, 0, 3);
                $totalSamplePeriods = 0;
                $sampledCount = 0;
                
                foreach ($sampleTours as $sampleTour) {
                    // Build the periods endpoint URL
                    $endpoint = $periodsEndpoint;
                    
                    if (preg_match_all('/\{([^}]+)\}/', $periodsEndpoint, $matches)) {
                        foreach ($matches[1] as $fieldName) {
                            $value = $sampleTour[$fieldName] ?? null;
                            if ($value === null) {
                                $value = $sampleTour['id'] ?? $sampleTour['tour_id'] ?? $sampleTour['external_id'] ?? $sampleTour['code'] ?? null;
                            }
                            if ($value !== null) {
                                $endpoint = str_replace('{' . $fieldName . '}', $value, $endpoint);
                            }
                        }
                    }
                    
                    // Check if all placeholders were replaced
                    if (!preg_match('/\{[^}]+\}/', $endpoint)) {
                        try {
                            $periodsResult = $adapter->fetchPeriods($endpoint);
                            if ($periodsResult->success && !empty($periodsResult->periods)) {
                                $totalSamplePeriods += count($periodsResult->periods);
                                $sampledCount++;
                                $toursWithDepartures++;
                            }
                        } catch (\Exception $e) {
                            Log::warning('checkTourCount: Failed to fetch periods', [
                                'endpoint' => $endpoint,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                
                // ประมาณจำนวน periods ทั้งหมดจาก sample
                if ($sampledCount > 0) {
                    $avgPeriodsPerTour = $totalSamplePeriods / $sampledCount;
                    $totalDepartures = (int) round($avgPeriodsPerTour * $tourCount);
                    $samplePeriodsCount = $totalSamplePeriods;
                    $twoPhaseNote = "ประมาณจาก sample {$sampledCount} ทัวร์ (พบ {$totalSamplePeriods} periods, เฉลี่ย " . round($avgPeriodsPerTour, 1) . " periods/tour)";
                } else {
                    $twoPhaseNote = "ไม่สามารถดึง periods จาก API แยกได้";
                }
            } else {
                // Single-Phase: นับ departures จาก response โดยตรง
                foreach ($tours as $tour) {
                    // ใช้ mapping path หรือ fallback to common names
                    $departures = [];
                    if ($departureArrayPath && isset($tour[$departureArrayPath])) {
                        $departures = $tour[$departureArrayPath];
                    } else {
                        // Fallback: ลองหลาย field names ที่เป็นไปได้
                        $departures = $tour['Periods'] ?? $tour['departures'] ?? $tour['programdepartures'] ?? $tour['schedules'] ?? [];
                    }
                    
                    if (is_array($departures) && count($departures) > 0) {
                        $toursWithDepartures++;
                        $totalDepartures += count($departures);
                    }
                }
            }

            // รวบรวมประเทศ (ทำทั้ง single และ two-phase)
            foreach ($tours as $tour) {
                $country = null;
                if ($countryFieldPath) {
                    $country = $extractValue($tour, $countryFieldPath);
                }
                if (!$country) {
                    // Fallback: ลองหลาย field names ที่เป็นไปได้
                    $country = $tour['CountryName'] ?? $tour['country'] ?? $tour['destination'] ?? $tour['region'] ?? null;
                }
                
                if ($country && !in_array($country, $countries)) {
                    $countries[] = $country;
                }
            }

            $responseData = [
                'tour_count' => $tourCount,
                'tours_with_departures' => $toursWithDepartures,
                'total_departures' => $totalDepartures,
                'countries' => array_slice($countries, 0, 10), // แสดงแค่ 10 ประเทศแรก
                'countries_count' => count($countries),
                'response_time_ms' => $responseTimeMs,
                'api_url' => $config->api_base_url,
                'wholesaler_name' => $wholesaler->name,
                'sync_mode' => $isTwoPhase ? 'two_phase' : 'single',
            ];
            
            // เพิ่มข้อมูล Two-Phase
            if ($isTwoPhase) {
                $responseData['two_phase_info'] = [
                    'periods_endpoint' => $periodsEndpoint,
                    'sample_periods_count' => $samplePeriodsCount,
                    'note' => $twoPhaseNote,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => "พบทัวร์ทั้งหมด {$tourCount} ทัวร์" . ($isTwoPhase ? ' (Two-Phase Sync)' : ''),
                'data' => $responseData,
            ]);

        } catch (\Exception $e) {
            Log::error('Check tour count failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test mapping with dry run (validate without saving)
     * Simulates sync process and reports any issues
     * 
     * @param int $id Integration ID (WholesalerApiConfig primary key)
     */
    public function testMapping(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sample_data' => 'required|array',
            'transformed_data' => 'required|array',
            'enabled_fields' => 'nullable|array', // fields ที่เปิดใช้งาน
            'mappings' => 'nullable|array', // mapping info สำหรับแสดง api_field
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $results = [
            'success' => true,
            'summary' => [
                'tours' => 0,
                'departures' => 0,
                'itineraries' => 0,
                'errors' => 0,
                'warnings' => 0,
            ],
            'validations' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            $transformedData = $request->transformed_data;
            $enabledFields = $request->enabled_fields ?? []; // fields ที่เปิดใช้งาน
            $mappingsData = $request->mappings ?? []; // mapping info
            
            // สร้าง lookup map สำหรับ mapping info
            // key = "section.field", value = ['api_field' => ..., 'source_type' => ...]
            $mappingsLookup = [];
            foreach ($mappingsData as $m) {
                $key = ($m['section'] ?? '') . '.' . ($m['our_field'] ?? '');
                $mappingsLookup[$key] = [
                    'api_field' => $m['api_field'] ?? null,
                    'source_type' => $m['source_type'] ?? 'api',
                    'fixed_value' => $m['fixed_value'] ?? null,
                ];
            }
            
            // Helper function: ตรวจสอบว่า field นี้เปิดใช้งานหรือไม่
            $isFieldEnabled = function($section, $key) use ($enabledFields) {
                if (empty($enabledFields)) return true; // ถ้าไม่ส่งมา = ตรวจสอบทั้งหมด
                return in_array("{$section}.{$key}", $enabledFields);
            };
            
            // Helper function: ดึง mapping info
            $getMappingInfo = function($section, $key) use ($mappingsLookup) {
                $lookupKey = "{$section}.{$key}";
                return $mappingsLookup[$lookupKey] ?? ['api_field' => null, 'source_type' => null];
            };
            
            // === Validate Tour Section ===
            // Field names must match ALL_FIELDS in frontend:
            // title, tour_code, primary_country_id, duration_days, duration_nights
            $tourSection = $transformedData['tour'] ?? [];
            
            // รวบรวม fields ที่เปิดใช้งานใน tour section
            $tourFieldsAll = ['external_id', 'tour_code', 'wholesaler_tour_code', 'title', 'tour_type', 'duration_days', 'duration_nights', 'primary_country_id', 'region', 'transport_id', 'hotel_star', 'tour_category', 'themes', 'suitable_for'];
            $tourEnabledFields = [];
            $tourTestedFields = [];
            
            foreach ($tourFieldsAll as $tf) {
                if ($isFieldEnabled('tour', $tf)) {
                    $mapInfo = $getMappingInfo('tour', $tf);
                    
                    // ข้าม fields ที่ไม่ได้ map (ไม่มี api_field และไม่ใช่ fixed)
                    $hasMapping = !empty($mapInfo['api_field']) || $mapInfo['source_type'] === 'fixed';
                    if (!$hasMapping) {
                        continue; // ไม่แสดง field ที่ไม่ได้ map
                    }
                    
                    $tourEnabledFields[] = $tf;
                    $value = $tourSection[$tf] ?? null;
                    $hasValue = isset($tourSection[$tf]) && $tourSection[$tf] !== '' && $tourSection[$tf] !== null;
                    $tourTestedFields[] = [
                        'field' => $tf,
                        'api_field' => $mapInfo['api_field'],
                        'source_type' => $mapInfo['source_type'],
                        'mapped_value' => $value,
                        'has_value' => $hasValue,
                        'status' => $hasValue ? 'ok' : 'empty',
                    ];
                }
            }
            
            $tourValidation = [
                'section' => 'tour',
                'status' => 'success',
                'message' => '',
                'enabled_count' => count($tourEnabledFields),
                'tested_fields' => $tourTestedFields,
                'fields' => [],
            ];

            // Required field: title (ตรวจสอบเฉพาะถ้าเปิดใช้งาน)
            if ($isFieldEnabled('tour', 'title')) {
                if (empty($tourSection['title'])) {
                    $tourValidation['status'] = 'error';
                    $tourValidation['message'] = 'Missing required field: title';
                    $results['errors'][] = [
                        'section' => 'tour',
                        'type' => 'required_field',
                        'message' => 'ต้องระบุ: title (ชื่อทัวร์)',
                    ];
                    $results['summary']['errors']++;
                } else {
                    $tourValidation['status'] = 'success';
                    $tourValidation['message'] = 'Tour data valid';
                    $results['summary']['tours'] = 1;
                }
            } else {
                // title ไม่ได้เปิดใช้งาน ถือว่าผ่าน
                $tourValidation['status'] = 'success';
                $tourValidation['message'] = 'Tour data valid (title disabled)';
                $results['summary']['tours'] = 1;
            }

            // Check recommended fields - เฉพาะที่เปิดใช้งาน
            $recommendedFields = [
                'primary_country_id' => 'primary_country_id (ประเทศหลัก)',
                'duration_days' => 'duration_days (จำนวนวัน)',
                'duration_nights' => 'duration_nights (จำนวนคืน)',
            ];
            
            foreach ($recommendedFields as $field => $label) {
                // ข้ามถ้า field นี้ไม่ได้เปิดใช้งาน
                if (!$isFieldEnabled('tour', $field)) {
                    continue;
                }
                
                // ใช้ isset + !== '' แทน empty() เพราะ empty("0") === true ใน PHP
                $hasValue = isset($tourSection[$field]) && $tourSection[$field] !== '' && $tourSection[$field] !== null;
                if (!$hasValue) {
                    $results['warnings'][] = [
                        'section' => 'tour',
                        'type' => 'missing_field',
                        'message' => "แนะนำให้ระบุ: {$label}",
                    ];
                    $results['summary']['warnings']++;
                }
            }
            
            $tourValidation['fields'] = [
                'title' => $tourSection['title'] ?? null,
                'tour_code' => $tourSection['tour_code'] ?? '(auto generate)',
                'primary_country_id' => $tourSection['primary_country_id'] ?? null,
                'duration_days' => $tourSection['duration_days'] ?? null,
                'duration_nights' => $tourSection['duration_nights'] ?? null,
            ];
            $results['validations'][] = $tourValidation;

            // === Validate Departure Section ===
            $departures = $transformedData['departure'] ?? [];
            
            // กำหนด departureFields ก่อนใช้งาน
            $departureFields = ['external_id', 'departure_date', 'return_date', 'capacity', 'available_seats', 'status', 'guarantee_status', 'currency', 'price_adult', 'discount_adult', 'price_child', 'discount_child_bed', 'price_child_nobed', 'discount_child_nobed', 'price_infant', 'price_joinland', 'price_single', 'discount_single', 'deposit', 'commission_agent', 'commission_sale', 'cancellation_policy', 'refund_policy', 'notes', 'ttl_minutes'];
            
            // รวบรวม fields ที่เปิดใช้งานใน departure section พร้อม mapping info
            $departureEnabledFields = [];
            $departureTestedFields = [];
            foreach ($departureFields as $df) {
                if ($isFieldEnabled('departure', $df)) {
                    $mapInfo = $getMappingInfo('departure', $df);
                    
                    // ข้าม fields ที่ไม่ได้ map
                    $hasMapping = !empty($mapInfo['api_field']) || $mapInfo['source_type'] === 'fixed';
                    if (!$hasMapping) {
                        continue;
                    }
                    
                    $departureEnabledFields[] = $df;
                    $departureTestedFields[] = [
                        'field' => $df,
                        'api_field' => $mapInfo['api_field'],
                        'source_type' => $mapInfo['source_type'],
                        'mapped_value' => null, // จะ fill จาก item แรก
                        'status' => 'pending',
                    ];
                }
            }
            
            $departureValidation = [
                'section' => 'departure',
                'status' => 'success',
                'message' => '',
                'count' => count($departures),
                'enabled_count' => count($departureEnabledFields),
                'enabled_fields' => $departureEnabledFields,
                'tested_fields' => $departureTestedFields, // แสดง mapping ที่ระดับ section
                'items' => [],
            ];

            // ตรวจสอบว่า departure section มี field ใดเปิดใช้งานบ้าง
            $hasDepartureFieldsEnabled = count($departureEnabledFields) > 0;
            
            if (!$hasDepartureFieldsEnabled) {
                // ไม่มี departure field ใดเปิดใช้งาน ข้ามไป
                $departureValidation['message'] = 'Departure section disabled';
            } elseif (empty($departures)) {
                $results['warnings'][] = [
                    'section' => 'departure',
                    'type' => 'empty',
                    'message' => 'ไม่มีข้อมูลรอบเดินทาง (departures)',
                ];
                $results['summary']['warnings']++;
            } else {
                foreach ($departures as $i => $dep) {
                    $depItem = [
                        'index' => $i + 1,
                        'status' => 'success',
                        'issues' => [],
                    ];

                    // Required: departure_date (ถ้าเปิดใช้งาน)
                    if ($isFieldEnabled('departure', 'departure_date') && empty($dep['departure_date'])) {
                        $depItem['status'] = 'error';
                        $depItem['issues'][] = 'ต้องระบุ departure_date';
                        $results['summary']['errors']++;
                    }

                    // Validate date format (ถ้าเปิดใช้งานและมีค่า)
                    if ($isFieldEnabled('departure', 'departure_date') && !empty($dep['departure_date'])) {
                        $date = \DateTime::createFromFormat('Y-m-d', $dep['departure_date']);
                        if (!$date) {
                            $depItem['status'] = 'warning';
                            $depItem['issues'][] = 'รูปแบบวันที่ควรเป็น YYYY-MM-DD';
                            $results['summary']['warnings']++;
                        }
                    }

                    // Check price (ถ้าเปิดใช้งาน)
                    if ($isFieldEnabled('departure', 'price_adult') && (empty($dep['price_adult']) || $dep['price_adult'] <= 0)) {
                        $depItem['issues'][] = 'แนะนำให้ระบุ price_adult';
                        $results['summary']['warnings']++;
                    }

                    // สร้างรายละเอียด fields ที่ทดสอบ
                    $depTestedFields = [];
                    foreach ($departureEnabledFields as $df) {
                        $value = $dep[$df] ?? null;
                        $hasValue = isset($dep[$df]) && $dep[$df] !== '' && $dep[$df] !== null;
                        $mapInfo = $getMappingInfo('departure', $df);
                        $depTestedFields[] = [
                            'field' => $df,
                            'api_field' => $mapInfo['api_field'],
                            'source_type' => $mapInfo['source_type'],
                            'mapped_value' => $value,
                            'status' => $hasValue ? 'ok' : 'empty',
                        ];
                    }
                    
                    $depItem['data'] = [
                        'departure_date' => $dep['departure_date'] ?? null,
                        'return_date' => $dep['return_date'] ?? null,
                        'price_adult' => $dep['price_adult'] ?? null,
                        'status' => $dep['status'] ?? null,
                    ];
                    $depItem['tested_fields'] = $depTestedFields;

                    $departureValidation['items'][] = $depItem;
                    
                    // Update section-level tested_fields with first item values
                    if ($i === 0) {
                        $departureValidation['tested_fields'] = $depTestedFields;
                    }
                }
                $results['summary']['departures'] = count($departures);
            }
            $results['validations'][] = $departureValidation;

            // === Validate Itinerary Section ===
            $itineraries = $transformedData['itinerary'] ?? [];
            
            // กำหนด itineraryFields ก่อนใช้งาน
            $itineraryFields = ['external_id', 'day_number', 'title', 'description', 'places', 'has_breakfast', 'has_lunch', 'has_dinner', 'meals_note', 'accommodation', 'hotel_star', 'images'];
            
            // รวบรวม fields ที่เปิดใช้งานใน itinerary section พร้อม mapping info
            $itineraryEnabledFields = [];
            $itineraryTestedFields = [];
            foreach ($itineraryFields as $itf) {
                if ($isFieldEnabled('itinerary', $itf)) {
                    $mapInfo = $getMappingInfo('itinerary', $itf);
                    
                    // ข้าม fields ที่ไม่ได้ map
                    $hasMapping = !empty($mapInfo['api_field']) || $mapInfo['source_type'] === 'fixed';
                    if (!$hasMapping) {
                        continue;
                    }
                    
                    $itineraryEnabledFields[] = $itf;
                    $itineraryTestedFields[] = [
                        'field' => $itf,
                        'api_field' => $mapInfo['api_field'],
                        'source_type' => $mapInfo['source_type'],
                        'mapped_value' => null, // จะ fill จาก item แรก
                        'status' => 'pending',
                    ];
                }
            }
            
            $itineraryValidation = [
                'section' => 'itinerary',
                'status' => 'success',
                'message' => '',
                'count' => count($itineraries),
                'enabled_count' => count($itineraryEnabledFields),
                'enabled_fields' => $itineraryEnabledFields,
                'tested_fields' => $itineraryTestedFields, // แสดง mapping ที่ระดับ section
                'items' => [],
            ];

            // ตรวจสอบว่า itinerary section มี field ใดเปิดใช้งานบ้าง
            $hasItineraryFieldsEnabled = count($itineraryEnabledFields) > 0;
            
            if (!$hasItineraryFieldsEnabled) {
                // ไม่มี itinerary field ใดเปิดใช้งาน ข้ามไป
                $itineraryValidation['message'] = 'Itinerary section disabled';
            } elseif (empty($itineraries)) {
                $results['warnings'][] = [
                    'section' => 'itinerary',
                    'type' => 'empty',
                    'message' => 'ไม่มีข้อมูลโปรแกรมทัวร์ (itineraries)',
                ];
                $results['summary']['warnings']++;
            } else {
                $dayNumbers = [];
                foreach ($itineraries as $i => $itin) {
                    $itinItem = [
                        'index' => $i + 1,
                        'status' => 'success',
                        'issues' => [],
                    ];

                    // Check day_number (ถ้าเปิดใช้งาน)
                    if ($isFieldEnabled('itinerary', 'day_number')) {
                        if (empty($itin['day_number'])) {
                            $itinItem['status'] = 'error';
                            $itinItem['issues'][] = 'ต้องระบุ day_number';
                            $results['summary']['errors']++;
                        } else {
                            // Check duplicate day_number
                            if (in_array($itin['day_number'], $dayNumbers)) {
                                $itinItem['status'] = 'warning';
                                $itinItem['issues'][] = "day_number {$itin['day_number']} ซ้ำ";
                                $results['summary']['warnings']++;
                            }
                            $dayNumbers[] = $itin['day_number'];
                        }
                    }

                    // สร้างรายละเอียด fields ที่ทดสอบ
                    $itinTestedFields = [];
                    foreach ($itineraryEnabledFields as $itf) {
                        $value = $itin[$itf] ?? null;
                        $hasValue = isset($itin[$itf]) && $itin[$itf] !== '' && $itin[$itf] !== null;
                        // สำหรับ boolean fields, null/undefined = false แต่ถือว่ามีค่า
                        if (in_array($itf, ['has_breakfast', 'has_lunch', 'has_dinner'])) {
                            $value = $itin[$itf] ?? false;
                            $hasValue = true; // boolean always has value
                        }
                        $mapInfo = $getMappingInfo('itinerary', $itf);
                        $itinTestedFields[] = [
                            'field' => $itf,
                            'api_field' => $mapInfo['api_field'],
                            'source_type' => $mapInfo['source_type'],
                            'mapped_value' => $value,
                            'status' => $hasValue ? 'ok' : 'empty',
                        ];
                    }
                    
                    $itinItem['data'] = [
                        'day_number' => $itin['day_number'] ?? null,
                        'title' => $itin['title'] ?? null,
                        'has_breakfast' => $itin['has_breakfast'] ?? false,
                        'has_lunch' => $itin['has_lunch'] ?? false,
                        'has_dinner' => $itin['has_dinner'] ?? false,
                    ];
                    $itinItem['tested_fields'] = $itinTestedFields;

                    $itineraryValidation['items'][] = $itinItem;
                    
                    // Update section-level tested_fields with first item values
                    if ($i === 0) {
                        $itineraryValidation['tested_fields'] = $itinTestedFields;
                    }
                }
                $results['summary']['itineraries'] = count($itineraries);
            }
            $results['validations'][] = $itineraryValidation;

            // === Final Status ===
            $results['success'] = $results['summary']['errors'] === 0;
            $results['message'] = $results['success'] 
                ? 'Mapping ผ่านการทดสอบ พร้อม sync ได้เลย!' 
                : 'พบปัญหา ' . $results['summary']['errors'] . ' รายการ กรุณาแก้ไขก่อน sync';

            return response()->json($results);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Get sync history for an integration
     * Note: Parameter is integration ID (WholesalerApiConfig ID), not wholesaler_id
     */
    public function getSyncHistory(int $id): JsonResponse
    {
        // Get config to find wholesaler_id
        $config = WholesalerApiConfig::find($id);
        $wholesalerId = $config ? $config->wholesaler_id : $id;

        $logs = SyncLog::where('wholesaler_id', $wholesalerId)
            ->with('errorLogs')
            ->orderBy('started_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Toggle sync enabled
     */
    public function toggleSync(int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);
        $config->update(['sync_enabled' => !$config->sync_enabled]);

        return response()->json([
            'success' => true,
            'message' => $config->sync_enabled ? 'Sync enabled' : 'Sync disabled',
            'data' => ['sync_enabled' => $config->sync_enabled],
        ]);
    }

    /**
     * Run health check for an integration
     */
    public function healthCheck(int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        try {
            $adapter = AdapterFactory::create($config->wholesaler_id);
            $healthy = $adapter->healthCheck();

            return response()->json([
                'success' => true,
                'data' => [
                    'healthy' => $healthy,
                    'checked_at' => now()->toIso8601String(),
                    'health_status' => $config->fresh()->health_status,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Health check failed: ' . $e->getMessage(),
                'data' => ['healthy' => false],
            ]);
        }
    }

    /**
     * Fetch sample data from API (first record)
     */
    public function fetchSample(int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        try {
            $adapter = AdapterFactory::create($config->wholesaler_id);
            
            // Fetch tours (first page)
            $result = $adapter->fetchTours(null);

            if ($result->success && !empty($result->tours)) {
                $sampleTour = $result->tours[0]; // First record
                
                // Check if two-phase sync is enabled
                // Support both: sync_mode column and auth_credentials.two_phase_sync
                $credentials = $config->auth_credentials ?? [];
                $isTwoPhase = ($config->sync_mode === 'two_phase') || !empty($credentials['two_phase_sync']);
                
                // Get periods endpoint - support both formats
                $periodsEndpoint = $credentials['endpoints']['periods'] 
                    ?? $credentials['periods_endpoint'] 
                    ?? null;
                
                if ($isTwoPhase && $periodsEndpoint && $adapter instanceof \App\Services\WholesalerAdapters\Adapters\GenericRestAdapter) {
                    // Build the periods endpoint URL by replacing all placeholders
                    // Extract all {field_name} patterns from the endpoint
                    $endpoint = $periodsEndpoint;
                    
                    // Find all placeholders like {external_id}, {tour_code}, {id}, etc.
                    if (preg_match_all('/\{([^}]+)\}/', $periodsEndpoint, $matches)) {
                        foreach ($matches[1] as $fieldName) {
                            // Try to get value from sample tour using the field name
                            $value = $sampleTour[$fieldName] ?? null;
                            
                            // If not found, try common fallbacks based on field name
                            if ($value === null) {
                                // For id-like fields, prefer numeric id
                                if (in_array($fieldName, ['external_id', 'tour_id', 'id'])) {
                                    $value = $sampleTour['id'] ?? $sampleTour['tour_id'] ?? $sampleTour['external_id'] ?? null;
                                } else {
                                    // For code-like fields, prefer code
                                    $value = $sampleTour['code'] ?? $sampleTour['tour_code'] ?? $sampleTour['id'] ?? null;
                                }
                            }
                            
                            if ($value !== null) {
                                $endpoint = str_replace('{' . $fieldName . '}', $value, $endpoint);
                            }
                        }
                    }
                    
                    // Check if all placeholders were replaced
                    if (!preg_match('/\{[^}]+\}/', $endpoint)) {
                        // Fetch periods
                        $periodsResult = $adapter->fetchPeriods($endpoint);
                        
                        if ($periodsResult->success && !empty($periodsResult->periods)) {
                            // Determine the field name to store periods
                            $periodsFieldName = $credentials['periods_field_name'] ?? 'periods';
                            $sampleTour[$periodsFieldName] = $periodsResult->periods;
                            $sampleTour['_periods_fetched_from'] = $endpoint;
                            $sampleTour['_periods_count'] = count($periodsResult->periods);
                        } else {
                            $sampleTour['_periods_error'] = $periodsResult->error ?? 'No periods found';
                            $sampleTour['_periods_endpoint'] = $endpoint;
                        }
                    } else {
                        $sampleTour['_periods_error'] = 'Could not resolve all placeholders in periods endpoint';
                        $sampleTour['_periods_endpoint_template'] = $periodsEndpoint;
                        $sampleTour['_periods_endpoint_resolved'] = $endpoint;
                    }
                }
                
                // Fetch itineraries if endpoint is configured
                $itinerariesEndpoint = $credentials['endpoints']['itineraries'] 
                    ?? $credentials['itineraries_endpoint'] 
                    ?? null;
                
                if ($itinerariesEndpoint && $adapter instanceof \App\Services\WholesalerAdapters\Adapters\GenericRestAdapter) {
                    // Build the itineraries endpoint URL by replacing all placeholders
                    $endpoint = $itinerariesEndpoint;
                    
                    // Find all placeholders like {external_id}, {id}, etc.
                    if (preg_match_all('/\{([^}]+)\}/', $itinerariesEndpoint, $matches)) {
                        foreach ($matches[1] as $fieldName) {
                            // Try to get value from sample tour using the field name
                            $value = $sampleTour[$fieldName] ?? null;
                            
                            // If not found, try common fallbacks
                            if ($value === null) {
                                $value = $sampleTour['id'] ?? $sampleTour['code'] ?? $sampleTour['tour_code'] ?? null;
                            }
                            
                            if ($value !== null) {
                                $endpoint = str_replace('{' . $fieldName . '}', $value, $endpoint);
                            }
                        }
                    }
                    
                    // Check if all placeholders were replaced
                    if (!preg_match('/\{[^}]+\}/', $endpoint)) {
                        // Fetch itineraries
                        $itinerariesResult = $adapter->fetchItineraries($endpoint);
                        
                        if ($itinerariesResult->success && !empty($itinerariesResult->itineraries)) {
                            // Determine the field name to store itineraries
                            $itinerariesFieldName = $credentials['itineraries_field_name'] ?? 'itineraries';
                            $sampleTour[$itinerariesFieldName] = $itinerariesResult->itineraries;
                            $sampleTour['_itineraries_fetched_from'] = $endpoint;
                            $sampleTour['_itineraries_count'] = count($itinerariesResult->itineraries);
                        } else {
                            $sampleTour['_itineraries_error'] = $itinerariesResult->error ?? 'No itineraries found';
                            $sampleTour['_itineraries_endpoint'] = $endpoint;
                        }
                    } else {
                        $sampleTour['_itineraries_error'] = 'Could not resolve all placeholders in itineraries endpoint';
                        $sampleTour['_itineraries_endpoint_template'] = $itinerariesEndpoint;
                        $sampleTour['_itineraries_endpoint_resolved'] = $endpoint;
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'data' => $sampleTour,
                    'meta' => [
                        'total' => count($result->tours),
                        'fetched_at' => now()->toIso8601String(),
                        'source' => 'api',
                        'two_phase_sync' => $isTwoPhase,
                        'periods_endpoint' => $periodsEndpoint,
                        'itineraries_endpoint' => $itinerariesEndpoint,
                    ],
                ]);
            }

            // If SyncResult has error, return it
            if (!$result->success) {
                return response()->json([
                    'success' => false,
                    'message' => $result->errorMessage ?? 'API returned error',
                    'error_code' => $result->errorCode ?? 'unknown',
                    'data' => null,
                    'meta' => [
                        'fetched_at' => now()->toIso8601String(),
                        'api_base_url' => $config->api_base_url,
                        'wholesaler' => $config->wholesaler?->name ?? 'Unknown',
                    ],
                ], 502); // Bad Gateway - upstream server error
            }

            // If API returns empty data, return mock data for testing mapping
            $mockData = [
                'tour_code' => 'SAMPLE-001',
                'tour_name' => 'Sample Tour from Wholesaler',
                'description' => 'This is sample data for testing field mapping. Replace with real API data.',
                'country' => 'Thailand',
                'city' => 'Bangkok',
                'duration_days' => 3,
                'duration_nights' => 2,
                'price_adult' => 15000,
                'price_child' => 12000,
                'currency' => 'THB',
                'departure_date' => '2026-03-15',
                'max_pax' => 30,
                'available_seats' => 25,
                'inclusions' => ['Hotel', 'Breakfast', 'Transportation'],
                'exclusions' => ['Lunch', 'Dinner', 'Personal expenses'],
                'itinerary' => [
                    ['day' => 1, 'title' => 'Arrival', 'description' => 'Arrive at destination'],
                    ['day' => 2, 'title' => 'Sightseeing', 'description' => 'Visit attractions'],
                    ['day' => 3, 'title' => 'Departure', 'description' => 'Return home'],
                ],
                'images' => [
                    'https://example.com/tour1.jpg',
                    'https://example.com/tour2.jpg',
                ],
                'status' => 'active',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            return response()->json([
                'success' => true,
                'data' => $mockData,
                'meta' => [
                    'total' => 1,
                    'fetched_at' => now()->toIso8601String(),
                    'source' => 'mock',
                    'note' => 'API returned empty data. Using sample data for mapping preview.',
                ],
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Connection timeout or failed
            Log::error('Connection failed to wholesaler API', [
                'integration_id' => $id,
                'api_base_url' => $config->api_base_url,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถเชื่อมต่อ API ได้: Connection timeout หรือ server ไม่ตอบสนอง',
                'error_detail' => $e->getMessage(),
                'error_type' => 'connection_failed',
                'data' => null,
                'meta' => [
                    'api_base_url' => $config->api_base_url,
                    'timeout_seconds' => $config->request_timeout_seconds,
                ],
            ], 504); // Gateway Timeout

        } catch (\Illuminate\Http\Client\RequestException $e) {
            // HTTP error response
            $response = $e->response;
            $statusCode = $response?->status() ?? 500;
            $body = $response?->json() ?? ['raw' => $response?->body()];
            
            Log::error('Wholesaler API returned error', [
                'integration_id' => $id,
                'status_code' => $statusCode,
                'response' => $body,
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->parseApiErrorMessage($statusCode, $body),
                'error_type' => 'api_error',
                'status_code' => $statusCode,
                'api_response' => $body,
                'data' => null,
                'meta' => [
                    'api_base_url' => $config->api_base_url,
                ],
            ], $statusCode >= 500 ? 502 : $statusCode);

        } catch (\Exception $e) {
            Log::error('Failed to fetch sample data', [
                'integration_id' => $id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Parse exception message for common errors
            $errorMessage = $e->getMessage();
            $errorType = 'unknown';
            
            if (str_contains($errorMessage, 'Could not resolve host')) {
                $errorType = 'dns_error';
                $errorMessage = 'ไม่พบ domain: ' . $config->api_base_url;
            } elseif (str_contains($errorMessage, 'Connection refused')) {
                $errorType = 'connection_refused';
                $errorMessage = 'Server ปฏิเสธการเชื่อมต่อ: ' . $config->api_base_url;
            } elseif (str_contains($errorMessage, 'SSL')) {
                $errorType = 'ssl_error';
                $errorMessage = 'SSL Certificate Error: ' . $errorMessage;
            } elseif (str_contains($errorMessage, 'API Error:')) {
                $errorType = 'api_error';
                // Extract actual error message
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error_type' => $errorType,
                'error_code' => $e->getCode() ?: null,
                'data' => null,
                'meta' => [
                    'api_base_url' => $config->api_base_url,
                    'wholesaler' => $config->wholesaler?->name ?? 'Unknown',
                ],
            ], 500);
        }
    }

    /**
     * Parse API error message for user-friendly display
     */
    private function parseApiErrorMessage(int $statusCode, array $body): string
    {
        // Common API error message locations
        $message = $body['message'] 
            ?? $body['error']['message'] 
            ?? $body['error'] 
            ?? $body['errors'][0]['message'] 
            ?? $body['detail']
            ?? null;

        if ($message && is_string($message)) {
            return "API Error ($statusCode): $message";
        }

        return match ($statusCode) {
            400 => 'Bad Request: คำขอไม่ถูกต้อง',
            401 => 'Unauthorized: API Key หรือ Token ไม่ถูกต้อง',
            403 => 'Forbidden: ไม่มีสิทธิ์เข้าถึง API นี้',
            404 => 'Not Found: ไม่พบ endpoint หรือข้อมูล',
            422 => 'Validation Error: ข้อมูลไม่ถูกต้อง',
            429 => 'Too Many Requests: เรียก API บ่อยเกินไป',
            500 => 'Internal Server Error: Server มีปัญหา',
            502 => 'Bad Gateway: Server upstream มีปัญหา',
            503 => 'Service Unavailable: Server ไม่พร้อมให้บริการ',
            504 => 'Gateway Timeout: Server ไม่ตอบสนองในเวลาที่กำหนด',
            default => "HTTP Error $statusCode",
        };
    }

    /**
     * Preview Sync - Fetch from API, apply mapping, return transformed data
     * This allows frontend to preview what will be synced before actually syncing
     */
    public function previewSync(Request $request, int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $limit = $request->limit ?? $config->sync_limit ?? 10;
            $wholesalerId = $config->wholesaler_id;

            // Fetch from API
            $adapter = \App\Services\WholesalerAdapters\AdapterFactory::create($wholesalerId);
            $result = $adapter->fetchTours(null);

            if (!$result->success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch tours: ' . $result->errorMessage,
                ], 500);
            }

            // Get mappings
            $mappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
                ->where('is_active', true)
                ->get()
                ->groupBy('section_name');

            // Apply limit
            $tours = array_slice($result->tours, 0, $limit);

            // Transform each tour using frontend-compatible format
            $transformedTours = [];
            foreach ($tours as $rawTour) {
                $transformed = $this->transformTourData($rawTour, $mappings);
                $transformedTours[] = $transformed;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_fetched' => count($result->tours),
                    'preview_count' => count($transformedTours),
                    'limit' => $limit,
                    'transformed_data' => $transformedTours,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Preview sync failed', [
                'integration_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Preview failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transform raw tour data using mappings (frontend-compatible format)
     * Uses correct column names: their_field, their_field_path, transform_type, transform_config
     */
    private function transformTourData(array $rawTour, $mappings): array
    {
        $result = [
            'tour' => [],
            'departure' => [],
            'itinerary' => [],
            'content' => [],
            'media' => [],
        ];

        // Helper to extract value from nested path (e.g., "Country.Name" or "Periods[].Price")
        $extractValue = function($data, $path) {
            if (empty($path)) return null;
            
            // Handle array notation like "Periods[]"
            if (strpos($path, '[]') !== false) {
                // This is an array path - for single values, just use first item
                $parts = explode('[].', $path);
                $arrayKey = $parts[0];
                $fieldPath = $parts[1] ?? null;
                
                if (!isset($data[$arrayKey]) || !is_array($data[$arrayKey])) return null;
                if (empty($data[$arrayKey])) return null;
                
                // Get from first item if fieldPath exists
                if ($fieldPath && isset($data[$arrayKey][0])) {
                    return $data[$arrayKey][0][$fieldPath] ?? null;
                }
                return null;
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
        $applyTransform = function($value, $mapping, $rawData) use ($extractValue) {
            if (empty($mapping->transform_type)) return $value;
            
            $config = $mapping->transform_config ?? [];
            
            switch ($mapping->transform_type) {
                case 'concat':
                    // Template-based concat like "{ProductName}-{Highlight}"
                    $stringTransform = $config['string_transform'] ?? [];
                    if (isset($stringTransform['template'])) {
                        $template = $stringTransform['template'];
                        // Replace {FieldName} with actual values
                        $result = preg_replace_callback('/\{(\w+)\}/', function($matches) use ($rawData) {
                            return $rawData[$matches[1]] ?? '';
                        }, $template);
                        return $result;
                    }
                    return $value;
                    
                case 'value_map':
                    $map = $config['map'] ?? [];
                    return $map[$value] ?? $value;
                    
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

        // Map single-value sections (tour, content, media)
        foreach (['tour', 'content', 'media'] as $section) {
            if (!isset($mappings[$section])) continue;
            
            foreach ($mappings[$section] as $mapping) {
                $fieldName = $mapping->our_field;
                $path = $mapping->their_field_path ?? $mapping->their_field;
                
                // Extract value from raw data
                $value = $extractValue($rawTour, $path);
                
                // Apply transforms
                $value = $applyTransform($value, $mapping, $rawTour);
                
                // Use default if null
                if ($value === null && !empty($mapping->default_value)) {
                    $value = $mapping->default_value;
                }
                
                $result[$section][$fieldName] = $value;
            }
        }

        // Map departures (from Periods array)
        $periods = $rawTour['Periods'] ?? [];
        if (isset($mappings['departure']) && !empty($periods)) {
            foreach ($periods as $period) {
                $dep = [];
                foreach ($mappings['departure'] as $mapping) {
                    $fieldName = $mapping->our_field;
                    $path = $mapping->their_field_path ?? $mapping->their_field;
                    
                    // Remove "Periods[]." prefix if exists
                    $cleanPath = preg_replace('/^Periods\[\]\\./', '', $path);
                    
                    // Extract value from period item
                    $value = $period[$cleanPath] ?? null;
                    
                    // Apply transforms
                    $value = $applyTransform($value, $mapping, $period);
                    
                    // Use default if null
                    if ($value === null && !empty($mapping->default_value)) {
                        $value = $mapping->default_value;
                    }
                    
                    $dep[$fieldName] = $value;
                }
                $result['departure'][] = $dep;
            }
        }

        // Map itinerary (from Itinerary array)
        $itineraries = $rawTour['Itinerary'] ?? [];
        if (isset($mappings['itinerary']) && !empty($itineraries)) {
            foreach ($itineraries as $itin) {
                $it = [];
                foreach ($mappings['itinerary'] as $mapping) {
                    $fieldName = $mapping->our_field;
                    $path = $mapping->their_field_path ?? $mapping->their_field;
                    
                    // Remove "Itinerary[]." prefix if exists
                    $cleanPath = preg_replace('/^Itinerary\[\]\\./', '', $path);
                    
                    // Extract value from itinerary item
                    $value = $itin[$cleanPath] ?? null;
                    
                    // Apply transforms
                    $value = $applyTransform($value, $mapping, $itin);
                    
                    // Use default if null
                    if ($value === null && !empty($mapping->default_value)) {
                        $value = $mapping->default_value;
                    }
                    
                    $it[$fieldName] = $value;
                }
                $result['itinerary'][] = $it;
            }
        }

        return $result;
    }

    /**
     * Sync Now - Run sync immediately
     * Supports two modes:
     * 1. With transformed_data: Insert directly
     * 2. Without: Fetch from API and map
     */
    public function syncNow(Request $request, int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'transformed_data' => 'nullable|array',
            'sync_type' => 'nullable|in:manual,incremental,full',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $transformedData = $request->transformed_data;
            $syncType = $request->sync_type ?? ($transformedData ? 'manual' : 'incremental');
            $limit = $request->limit ? (int) $request->limit : null;

            // Dispatch job to queue (background processing)
            \App\Jobs\SyncToursJob::dispatch(
                $config->wholesaler_id,
                $transformedData,
                $syncType,
                $limit
            );

            return response()->json([
                'success' => true,
                'message' => 'Sync job dispatched successfully',
                'data' => [
                    'sync_type' => $syncType,
                    'has_transformed_data' => !empty($transformedData),
                    'limit' => $limit,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch sync job', [
                'integration_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start sync: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload PDF header image
     */
    public function uploadHeader(Request $request, int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:png,jpg,jpeg|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('image');
            
            // Get image dimensions
            $imageInfo = getimagesize($file->getPathname());
            $height = $imageInfo[1] ?? null;

            // Try Cloudflare first, fallback to local storage
            $cloudflare = app(\App\Services\CloudflareImagesService::class);
            $customId = 'integration-header-' . $id . '-' . time();
            
            if ($cloudflare->isConfigured()) {
                $result = $cloudflare->uploadFromFile($file, $customId, [
                    'type' => 'integration-header',
                    'integration_id' => $id,
                ]);
                
                if ($result && !empty($result['variants'])) {
                    // Find 'public' variant, fallback to first variant
                    $url = collect($result['variants'])->first(fn($v) => str_contains($v, '/public')) 
                        ?? $result['variants'][0] 
                        ?? null;
                }
            }
            
            // Fallback to local storage
            if (empty($url)) {
                $path = $file->store('integrations/headers', 'public');
                $url = \Storage::disk('public')->url($path);
            }

            // Update config
            $config->update([
                'pdf_header_image' => $url,
                'pdf_header_height' => $height,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Header image uploaded successfully',
                'data' => [
                    'url' => $url,
                    'height' => $height,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload header image', [
                'integration_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload PDF footer image
     */
    public function uploadFooter(Request $request, int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:png,jpg,jpeg|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('image');
            
            // Get image dimensions
            $imageInfo = getimagesize($file->getPathname());
            $height = $imageInfo[1] ?? null;

            // Try Cloudflare first, fallback to local storage
            $cloudflare = app(\App\Services\CloudflareImagesService::class);
            $customId = 'integration-footer-' . $id . '-' . time();
            
            if ($cloudflare->isConfigured()) {
                $result = $cloudflare->uploadFromFile($file, $customId, [
                    'type' => 'integration-footer',
                    'integration_id' => $id,
                ]);
                
                if ($result && !empty($result['variants'])) {
                    // Find 'public' variant, fallback to first variant
                    $url = collect($result['variants'])->first(fn($v) => str_contains($v, '/public')) 
                        ?? $result['variants'][0] 
                        ?? null;
                }
            }
            
            // Fallback to local storage
            if (empty($url)) {
                $path = $file->store('integrations/footers', 'public');
                $url = \Storage::disk('public')->url($path);
            }

            // Update config
            $config->update([
                'pdf_footer_image' => $url,
                'pdf_footer_height' => $height,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Footer image uploaded successfully',
                'data' => [
                    'url' => $url,
                    'height' => $height,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload footer image', [
                'integration_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove PDF header image
     */
    public function removeHeader(int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        $config->update([
            'pdf_header_image' => null,
            'pdf_header_height' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Header image removed successfully',
        ]);
    }

    /**
     * Remove PDF footer image
     */
    public function removeFooter(int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        $config->update([
            'pdf_footer_image' => null,
            'pdf_footer_height' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Footer image removed successfully',
        ]);
    }

    /**
     * Get aggregation config for a wholesaler
     */
    public function getAggregationConfig(int $id): JsonResponse
    {
        $config = WholesalerApiConfig::with('wholesaler:id,name')->findOrFail($id);

        $globalConfig = \App\Models\Setting::get('tour_aggregations', [
            'price_adult' => 'min',
            'discount_adult' => 'max',
            'min_price' => 'min',
            'max_price' => 'max',
            'display_price' => 'min',
            'discount_amount' => 'max',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'wholesaler_id' => $config->wholesaler_id,
                'wholesaler_name' => $config->wholesaler?->name,
                'global_config' => $globalConfig,
                'override_config' => $config->aggregation_config,
                'effective_config' => array_merge($globalConfig, $config->aggregation_config ?? []),
                'options' => ['min', 'max', 'avg', 'first'],
                'fields' => [
                    'price_adult' => 'ราคาผู้ใหญ่',
                    'discount_adult' => 'ส่วนลดผู้ใหญ่',
                    'min_price' => 'ราคาต่ำสุด',
                    'max_price' => 'ราคาสูงสุด',
                    'display_price' => 'ราคาที่แสดง',
                    'discount_amount' => 'จำนวนส่วนลด',
                ],
            ],
        ]);
    }

    /**
     * Update aggregation config for a wholesaler
     */
    public function updateAggregationConfig(Request $request, int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        $validated = $request->validate([
            'config' => 'nullable|array',
            'config.price_adult' => 'nullable|in:min,max,avg,first',
            'config.discount_adult' => 'nullable|in:min,max,avg,first',
            'config.min_price' => 'nullable|in:min,max,avg,first',
            'config.max_price' => 'nullable|in:min,max,avg,first',
            'config.display_price' => 'nullable|in:min,max,avg,first',
            'config.discount_amount' => 'nullable|in:min,max,avg,first',
            'use_global' => 'nullable|boolean',
        ]);

        // If use_global is true, clear the override config
        if ($request->boolean('use_global')) {
            $config->update(['aggregation_config' => null]);
        } else {
            // Filter out null values to only override specified fields
            $overrideConfig = array_filter($validated['config'] ?? [], fn($v) => $v !== null);
            $config->update(['aggregation_config' => !empty($overrideConfig) ? $overrideConfig : null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Aggregation config updated successfully',
            'data' => [
                'override_config' => $config->aggregation_config,
            ],
        ]);
    }

    /**
     * Get queue and sync status
     */
    public function getQueueStatus(): JsonResponse
    {
        // Count pending jobs in queue
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        
        // Check for stuck sync logs (running for more than 30 minutes)
        $stuckSyncs = SyncLog::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(30))
            ->get(['id', 'wholesaler_id', 'started_at', 'sync_type']);
        
        // Currently running syncs
        $runningSyncs = SyncLog::where('status', 'running')
            ->where('started_at', '>=', now()->subMinutes(30))
            ->with('wholesaler:id,name')
            ->get(['id', 'wholesaler_id', 'started_at', 'sync_type']);
        
        // Queue worker status (check if jobs are being processed)
        $lastProcessedJob = SyncLog::whereIn('status', ['completed', 'failed'])
            ->latest('completed_at')
            ->first();
        
        $queueWorkerActive = $lastProcessedJob && $lastProcessedJob->completed_at > now()->subMinutes(5);
        
        return response()->json([
            'success' => true,
            'data' => [
                'queue' => [
                    'pending_jobs' => $pendingJobs,
                    'failed_jobs' => $failedJobs,
                    'worker_active' => $queueWorkerActive,
                    'last_job_completed_at' => $lastProcessedJob?->completed_at,
                ],
                'syncs' => [
                    'running' => $runningSyncs,
                    'stuck' => $stuckSyncs,
                    'stuck_count' => $stuckSyncs->count(),
                ],
                'alerts' => [
                    'queue_not_running' => $pendingJobs > 0 && !$queueWorkerActive,
                    'has_stuck_syncs' => $stuckSyncs->count() > 0,
                    'has_failed_jobs' => $failedJobs > 0,
                ],
            ],
        ]);
    }

    /**
     * Fix stuck sync jobs (mark as failed)
     */
    public function fixStuckSyncs(): JsonResponse
    {
        $stuckSyncs = SyncLog::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(30))
            ->get();
        
        $fixed = 0;
        foreach ($stuckSyncs as $sync) {
            $sync->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);
            $fixed++;
        }
        
        return response()->json([
            'success' => true,
            'message' => "Fixed {$fixed} stuck sync jobs",
            'data' => [
                'fixed_count' => $fixed,
            ],
        ]);
    }

    /**
     * Clear all failed jobs from the queue
     */
    public function clearFailedJobs(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();
        DB::table('failed_jobs')->truncate();
        
        return response()->json([
            'success' => true,
            'message' => "Cleared {$count} failed jobs",
            'data' => [
                'cleared_count' => $count,
            ],
        ]);
    }

    /**
     * Sync selected tours from search results (Mass Sync)
     * 
     * This allows users to sync specific tours from the search page
     * without waiting for scheduled sync.
     * Uses the same mapping logic as SyncToursJob.
     * 
     * @param int $id Integration ID (WholesalerApiConfig primary key)
     */
    public function syncSelectedTours(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tours' => 'required|array|min:1|max:5',
            'tours.*._raw' => 'required|array',
            'tours.*._wholesaler_id' => 'required|integer',
        ], [
            'tours.required' => 'กรุณาเลือกทัวร์ที่ต้องการ sync',
            'tours.min' => 'กรุณาเลือกอย่างน้อย 1 ทัวร์',
            'tours.max' => 'สามารถ sync ได้สูงสุด 5 ทัวร์ต่อครั้ง',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Use integration ID (primary key) instead of wholesaler_id
        $config = WholesalerApiConfig::findOrFail($id);
        $wholesalerId = $config->wholesaler_id;
        $wholesalerCode = $config->wholesaler?->code ?? 'default';

        // Check sync mode (single or two_phase)
        $syncMode = $config->sync_mode ?? 'single';
        $credentials = $config->auth_credentials ?? [];
        $endpoints = $credentials['endpoints'] ?? [];

        // Initialize PDF branding service if configured (same as SyncToursJob)
        $pdfBranding = null;
        if ($config->pdf_header_image || $config->pdf_footer_image) {
            $pdfBranding = new \App\Services\PdfBrandingService();
            $pdfBranding->setHeader($config->pdf_header_image, $config->pdf_header_height);
            $pdfBranding->setFooter($config->pdf_footer_image, $config->pdf_footer_height);
        }

        // Create adapter for two-phase API calls
        $adapter = null;
        if ($syncMode === 'two_phase') {
            $adapter = \App\Services\WholesalerAdapters\AdapterFactory::create($wholesalerId);
        }

        // Load field mappings (same as SyncToursJob)
        $mappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
            ->where('is_active', true)
            ->get()
            ->groupBy('section_name');

        $results = [
            'total' => count($request->tours),
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        foreach ($request->tours as $index => $tourData) {
            $raw = $tourData['_raw'] ?? [];
            $externalId = $tourData['external_id'] ?? $raw['ProductId'] ?? $raw['id'] ?? $raw['code'] ?? null;

            if (!$externalId) {
                $results['failed']++;
                $results['details'][] = [
                    'index' => $index,
                    'status' => 'failed',
                    'error' => 'ไม่พบ external_id',
                ];
                continue;
            }

            try {
                DB::beginTransaction();

                // Transform raw data using mappings (same logic as SyncToursJob)
                // Pass aggregation_config for nested data structure support
                $transformed = $this->transformTourDataForSync($raw, $mappings, $config->aggregation_config ?? []);
                
                // Extract tour data (merge tour + content + media sections)
                $tourFields = array_merge(
                    $transformed['tour'] ?? [],
                    $transformed['content'] ?? [],
                    $transformed['media'] ?? [],
                    $transformed['seo'] ?? []
                );
                $tourFields['external_id'] = $externalId;

                // Process PDF if exists - upload to Cloudflare R2 (with branding if configured)
                // Same logic as SyncToursJob
                $pdfUrl = $tourFields['pdf_url'] ?? null;
                if ($pdfUrl && str_starts_with($pdfUrl, 'http') && !str_contains($pdfUrl, env('R2_URL', ''))) {
                    try {
                        if ($pdfBranding) {
                            // Download, add header/footer, then upload to R2
                            $brandedPdfUrl = $pdfBranding->processAndUpload($pdfUrl, $wholesalerCode);
                            if ($brandedPdfUrl) {
                                $tourFields['pdf_url'] = $brandedPdfUrl;
                            }
                        } else {
                            // No branding - still upload to R2
                            $filename = pathinfo(parse_url($pdfUrl, PHP_URL_PATH), PATHINFO_FILENAME);
                            $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename) . '_' . uniqid() . '.pdf';
                            $r2Path = "pdfs/{$wholesalerCode}/" . date('Y/m') . "/{$filename}";
                            
                            // Download and upload to R2
                            $pdfContent = @file_get_contents($pdfUrl);
                            if ($pdfContent) {
                                $disk = \Storage::disk('r2');
                                $disk->put($r2Path, $pdfContent, 'public');
                                $r2Url = env('R2_URL');
                                if ($r2Url) {
                                    $tourFields['pdf_url'] = rtrim($r2Url, '/') . '/' . $r2Path;
                                } else {
                                    $tourFields['pdf_url'] = $disk->url($r2Path);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Mass Sync: Failed to upload PDF to R2', [
                            'url' => $pdfUrl,
                            'error' => $e->getMessage(),
                        ]);
                        // Keep original URL if upload fails
                    }
                }

                // Process cover image - upload to Cloudflare (same as SyncToursJob)
                $coverImageUrl = $tourFields['cover_image_url'] ?? null;
                if ($coverImageUrl && str_starts_with($coverImageUrl, 'http') && !str_contains($coverImageUrl, 'imagedelivery.net')) {
                    try {
                        $cloudflare = app(\App\Services\CloudflareImagesService::class);
                        if ($cloudflare->isConfigured()) {
                            $uploadResult = $cloudflare->uploadFromUrl($coverImageUrl, 'tour-cover-' . uniqid());
                            if ($uploadResult && isset($uploadResult['id'])) {
                                $tourFields['cover_image_url'] = $cloudflare->getDisplayUrl($uploadResult['id']);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Mass Sync: Failed to upload cover image', [
                            'url' => $coverImageUrl,
                            'error' => $e->getMessage(),
                        ]);
                        // Keep original URL if upload fails
                    }
                }
                
                // Auto-calculate duration_nights from duration_days if not provided
                if (empty($tourFields['duration_nights']) && !empty($tourFields['duration_days'])) {
                    $tourFields['duration_nights'] = max(0, (int)$tourFields['duration_days'] - 1);
                }
                
                // Ensure duration_nights has a value (required field)
                if (!isset($tourFields['duration_nights']) || $tourFields['duration_nights'] === null) {
                    $tourFields['duration_nights'] = 0;
                }

                // Find or create tour
                $tour = \App\Models\Tour::where('wholesaler_id', $wholesalerId)
                    ->where('external_id', $externalId)
                    ->first();

                $isNew = !$tour;

                if ($isNew) {
                    $tour = new \App\Models\Tour();
                    $tour->wholesaler_id = $wholesalerId;
                    $tour->external_id = $externalId;
                    $tour->tour_code = \App\Models\Tour::generateTourCode();
                    $tour->data_source = 'api';
                    $tour->status = 'draft';
                } else {
                    // Skip if tour was created manually
                    if ($tour->data_source === 'manual') {
                        $results['skipped']++;
                        $results['details'][] = [
                            'index' => $index,
                            'external_id' => $externalId,
                            'status' => 'skipped',
                            'reason' => 'manual_tour',
                        ];
                        DB::rollBack();
                        continue;
                    }
                }

                // Fill tour data
                $tour->fill($this->filterFillable($tour, $tourFields));
                $tour->sync_status = 'active';
                $tour->last_synced_at = now();
                $tour->save();

                // Sync primary country to tour_countries pivot table (same as SyncToursJob)
                if (!empty($tourFields['primary_country_id'])) {
                    $countryId = $tourFields['primary_country_id'];
                    $currentPrimary = $tour->countries()->wherePivot('is_primary', true)->first();
                    if ($currentPrimary && $currentPrimary->id !== $countryId) {
                        $tour->countries()->detach($currentPrimary->id);
                    }
                    $exists = $tour->countries()->where('country_id', $countryId)->exists();
                    if (!$exists) {
                        $tour->countries()->attach($countryId, ['is_primary' => true, 'sort_order' => 1]);
                    } else {
                        $tour->countries()->updateExistingPivot($countryId, ['is_primary' => true]);
                    }
                }

                // Sync transport to tour_transports table (same as SyncToursJob)
                if (!empty($tourFields['transport_id'])) {
                    $transportId = $tourFields['transport_id'];
                    $transport = \App\Models\Transport::find($transportId);
                    if ($transport) {
                        $exists = $tour->transports()->where('transport_id', $transportId)->exists();
                        if (!$exists) {
                            $tour->transports()->create([
                                'transport_id' => $transportId,
                                'transport_code' => $transport->code ?? '',
                                'transport_name' => $transport->name ?? '',
                                'transport_type' => 'outbound',
                                'sort_order' => 1,
                            ]);
                        }
                    }
                }

                // Process periods/departures
                // Two Phase: Periods MUST be fetched from separate Periods Endpoint
                // Single Phase: Use periods from Tours response
                $periodsResult = ['created' => 0, 'updated' => 0, 'skipped_past' => 0];
                
                if ($syncMode === 'two_phase') {
                    // Two-Phase: MUST fetch periods from separate API endpoint
                    if (!empty($endpoints['periods']) && $adapter) {
                        try {
                            $periodsResult = $this->fetchAndSyncPeriodsFromApi(
                                $tour, $adapter, $endpoints, $wholesalerId
                            );
                        } catch (\Exception $e) {
                            Log::error('Mass Sync: Failed to fetch periods from API', [
                                'tour_id' => $tour->id,
                                'endpoint' => $endpoints['periods'] ?? 'N/A',
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } else {
                        Log::warning('Mass Sync: Two-phase mode but no periods endpoint configured', [
                            'tour_id' => $tour->id,
                        ]);
                    }
                } else {
                    // Single-Phase: Use periods from Tours response
                    $departures = $transformed['departure'] ?? [];
                    if (!empty($departures)) {
                        $periodsResult = $this->processDeparturesForSync($tour, $departures);
                    }
                }

                // Process itineraries
                // Two Phase: If Itineraries Endpoint is set → fetch from it, otherwise use Tours response
                // Single Phase: Use itineraries from Tours response
                $itineraries = $transformed['itinerary'] ?? [];
                
                Log::debug('Mass Sync: Processing itineraries', [
                    'tour_id' => $tour->id,
                    'sync_mode' => $syncMode,
                    'has_itineraries_endpoint' => !empty($endpoints['itineraries']),
                    'has_adapter' => $adapter !== null,
                    'transformed_itineraries_count' => count($itineraries),
                ]);
                
                if ($syncMode === 'two_phase' && !empty($endpoints['itineraries']) && $adapter) {
                    // Two-Phase with Itineraries Endpoint: fetch from separate API
                    try {
                        $this->fetchAndSyncItinerariesFromApi(
                            $tour, $adapter, $endpoints, $wholesalerId
                        );
                    } catch (\Exception $e) {
                        Log::error('Mass Sync: Failed to fetch itineraries from API', [
                            'tour_id' => $tour->id,
                            'endpoint' => $endpoints['itineraries'] ?? 'N/A',
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    // Single-Phase OR Two-Phase without Itineraries Endpoint: use Tours response
                    if (!empty($itineraries)) {
                        foreach ($itineraries as $itin) {
                            $this->processItineraryForSync($tour, $itin);
                        }
                    }
                }

                DB::commit();

                // Recalculate aggregates (outside transaction to not fail the sync)
                try {
                    $tour->recalculateAggregates();
                } catch (\Exception $e) {
                    Log::warning('recalculateAggregates failed (may need migration)', [
                        'tour_id' => $tour->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $results['success']++;
                $results['details'][] = [
                    'index' => $index,
                    'external_id' => $externalId,
                    'tour_id' => $tour->id,
                    'tour_code' => $tour->tour_code,
                    'status' => 'success',
                    'is_new' => $isNew,
                    'periods' => $periodsResult,
                ];

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Sync selected tour failed', [
                    'index' => $index,
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);

                $results['failed']++;
                $results['details'][] = [
                    'index' => $index,
                    'external_id' => $externalId,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Cleanup PDF branding service (same as SyncToursJob)
        if ($pdfBranding) {
            $pdfBranding->cleanup();
        }

        return response()->json([
            'success' => $results['failed'] === 0,
            'message' => "Sync สำเร็จ {$results['success']}/{$results['total']} ทัวร์",
            'data' => $results,
        ]);
    }

    /**
     * Transform raw tour data using mappings (same logic as SyncToursJob)
     * Supports nested data structures via aggregation_config
     */
    protected function transformTourDataForSync(array $rawTour, $mappings, array $dataStructure = []): array
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
                    if ($value === null || $value === '') return null;
                    
                    $lookupTable = $config['lookup_table'] ?? null;
                    $lookupBy = $config['lookup_by'] ?? 'id';
                    
                    if (!$lookupTable) {
                        $ourField = $mapping->our_field;
                        if (str_ends_with($ourField, '_id')) {
                            $baseName = substr($ourField, 0, -3);
                            if (str_contains($baseName, '_')) {
                                $parts = explode('_', $baseName);
                                $baseName = end($parts);
                            }
                            $lookupTable = str($baseName)->plural()->toString();
                        }
                    }
                    
                    if (!$lookupTable) return $value;
                    
                    $modelClass = 'App\\Models\\' . str($lookupTable)->singular()->studly()->toString();
                    if (!class_exists($modelClass)) return $value;
                    
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
                    
                case 'value_map':
                    $map = $config['map'] ?? null;
                    if ($map === null && isset($config['value_map'])) {
                        $map = [];
                        foreach ($config['value_map'] as $item) {
                            $fromVal = $item['from'] ?? null;
                            if ($fromVal === '__EMPTY__') $fromVal = '';
                            if ($fromVal !== null) {
                                $map[$fromVal] = $item['to'] ?? null;
                            }
                        }
                    }
                    if ($map === null) return $value;
                    
                    $lookupKey = ($value === '' || $value === null) ? '' : $value;
                    $mappedValue = $map[$lookupKey] ?? ($config['default'] ?? null);
                    
                    if ($mappedValue === 'true') return 1;
                    if ($mappedValue === 'false') return 0;
                    return $mappedValue;
                    
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
                $value = $applyTransform($value, $mapping, $rawTour);
                
                if ($value === null && !empty($mapping->default_value)) {
                    $value = $mapping->default_value;
                }
                
                $result[$section][$fieldName] = $value;
            }
        }

        // Map departures - support nested paths from aggregation_config (same as SyncToursJob)
        $departuresPath = $dataStructure['data_structure']['departures']['path'] ?? null;
        
        if ($departuresPath) {
            // Use custom nested path (e.g., "periods[].tour_period[]" for GO365)
            $periods = $this->flattenNestedPathForSync($rawTour, $departuresPath);
        } else {
            // Default: use standard periods/schedules/departures array
            $periods = $rawTour['Periods'] ?? $rawTour['periods'] ?? $rawTour['schedules'] ?? $rawTour['departures'] ?? [];
        }
        
        if (isset($mappings['departure']) && !empty($periods) && is_array($periods)) {
            foreach ($periods as $period) {
                if (!is_array($period)) continue; // Skip if not an array
                $dep = [];
                foreach ($mappings['departure'] as $mapping) {
                    $fieldName = $mapping->our_field;
                    $path = $mapping->their_field_path ?? $mapping->their_field;
                    
                    // Remove all known array prefixes to get the final field key
                    $cleanPath = $this->cleanNestedPathForSync($path, $departuresPath);
                    
                    // Extract value - support nested fields within the item
                    $value = $this->extractNestedValueForSync($period, $cleanPath);
                    $value = $applyTransform($value, $mapping, $period);
                    
                    if ($value === null && !empty($mapping->default_value)) {
                        $value = $mapping->default_value;
                    }
                    
                    $dep[$fieldName] = $value;
                }
                $result['departure'][] = $dep;
            }
        }

        // Map itineraries - support nested paths from aggregation_config (same as SyncToursJob)
        $itinerariesPath = $dataStructure['data_structure']['itineraries']['path'] ?? null;
        
        if ($itinerariesPath) {
            // Use custom nested path (e.g., "periods[].tour_daily[].day_list[]" for GO365)
            $itineraries = $this->flattenNestedPathForSync($rawTour, $itinerariesPath);
        } else {
            // Default: use standard itinerary arrays
            $itineraries = $rawTour['Itinerary'] ?? $rawTour['itinerary'] ?? $rawTour['itineraries'] ?? $rawTour['days'] ?? $rawTour['programs'] ?? $rawTour['tour_daily'] ?? [];
        }
        
        if (isset($mappings['itinerary']) && !empty($itineraries) && is_array($itineraries)) {
            $dayIndex = 1;
            foreach ($itineraries as $itin) {
                if (!is_array($itin)) continue; // Skip if not an array
                $it = [];
                foreach ($mappings['itinerary'] as $mapping) {
                    $fieldName = $mapping->our_field;
                    $path = $mapping->their_field_path ?? $mapping->their_field;
                    
                    // Remove all known array prefixes to get the final field key
                    $cleanPath = $this->cleanNestedPathForSync($path, $itinerariesPath);
                    
                    // Extract value - support nested fields within the item
                    $value = $this->extractNestedValueForSync($itin, $cleanPath);
                    $value = $applyTransform($value, $mapping, $itin);
                    
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
     * Flatten nested path like "periods[].tour_period[]" into flat array
     * Same logic as SyncToursJob::flattenNestedPath()
     */
    protected function flattenNestedPathForSync(array $data, string $path): array
    {
        $result = [];
        
        // Split path into segments (e.g., "periods[].tour_period[]" -> ["periods[]", "tour_period[]"])
        $segments = preg_split('/\[\]\.?/', $path, -1, PREG_SPLIT_NO_EMPTY);
        
        // Recursive helper to traverse nested arrays
        $traverse = function($currentData, $remainingSegments) use (&$traverse, &$result) {
            if (empty($remainingSegments)) {
                // We've reached the end, add to result
                if (is_array($currentData)) {
                    $result[] = $currentData;
                }
                return;
            }
            
            $segment = array_shift($remainingSegments);
            
            if (!is_array($currentData) || !isset($currentData[$segment])) {
                return;
            }
            
            $nextData = $currentData[$segment];
            
            if (is_array($nextData)) {
                // Check if it's an indexed array (list of items) or associative
                if (array_keys($nextData) === range(0, count($nextData) - 1)) {
                    // Indexed array - iterate each item
                    foreach ($nextData as $item) {
                        $traverse($item, $remainingSegments);
                    }
                } else {
                    // Associative array - continue down
                    $traverse($nextData, $remainingSegments);
                }
            }
        };
        
        $traverse($data, $segments);
        
        return $result;
    }

    /**
     * Clean nested path by removing known prefixes
     * Same logic as SyncToursJob::cleanNestedPath()
     */
    protected function cleanNestedPathForSync(string $fieldPath, ?string $basePath): string
    {
        if (!$basePath) {
            // Just remove common prefixes if no base path
            return preg_replace('/^(Periods|periods|schedules|departures|Itinerary|itinerary|itineraries|days|programs|tour_daily|tour_period)\[\]\\.?/', '', $fieldPath);
        }
        
        // Remove the base path prefix from field path
        // e.g., base = "periods[].tour_period[]", field = "periods[].tour_period[].period_date"
        // result = "period_date"
        
        // Escape special regex characters in base path
        $escapedBase = preg_quote($basePath, '/');
        // Replace \[\] with actual regex for []
        $escapedBase = str_replace('\[\]', '\\[\\]', $escapedBase);
        
        // Add optional dot after the base path
        $pattern = '/^' . $escapedBase . '\\.?/';
        
        $cleaned = preg_replace($pattern, '', $fieldPath);
        
        // Also handle case where field path uses different prefix format
        // Remove any remaining array notation prefix
        $cleaned = preg_replace('/^[a-zA-Z_]+\[\]\\.?/', '', $cleaned);
        
        return $cleaned ?: $fieldPath;
    }

    /**
     * Extract nested value from data using dot notation
     * Same logic as SyncToursJob::extractNestedValue()
     */
    protected function extractNestedValueForSync(array $data, string $path)
    {
        if (empty($path)) {
            return null;
        }
        
        // Handle simple direct field access
        if (isset($data[$path])) {
            return $data[$path];
        }
        
        // Handle dot notation
        $keys = explode('.', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (!is_array($value)) {
                return null;
            }
            
            // Handle array notation like "field[]"
            if (str_ends_with($key, '[]')) {
                $arrayKey = substr($key, 0, -2);
                if (!isset($value[$arrayKey]) || !is_array($value[$arrayKey])) {
                    return null;
                }
                // Return first element of array
                $value = $value[$arrayKey][0] ?? null;
            } else {
                if (!isset($value[$key])) {
                    return null;
                }
                $value = $value[$key];
            }
        }
        
        return $value;
    }

    /**
     * Process departures for sync (same logic as SyncToursJob)
     */
    protected function processDeparturesForSync(\App\Models\Tour $tour, array $departures): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped_past' => 0];

        // Get sync settings
        $syncSettings = \App\Models\Setting::get('sync_settings', [
            'skip_past_periods' => true,
            'past_period_threshold_days' => 0,
        ]);
        
        $skipPastPeriods = $syncSettings['skip_past_periods'] ?? true;
        $thresholdDays = $syncSettings['past_period_threshold_days'] ?? 0;
        $thresholdDate = now()->subDays($thresholdDays)->toDateString();

        foreach ($departures as $dep) {
            $departureDate = $dep['departure_date'] ?? $dep['start_date'] ?? null;
            if (!$departureDate) continue;
            
            // Skip past periods if enabled
            if ($skipPastPeriods && $departureDate < $thresholdDate) {
                $result['skipped_past']++;
                continue;
            }

            $externalId = $dep['external_id'] ?? null;

            // Find existing period
            $period = \App\Models\Period::where('tour_id', $tour->id)
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
                $period = new \App\Models\Period();
                $period->tour_id = $tour->id;
            }

            // Fill period fields from transformed data (same as SyncToursJob - no hardcode)
            $fillableFields = $period->getFillable();
            $periodFields = [];
            
            // Map departure_date to start_date if needed
            if (isset($dep['departure_date']) && !isset($dep['start_date'])) {
                $dep['start_date'] = $dep['departure_date'];
            }
            
            foreach ($dep as $field => $value) {
                if ($value === null) continue;
                if (in_array($field, $fillableFields) || empty($fillableFields)) {
                    $periodFields[$field] = $value;
                }
            }
            
            // Auto-generate period_code if not provided
            if (empty($periodFields['period_code']) && empty($period->period_code) && $departureDate) {
                $periodFields['period_code'] = 'P' . date('ymd', strtotime($departureDate));
            }
            
            // Map status if provided
            if (isset($dep['status'])) {
                $periodFields['status'] = $this->mapPeriodStatus($dep['status']);
            }
            
            // Sanitize: available/capacity cannot be negative
            if (isset($periodFields['available']) && $periodFields['available'] < 0) {
                $periodFields['available'] = 0;
            }
            if (isset($periodFields['capacity']) && $periodFields['capacity'] < 0) {
                $periodFields['capacity'] = 0;
            }

            $period->fill($periodFields);
            $period->save();

            // Create/update offer for pricing (same as SyncToursJob)
            if (isset($dep['price_adult'])) {
                $this->processOfferForSync($period, $dep);
            }

            $isNew ? $result['created']++ : $result['updated']++;
        }

        return $result;
    }

    /**
     * Process offer/pricing for a period (same logic as SyncToursJob)
     */
    protected function processOfferForSync(\App\Models\Period $period, array $depData): void
    {
        $offer = \App\Models\Offer::firstOrNew(['period_id' => $period->id]);
        
        // Fill offer fields from transformed data (no hardcode - same as SyncToursJob)
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
     * Process itinerary for sync (same logic as SyncToursJob)
     */
    protected function processItineraryForSync(\App\Models\Tour $tour, array $itinData): void
    {
        $dayNumber = $itinData['day_number'] ?? null;
        $externalId = $itinData['external_id'] ?? null;

        if (!$dayNumber && !$externalId) {
            return;
        }

        // Find existing itinerary
        $itinerary = \App\Models\TourItinerary::where('tour_id', $tour->id)
            ->where(function ($q) use ($dayNumber, $externalId) {
                if ($externalId) {
                    $q->where('external_id', $externalId);
                } else {
                    $q->where('day_number', $dayNumber);
                }
            })
            ->first();

        if (!$itinerary) {
            $itinerary = new \App\Models\TourItinerary();
            $itinerary->tour_id = $tour->id;
        }

        // Fill itinerary fields from transformed data (no hardcode - same as SyncToursJob)
        $fillableFields = $itinerary->getFillable();
        $itinFields = [];
        
        // Fields that should skip when empty (numeric fields)
        $numericFields = ['hotel_star', 'day_number', 'sort_order'];
        
        foreach ($itinData as $field => $value) {
            if ($value === null) continue;
            if ($value === '' && in_array($field, $numericFields)) {
                continue;
            }
            if (in_array($field, $fillableFields) || empty($fillableFields)) {
                $itinFields[$field] = $value;
            }
        }
        
        // Set data_source
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
     * Map period status to valid enum value (same as SyncToursJob)
     */
    protected function mapPeriodStatus(?string $status): string
    {
        if (!$status) {
            return \App\Models\Period::STATUS_OPEN;
        }

        $statusMap = [
            'open' => \App\Models\Period::STATUS_OPEN,
            'available' => \App\Models\Period::STATUS_OPEN,
            'active' => \App\Models\Period::STATUS_OPEN,
            'closed' => \App\Models\Period::STATUS_CLOSED,
            'inactive' => \App\Models\Period::STATUS_CLOSED,
            'sold_out' => \App\Models\Period::STATUS_SOLD_OUT,
            'full' => \App\Models\Period::STATUS_SOLD_OUT,
            'cancelled' => \App\Models\Period::STATUS_CANCELLED,
            'canceled' => \App\Models\Period::STATUS_CANCELLED,
        ];

        return $statusMap[strtolower($status)] ?? \App\Models\Period::STATUS_OPEN;
    }

    /**
     * Resolve lookup fields (country code → ID, transport code → ID)
     */
    protected function resolveLookups(array $data): array
    {
        // Resolve country
        if (isset($data['primary_country_id']) && !is_numeric($data['primary_country_id'])) {
            $country = \App\Models\Country::where('iso2', $data['primary_country_id'])
                ->orWhere('iso3', $data['primary_country_id'])
                ->orWhere('name', $data['primary_country_id'])
                ->orWhere('name_th', $data['primary_country_id'])
                ->first();
            $data['primary_country_id'] = $country?->id;
        }

        // Resolve transport
        if (isset($data['transport_id']) && !is_numeric($data['transport_id'])) {
            $transport = \App\Models\Transport::where('code', $data['transport_id'])
                ->orWhere('name', $data['transport_id'])
                ->first();
            $data['transport_id'] = $transport?->id;
        }

        return $data;
    }

    /**
     * Filter data to only include fillable fields
     */
    protected function filterFillable($model, array $data): array
    {
        $fillable = $model->getFillable();
        $filtered = [];

        foreach ($data as $key => $value) {
            if ($value === null) continue;
            if ($value === '' && in_array($key, ['hotel_star', 'duration_days', 'duration_nights', 'primary_country_id', 'transport_id'])) {
                continue;
            }
            if (in_array($key, $fillable) || empty($fillable)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Fetch and sync periods from separate API endpoint (Two-Phase Sync)
     * Based on SyncPeriodsJob logic
     */
    protected function fetchAndSyncPeriodsFromApi(
        \App\Models\Tour $tour, 
        $adapter, 
        array $endpoints, 
        int $wholesalerId
    ): array {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        
        $periodsEndpoint = $endpoints['periods'] ?? null;
        if (!$periodsEndpoint) {
            Log::warning('Two-Phase Sync: No periods endpoint configured', [
                'tour_id' => $tour->id,
                'wholesaler_id' => $wholesalerId,
            ]);
            return $result;
        }

        // Build URL - replace placeholders
        $url = str_replace(
            ['{external_id}', '{tour_code}', '{wholesaler_tour_code}'],
            [$tour->external_id ?? '', $tour->tour_code ?? '', $tour->wholesaler_tour_code ?? ''],
            $periodsEndpoint
        );

        try {
            // Fetch periods from API
            $fetchResult = $adapter->fetchPeriods($url);

            if (!$fetchResult->success) {
                Log::error('Two-Phase Sync: Failed to fetch periods', [
                    'tour_id' => $tour->id,
                    'url' => $url,
                    'error' => $fetchResult->errorMessage ?? 'Unknown error',
                ]);
                return $result;
            }

            Log::info('Two-Phase Sync: Fetched periods from API', [
                'tour_id' => $tour->id,
                'url' => $url,
                'periods_count' => count($fetchResult->periods ?? []),
            ]);

            // Get field mappings for periods (departure section)
            $mappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
                ->where('section_name', 'departure')
                ->where('is_active', true)
                ->get();
            
            Log::debug('Two-Phase Sync: Got departure mappings', [
                'tour_id' => $tour->id,
                'mappings_count' => $mappings->count(),
            ]);

            // Get sync settings
            $syncSettings = \App\Models\Setting::get('sync_settings', [
                'skip_past_periods' => true,
                'past_period_threshold_days' => 0,
            ]);
            $skipPastPeriods = $syncSettings['skip_past_periods'] ?? true;
            $thresholdDays = $syncSettings['past_period_threshold_days'] ?? 0;
            $thresholdDate = now()->subDays($thresholdDays)->toDateString();

            // Process each period
            foreach ($fetchResult->periods ?? [] as $rawPeriod) {
                try {
                    // Transform period data using mappings
                    $periodData = [];
                    foreach ($mappings as $mapping) {
                        $fieldPath = $mapping->their_field_path ?? $mapping->their_field ?? null;
                        if (empty($fieldPath)) {
                            continue;
                        }
                        
                        // Strip array prefix like "periods[]." or "Periods[]." since we're already iterating over the array
                        $cleanPath = preg_replace('/^[Pp]eriods\[\]\./', '', $fieldPath);
                        $cleanPath = preg_replace('/^[Ss]chedules\[\]\./', '', $cleanPath);
                        $cleanPath = preg_replace('/^[Dd]epartures\[\]\./', '', $cleanPath);
                        
                        $value = $this->extractValueFromPath($rawPeriod, $cleanPath);
                        if ($value !== null) {
                            $value = $this->applyTransformForSync($value, $mapping->transform_type, $mapping->transform_config);
                        }
                        $periodData[$mapping->our_field] = $value;
                    }

                    // Map departure_date to start_date if needed
                    if (!empty($periodData['departure_date']) && empty($periodData['start_date'])) {
                        $periodData['start_date'] = $periodData['departure_date'];
                    }

                    // Skip if no start date
                    if (empty($periodData['start_date'])) {
                        $result['skipped']++;
                        continue;
                    }

                    // Skip past periods if enabled
                    if ($skipPastPeriods && $periodData['start_date'] < $thresholdDate) {
                        $result['skipped']++;
                        continue;
                    }

                    // Generate period code if not provided
                    $periodCode = $periodData['period_code'] ?? $periodData['external_id'] ?? null;
                    if (!$periodCode) {
                        $periodCode = $tour->tour_code . '-' . date('Ymd', strtotime($periodData['start_date']));
                    }

                    // Find or create period
                    $period = \App\Models\Period::where('tour_id', $tour->id)
                        ->where(function($q) use ($periodCode, $periodData) {
                            $q->where('period_code', $periodCode)
                              ->orWhere('start_date', $periodData['start_date']);
                        })
                        ->first();

                    $isNew = !$period;
                    if (!$period) {
                        $period = new \App\Models\Period();
                    }

                    $fillData = [
                        'tour_id' => $tour->id,
                        'external_id' => $periodData['external_id'] ?? null,
                        'period_code' => $periodCode,
                        'start_date' => $periodData['start_date'],
                        'end_date' => $periodData['end_date'] ?? $periodData['start_date'],
                        'capacity' => $periodData['capacity'] ?? 30,
                        'booked' => $periodData['booked'] ?? 0,
                        'available' => $periodData['available'] ?? ($periodData['capacity'] ?? 30) - ($periodData['booked'] ?? 0),
                        'status' => $this->mapPeriodStatus($periodData['status'] ?? null),
                        'is_visible' => $periodData['is_visible'] ?? true,
                        'sale_status' => $periodData['sale_status'] ?? 'available',
                    ];

                    $period->fill($fillData);
                    $period->save();

                    // Sync offer (pricing)
                    if (!empty($periodData['price_adult']) || !empty($periodData['price'])) {
                        $this->processOfferForSync($period, $periodData);
                    }

                    $isNew ? $result['created']++ : $result['updated']++;

                } catch (\Exception $e) {
                    Log::error('Two-Phase Sync: Error processing period', [
                        'tour_id' => $tour->id,
                        'error' => $e->getMessage(),
                    ]);
                    $result['skipped']++;
                }
            }

        } catch (\Exception $e) {
            Log::error('Two-Phase Sync: Exception fetching periods', [
                'tour_id' => $tour->id,
                'url' => $url ?? 'N/A',
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Fetch and sync itineraries from separate API endpoint (Two-Phase Sync)
     */
    protected function fetchAndSyncItinerariesFromApi(
        \App\Models\Tour $tour, 
        $adapter, 
        array $endpoints, 
        int $wholesalerId
    ): void {
        $itinerariesEndpoint = $endpoints['itineraries'] ?? null;
        if (!$itinerariesEndpoint) {
            Log::debug('Two-Phase Sync: No itineraries endpoint configured', [
                'tour_id' => $tour->id,
                'wholesaler_id' => $wholesalerId,
            ]);
            return;
        }

        // Build URL - replace placeholders
        $url = str_replace(
            ['{external_id}', '{tour_code}', '{wholesaler_tour_code}'],
            [$tour->external_id ?? '', $tour->tour_code ?? '', $tour->wholesaler_tour_code ?? ''],
            $itinerariesEndpoint
        );

        try {
            // Fetch itineraries from API
            $fetchResult = $adapter->fetchItineraries($url);

            Log::info('Two-Phase Sync: Fetched itineraries from API', [
                'tour_id' => $tour->id,
                'url' => $url,
                'success' => $fetchResult->success,
                'itineraries_count' => count($fetchResult->itineraries ?? []),
            ]);

            if (!$fetchResult->success) {
                Log::error('Two-Phase Sync: Failed to fetch itineraries', [
                    'tour_id' => $tour->id,
                    'url' => $url,
                    'error' => $fetchResult->error ?? 'Unknown error',
                ]);
                return;
            }

            // Get field mappings for itineraries
            $mappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
                ->where('section_name', 'itinerary')
                ->where('is_active', true)
                ->get();

            // Process each itinerary
            foreach ($fetchResult->itineraries ?? [] as $rawItinerary) {
                try {
                    // Transform itinerary data using mappings
                    $itinData = [];
                    foreach ($mappings as $mapping) {
                        $fieldPath = $mapping->their_field_path ?? $mapping->their_field ?? null;
                        if (empty($fieldPath)) {
                            continue;
                        }
                        
                        // Strip array prefix like "itinerary[]." or "Itinerary[]." since we're already iterating over the array
                        $cleanPath = preg_replace('/^[Ii]tinerary\[\]\./', '', $fieldPath);
                        $cleanPath = preg_replace('/^[Ii]tineraries\[\]\./', '', $cleanPath);
                        $cleanPath = preg_replace('/^[Dd]ays\[\]\./', '', $cleanPath);
                        $cleanPath = preg_replace('/^[Pp]rograms\[\]\./', '', $cleanPath);
                        
                        $value = $this->extractValueFromPath($rawItinerary, $cleanPath);
                        if ($value !== null) {
                            $value = $this->applyTransformForSync($value, $mapping->transform_type, $mapping->transform_config);
                        }
                        $itinData[$mapping->our_field] = $value;
                    }
                    
                    Log::debug('Two-Phase Sync: Transformed itinerary', [
                        'tour_id' => $tour->id,
                        'raw_keys' => array_keys($rawItinerary),
                        'itinData' => $itinData,
                    ]);

                    // Process itinerary (create/update)
                    $this->processItineraryForSync($tour, $itinData);

                } catch (\Exception $e) {
                    Log::error('Two-Phase Sync: Error processing itinerary', [
                        'tour_id' => $tour->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Two-Phase Sync: Exception fetching itineraries', [
                'tour_id' => $tour->id,
                'url' => $url ?? 'N/A',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract value from nested array using dot notation path
     */
    protected function extractValueFromPath(array $data, ?string $path): mixed
    {
        if (empty($path)) {
            return null;
        }
        
        $parts = explode('.', $path);
        $value = $data;

        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Apply transformation to value (same as SyncPeriodsJob)
     */
    protected function applyTransformForSync(mixed $value, ?string $type, ?array $config): mixed
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
     * Process pending queue jobs manually (for debugging/dev)
     */
    public function processQueue(Request $request): JsonResponse
    {
        $maxJobs = $request->input('max', 5);
        $processed = 0;
        
        // Process jobs synchronously
        while ($processed < $maxJobs) {
            $job = DB::table('jobs')->orderBy('id')->first();
            if (!$job) break;
            
            try {
                \Artisan::call('queue:work', [
                    '--once' => true,
                    '--quiet' => true,
                ]);
                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to process queue job', ['error' => $e->getMessage()]);
                break;
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => "Processed {$processed} jobs",
            'data' => [
                'processed' => $processed,
                'remaining' => DB::table('jobs')->count(),
            ],
        ]);
    }
}

