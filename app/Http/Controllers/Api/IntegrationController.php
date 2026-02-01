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
     * Test API connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'api_base_url' => 'required|url',
            'auth_type' => 'required|in:api_key,oauth2,basic,bearer,custom',
            'auth_credentials' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
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
     */
    public function checkTourCount(int $wholesalerId): JsonResponse
    {
        try {
            // Get wholesaler config
            $config = WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบการตั้งค่า API สำหรับ Wholesaler นี้',
                ], 404);
            }

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
                
                // รวบรวมประเทศ - ใช้ mapping path หรือ fallback
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

            return response()->json([
                'success' => true,
                'message' => "พบทัวร์ทั้งหมด {$tourCount} ทัวร์",
                'data' => [
                    'tour_count' => $tourCount,
                    'tours_with_departures' => $toursWithDepartures,
                    'total_departures' => $totalDepartures,
                    'countries' => array_slice($countries, 0, 10), // แสดงแค่ 10 ประเทศแรก
                    'countries_count' => count($countries),
                    'response_time_ms' => $responseTimeMs,
                    'api_url' => $config->api_base_url,
                    'wholesaler_name' => $wholesaler->name,
                ],
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
     */
    public function testMapping(Request $request, int $wholesalerId): JsonResponse
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
            $departureFields = ['external_id', 'departure_date', 'return_date', 'capacity', 'available_seats', 'status', 'guarantee_status', 'currency', 'price_adult', 'discount_adult', 'price_child', 'discount_child_bed', 'price_child_nobed', 'discount_child_nobed', 'price_infant', 'price_joinland', 'price_single_surcharge', 'discount_single', 'deposit', 'commission_agent', 'commission_sale', 'cancellation_policy', 'refund_policy', 'notes', 'ttl_minutes'];
            
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

            // Dispatch job
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
                
                if ($result) {
                    $url = $result['url'] ?? $result['variants'][0] ?? null;
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
                
                if ($result) {
                    $url = $result['url'] ?? $result['variants'][0] ?? null;
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

