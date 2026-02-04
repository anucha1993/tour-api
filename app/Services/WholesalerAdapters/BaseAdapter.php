<?php

namespace App\Services\WholesalerAdapters;

use App\Models\OutboundApiLog;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\Contracts\AdapterInterface;
use App\Services\WholesalerAdapters\Contracts\DTOs\AvailabilityResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\BookingResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\HoldResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\SyncResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base adapter with common functionality for all wholesaler adapters
 * 
 * Provides:
 * - HTTP client setup with auth
 * - Retry logic with exponential backoff
 * - Request/response logging
 * - Error handling
 */
abstract class BaseAdapter implements AdapterInterface
{
    protected WholesalerApiConfig $config;
    protected int $wholesalerId;
    protected ?int $currentSyncLogId = null;

    public function __construct(WholesalerApiConfig $config)
    {
        $this->config = $config;
        $this->wholesalerId = $config->wholesaler_id;
    }

    // ═══════════════════════════════════════════════════════════
    // HTTP CLIENT
    // ═══════════════════════════════════════════════════════════

    /**
     * Get configured HTTP client
     */
    protected function httpClient(): PendingRequest
    {
        $client = Http::baseUrl($this->config->api_base_url)
            ->timeout($this->config->request_timeout_seconds)
            ->connectTimeout($this->config->connect_timeout_seconds)
            ->withHeaders($this->getDefaultHeaders());

        // Apply authentication
        return $this->applyAuth($client);
    }

    /**
     * Get default headers
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'NextTrip/1.0',
        ];
    }

    /**
     * Apply authentication to HTTP client
     */
    protected function applyAuth(PendingRequest $client): PendingRequest
    {
        $credentials = $this->config->auth_credentials;

        return match ($this->config->auth_type) {
            'api_key' => $client->withHeaders([
                $this->config->auth_header_name => $credentials['api_key'] ?? '',
            ]),
            'bearer' => $client->withToken($credentials['token'] ?? ''),
            'basic' => $client->withBasicAuth(
                $credentials['username'] ?? '',
                $credentials['password'] ?? ''
            ),
            'oauth2' => $this->applyOAuth2($client, $credentials),
            'custom' => $this->applyCustomHeaders($client, $credentials),
            default => $client,
        };
    }

    /**
     * Apply custom headers authentication (HTTP Headers)
     */
    protected function applyCustomHeaders(PendingRequest $client, ?array $credentials): PendingRequest
    {
        if (!$credentials || !isset($credentials['headers'])) {
            return $client;
        }

        $headersData = $credentials['headers'];
        $headers = [];
        
        // Support both formats:
        // 1. Array of objects: [{"key": "X-API-Key", "value": "xxx"}, ...]
        // 2. Simple object: {"X-API-Key": "xxx", "Authorization": "Bearer yyy"}
        if (is_array($headersData)) {
            // Check if it's array of objects or simple key-value
            $firstItem = reset($headersData);
            
            if (is_array($firstItem) && isset($firstItem['key'])) {
                // Format 1: Array of objects
                foreach ($headersData as $header) {
                    if (isset($header['key']) && isset($header['value']) && !empty($header['key'])) {
                        $headers[$header['key']] = $header['value'];
                    }
                }
            } else {
                // Format 2: Simple key-value object
                foreach ($headersData as $key => $value) {
                    if (!empty($key) && is_string($value)) {
                        $headers[$key] = $value;
                    }
                }
            }
        }

        return $client->withHeaders($headers);
    }

    /**
     * Apply OAuth2 authentication (Client Credentials Flow)
     * 
     * Supports:
     * - Pre-existing access_token
     * - Client Credentials flow (client_id + client_secret + token_url)
     * - Custom API headers after getting token
     */
    protected function applyOAuth2(PendingRequest $client, ?array $credentials): PendingRequest
    {
        // If we already have an access token, use it
        if (!empty($credentials['access_token'])) {
            $client = $client->withToken($credentials['access_token']);
            return $this->applyOAuth2ApiHeaders($client, $credentials);
        }

        // Check if we have oauth_fields (new format) or client_id/client_secret (old format)
        $hasOAuthFields = !empty($credentials['oauth_fields']) && is_array($credentials['oauth_fields']);
        $hasLegacyCredentials = !empty($credentials['client_id']) && !empty($credentials['client_secret']);
        $hasTokenUrl = !empty($credentials['token_url']);

        if ($hasTokenUrl && ($hasOAuthFields || $hasLegacyCredentials)) {
            $token = $this->getOAuth2Token($credentials);
            if ($token) {
                $client = $client->withToken($token);
                return $this->applyOAuth2ApiHeaders($client, $credentials);
            }
        }

        Log::warning('OAuth2: No valid credentials found', [
            'wholesaler_id' => $this->wholesalerId,
            'has_oauth_fields' => $hasOAuthFields,
            'has_legacy_credentials' => $hasLegacyCredentials,
            'has_token_url' => $hasTokenUrl,
        ]);

        return $client;
    }

    /**
     * Apply API headers for OAuth2 requests (after getting token)
     * These are custom headers to send with API requests (not token requests)
     */
    protected function applyOAuth2ApiHeaders(PendingRequest $client, ?array $credentials): PendingRequest
    {
        $apiHeaders = $credentials['api_headers'] ?? [];
        
        if (empty($apiHeaders) || !is_array($apiHeaders)) {
            return $client;
        }
        
        // Support both formats:
        // 1. Array of objects: [{"key": "User-Agent", "value": "MyApp/1.0"}, ...]
        // 2. Simple object: {"User-Agent": "MyApp/1.0", ...}
        $headers = [];
        $firstItem = reset($apiHeaders);
        
        if (is_array($firstItem) && isset($firstItem['key'])) {
            // Format 1: Array of objects
            foreach ($apiHeaders as $header) {
                if (!empty($header['key'])) {
                    $headers[$header['key']] = $header['value'] ?? '';
                }
            }
        } else {
            // Format 2: Simple key-value object
            foreach ($apiHeaders as $key => $value) {
                if (!empty($key) && is_string($value)) {
                    $headers[$key] = $value;
                }
            }
        }
        
        if (!empty($headers)) {
            return $client->withHeaders($headers);
        }
        
        return $client;
    }

    /**
     * Get OAuth2 access token using custom request body and headers
     * 
     * Uses oauth_fields array to build request body with custom field names
     * Uses token_headers object to build request headers for token request
     * Supports any field names (grant_type, client_id, clientId, etc.)
     * Also supports legacy format with client_id, client_secret, grant_type directly
     */
    protected function getOAuth2Token(array $credentials): ?string
    {
        $tokenUrl = $credentials['token_url'];
        $oauthBody = $credentials['oauth_fields'] ?? $credentials['oauth_body'] ?? [];
        $oauthHeaders = $credentials['token_headers'] ?? $credentials['oauth_headers'] ?? [];
        $responseTokenField = $credentials['response_token_field'] ?? 'access_token';

        // Check cache first
        $cacheKey = "oauth2_token_{$this->wholesalerId}";
        $cachedToken = cache($cacheKey);
        
        if ($cachedToken) {
            Log::debug('OAuth2: Using cached token', ['wholesaler_id' => $this->wholesalerId]);
            return $cachedToken;
        }

        try {
            // Build POST body from oauth_body array (new format)
            $postBody = [];
            
            if (!empty($oauthBody) && is_array($oauthBody)) {
                // New format: oauth_body array with key-value pairs
                foreach ($oauthBody as $field) {
                    if (!empty($field['key'])) {
                        $postBody[$field['key']] = $field['value'] ?? '';
                    }
                }
            } else {
                // Legacy format: client_id, client_secret, grant_type directly in credentials
                $postBody = [
                    'grant_type' => $credentials['grant_type'] ?? 'client_credentials',
                    'client_id' => $credentials['client_id'] ?? '',
                    'client_secret' => $credentials['client_secret'] ?? '',
                ];
            }

            // Build headers from token_headers
            // Support both formats:
            // 1. Array of objects: [{"key": "Content-Type", "value": "application/json"}, ...]
            // 2. Simple object: {"Content-Type": "application/json", ...}
            $headers = [];
            if (!empty($oauthHeaders) && is_array($oauthHeaders)) {
                $firstItem = reset($oauthHeaders);
                
                if (is_array($firstItem) && isset($firstItem['key'])) {
                    // Format 1: Array of objects
                    foreach ($oauthHeaders as $header) {
                        if (!empty($header['key'])) {
                            $headers[$header['key']] = $header['value'] ?? '';
                        }
                    }
                } else {
                    // Format 2: Simple key-value object
                    foreach ($oauthHeaders as $key => $value) {
                        if (!empty($key) && is_string($value)) {
                            $headers[$key] = $value;
                        }
                    }
                }
            }

            Log::info('OAuth2: Requesting new access token', [
                'wholesaler_id' => $this->wholesalerId,
                'token_url' => $tokenUrl,
                'body_fields' => array_keys($postBody),
                'header_fields' => array_keys($headers),
            ]);

            $httpClient = Http::timeout(30);
            
            // Add custom headers if any
            if (!empty($headers)) {
                $httpClient = $httpClient->withHeaders($headers);
            }

            $response = $httpClient->post($tokenUrl, $postBody);

            if ($response->successful()) {
                $data = $response->json();
                
                // Get token using custom field name
                $accessToken = $this->getNestedValue($data, $responseTokenField);
                $expiresIn = $data['expires_in'] ?? $data['expiresIn'] ?? 3600;

                if ($accessToken) {
                    // Cache token with 5 minute buffer before expiry
                    $cacheTtl = max(60, $expiresIn - 300);
                    cache([$cacheKey => $accessToken], $cacheTtl);

                    Log::info('OAuth2: Token obtained successfully', [
                        'wholesaler_id' => $this->wholesalerId,
                        'expires_in' => $expiresIn,
                    ]);

                    return $accessToken;
                }
            }

            Log::error('OAuth2: Failed to get token', [
                'wholesaler_id' => $this->wholesalerId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('OAuth2: Exception getting token', [
                'wholesaler_id' => $this->wholesalerId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get nested value from array using dot notation
     * e.g., "data.access_token" or just "access_token"
     */
    protected function getNestedValue(array $data, string $path): ?string
    {
        $keys = explode('.', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }
        
        return is_string($value) ? $value : null;
    }

    // ═══════════════════════════════════════════════════════════
    // REQUEST WITH RETRY
    // ═══════════════════════════════════════════════════════════

    /**
     * Make request with retry logic and logging
     */
    protected function request(
        string $method,
        string $endpoint,
        array $data = [],
        string $action = 'fetch_tours'
    ): array {
        $startTime = microtime(true);
        
        // Build full URL
        // If endpoint is already a full URL (starts with http), use it directly
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            $fullUrl = $endpoint;
        } else {
            // Otherwise, prepend base URL - don't add trailing slash if endpoint is empty
            $fullUrl = $this->config->api_base_url;
            if ($endpoint !== '') {
                $fullUrl .= '/' . ltrim($endpoint, '/');
            }
        }
        
        // Create log entry
        $log = OutboundApiLog::log(
            $this->wholesalerId,
            $action,
            $fullUrl,
            strtoupper($method),
            ['body' => $data]
        );

        if ($this->currentSyncLogId) {
            $log->update(['sync_log_id' => $this->currentSyncLogId]);
        }

        $attempts = 0;
        $maxAttempts = $this->config->retry_attempts;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            $attempts++;
            
            try {
                $response = $this->executeRequest($method, $endpoint, $data);
                $timeMs = (int) ((microtime(true) - $startTime) * 1000);

                if ($response->successful()) {
                    $log->recordResponse(
                        $response->status(),
                        $response->json() ?? [],
                        $timeMs
                    );
                    return $response->json() ?? [];
                }

                // Non-retryable error codes
                if (in_array($response->status(), [400, 401, 403, 404, 422])) {
                    $log->recordResponse(
                        $response->status(),
                        $response->json() ?? ['error' => $response->body()],
                        $timeMs
                    );
                    throw new \Exception(
                        "API Error: " . ($response->json()['message'] ?? $response->body()),
                        $response->status()
                    );
                }

                // Retryable errors (5xx, etc.)
                $lastException = new \Exception("HTTP {$response->status()}: " . $response->body());

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;
                $log->recordError('timeout', $e->getMessage(), (int) ((microtime(true) - $startTime) * 1000));
            } catch (\Exception $e) {
                $lastException = $e;
                if ($e->getCode() >= 400 && $e->getCode() < 500) {
                    throw $e; // Don't retry client errors
                }
            }

            // Wait before retry (exponential backoff)
            if ($attempts < $maxAttempts) {
                $waitSeconds = min(60, pow(2, $attempts)); // 2, 4, 8, 16... max 60
                Log::warning("Wholesaler API retry", [
                    'wholesaler_id' => $this->wholesalerId,
                    'attempt' => $attempts,
                    'wait_seconds' => $waitSeconds,
                    'error' => $lastException?->getMessage(),
                ]);
                sleep($waitSeconds);
            }
        }

        // All retries failed
        $timeMs = (int) ((microtime(true) - $startTime) * 1000);
        $log->recordError('error', $lastException?->getMessage() ?? 'Unknown error', $timeMs);
        $log->update(['retry_count' => $attempts]);

        throw $lastException ?? new \Exception('Request failed after all retries');
    }

    /**
     * Execute single HTTP request
     */
    protected function executeRequest(string $method, string $endpoint, array $data = []): Response
    {
        // If endpoint is a full URL, use it directly without baseUrl
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            $client = Http::timeout($this->config->request_timeout_seconds)
                ->connectTimeout($this->config->connect_timeout_seconds)
                ->withHeaders($this->getDefaultHeaders());
            
            // Apply auth
            $client = $this->applyAuth($client);
            
            // For full URL, pass the full URL directly
            $url = $endpoint;
        } else {
            $client = $this->httpClient();
            $url = $endpoint;
        }

        /** @var Response $response */
        $response = match (strtoupper($method)) {
            'GET' => $client->get($url, $data),
            'POST' => $client->post($url, $data),
            'PUT' => $client->put($url, $data),
            'PATCH' => $client->patch($url, $data),
            'DELETE' => $client->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported method: $method"),
        };
        
        return $response;
    }

    // ═══════════════════════════════════════════════════════════
    // DEFAULT IMPLEMENTATIONS
    // ═══════════════════════════════════════════════════════════

    /**
     * Health check - ping the API
     */
    public function healthCheck(): bool
    {
        try {
            $startTime = microtime(true);
            $response = $this->httpClient()->get('/health');
            $timeMs = (int) ((microtime(true) - $startTime) * 1000);

            // Log health check
            OutboundApiLog::log(
                $this->wholesalerId,
                'health_check',
                $this->config->api_base_url . '/health',
                'GET'
            )->recordResponse($response->status(), [], $timeMs);

            // Update config
            $this->config->update([
                'last_health_check_at' => now(),
                'last_health_check_status' => $response->successful(),
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            $this->config->update([
                'last_health_check_at' => now(),
                'last_health_check_status' => false,
            ]);
            return false;
        }
    }

    /**
     * Get adapter configuration
     */
    public function getConfig(): array
    {
        return [
            'wholesaler_id' => $this->wholesalerId,
            'api_base_url' => $this->config->api_base_url,
            'api_version' => $this->config->api_version,
            'api_format' => $this->config->api_format,
            'auth_type' => $this->config->auth_type,
            'sync_enabled' => $this->config->sync_enabled,
            'sync_method' => $this->config->sync_method,
            'supports' => [
                'availability_check' => $this->config->supports_availability_check,
                'hold_booking' => $this->config->supports_hold_booking,
                'modify_booking' => $this->config->supports_modify_booking,
            ],
        ];
    }

    /**
     * Get wholesaler ID
     */
    public function getWholesalerId(): int
    {
        return $this->wholesalerId;
    }

    /**
     * Set current sync log ID for linking
     */
    public function setSyncLogId(int $syncLogId): void
    {
        $this->currentSyncLogId = $syncLogId;
    }

    // ═══════════════════════════════════════════════════════════
    // ABSTRACT METHODS - Must be implemented by specific adapters
    // ═══════════════════════════════════════════════════════════

    /**
     * Fetch tours from wholesaler - must be implemented
     */
    abstract public function fetchTours(?string $cursor = null): SyncResult;

    /**
     * Fetch single tour detail - must be implemented
     */
    abstract public function fetchTourDetail(string $code): ?array;

    // ═══════════════════════════════════════════════════════════
    // DEFAULT OUTBOUND IMPLEMENTATIONS (can be overridden)
    // ═══════════════════════════════════════════════════════════

    /**
     * Default ACK implementation - can be overridden
     */
    public function acknowledgeSynced(array $tourCodes, string $syncId): bool
    {
        // Default: do nothing (use cursor-based sync)
        return true;
    }

    /**
     * Default availability check - must be overridden if supported
     */
    public function checkAvailability(
        string $code,
        string $date,
        int $paxAdult,
        int $paxChild = 0
    ): AvailabilityResult {
        return AvailabilityResult::error('Availability check not implemented for this wholesaler');
    }

    /**
     * Default hold booking - must be overridden if supported
     */
    public function holdBooking(
        string $code,
        string $date,
        int $paxAdult,
        int $paxChild = 0
    ): HoldResult {
        return HoldResult::failed('Hold booking not implemented for this wholesaler');
    }

    /**
     * Default confirm booking - must be overridden if supported
     */
    public function confirmBooking(
        string $holdId,
        array $passengers,
        array $paymentInfo
    ): BookingResult {
        return BookingResult::failed('Confirm booking not implemented for this wholesaler');
    }

    /**
     * Default cancel booking - must be overridden if supported
     */
    public function cancelBooking(string $bookingRef, string $reason): BookingResult
    {
        return BookingResult::failed('Cancel booking not implemented for this wholesaler');
    }

    /**
     * Default modify booking - must be overridden if supported
     */
    public function modifyBooking(string $bookingRef, array $changes): BookingResult
    {
        return BookingResult::failed('Modify booking not implemented for this wholesaler');
    }
}
