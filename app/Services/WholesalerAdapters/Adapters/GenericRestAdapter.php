<?php

namespace App\Services\WholesalerAdapters\Adapters;

use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\BaseAdapter;
use App\Services\WholesalerAdapters\Contracts\DTOs\AvailabilityResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\BookingResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\HoldResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\SyncResult;
use App\Services\WholesalerAdapters\DTOs\PeriodsResult;
use App\Services\WholesalerAdapters\DTOs\ItinerariesResult;

/**
 * Generic REST API Adapter
 * 
 * Works with standard REST APIs that follow common conventions.
 * Can be extended for specific wholesalers.
 */
class GenericRestAdapter extends BaseAdapter
{
    /**
     * Default endpoint paths (can be customized via config)
     * Use empty string '' to use base_url directly without path suffix
     */
    protected array $endpoints = [
        'tours' => '',  // Empty = use base URL directly (some APIs like Zego)
        'tour_detail' => '/{code}',
        'availability' => '/{code}/availability',
        'hold' => '/bookings/hold',
        'confirm' => '/bookings/confirm',
        'cancel' => '/bookings/{ref}/cancel',
        'modify' => '/bookings/{ref}',
        'ack' => '/sync/acknowledge',
        'health' => '/health',
    ];

    public function __construct(WholesalerApiConfig $config)
    {
        parent::__construct($config);

        // Override endpoints from config if available
        $credentials = $config->auth_credentials;
        if (isset($credentials['endpoints']) && is_array($credentials['endpoints'])) {
            $this->endpoints = array_merge($this->endpoints, $credentials['endpoints']);
        }
    }

    /**
     * Fetch tours from API
     */
    /**
     * Fetch tours from API with pagination support
     * 
     * Supports multiple pagination types via auth_credentials.pagination config:
     * - cursor:    { type: 'cursor', param: 'cursor' }
     * - page:      { type: 'page', page_param: 'page', per_page_param: 'per_page', per_page: 50 }
     * - offset:    { type: 'offset', offset_param: 'offset', limit_param: 'limit', limit: 50 }
     * - post_bulk: { type: 'post_bulk', body: { limit_page: 300, ... } }  — POST with body params, single request
     * - none:      No pagination (default) — single GET returns all data
     */
    public function fetchTours(?string $cursor = null): SyncResult
    {
        try {
            $credentials = $this->config->auth_credentials;
            $paginationConfig = $credentials['pagination'] ?? [];
            $paginationType = $paginationConfig['type'] ?? 'none';

            $params = [];
            $httpMethod = 'GET';

            // Build pagination params based on type
            switch ($paginationType) {
                case 'page':
                    $pageParam = $paginationConfig['page_param'] ?? 'page';
                    $perPageParam = $paginationConfig['per_page_param'] ?? 'per_page';
                    $perPage = (int) ($paginationConfig['per_page'] ?? 0);
                    $currentPage = $cursor ? (int) $cursor : 1;
                    $params[$pageParam] = $currentPage;
                    // Only send per_page param when explicitly configured (> 0)
                    if ($perPage > 0 && $perPageParam) {
                        $params[$perPageParam] = $perPage;
                    }
                    break;

                case 'offset':
                    $offsetParam = $paginationConfig['offset_param'] ?? 'offset';
                    $limitParam = $paginationConfig['limit_param'] ?? 'limit';
                    $limit = (int) ($paginationConfig['limit'] ?? 50);
                    $currentOffset = $cursor ? (int) $cursor : 0;
                    $params[$offsetParam] = $currentOffset;
                    $params[$limitParam] = $limit;
                    break;

                case 'cursor':
                    $cursorParam = $paginationConfig['param'] ?? 'cursor';
                    if ($cursor) {
                        $params[$cursorParam] = $cursor;
                    }
                    break;

                case 'post_bulk':
                    // POST request with body params (e.g. limit_page=300) — single request fetches all
                    $httpMethod = 'POST';
                    $params = $paginationConfig['body'] ?? [];
                    break;

                default: // 'none'
                    // No pagination params
                    break;
            }

            $response = $this->request($httpMethod, $this->endpoints['tours'], $params, 'fetch_tours');

            // Handle various response formats:
            // 1. Direct array: [{...}, {...}] (Zego format)
            // 2. Wrapped: { data: [...] } or { tours: [...] } or { items: [...] }
            // 3. Double-wrapped: { data: { data: [...], meta: {...} } } (Laravel-style paginated)
            // 4. Object with numeric-string keys: { "1": {...}, "2": {...}, ... } (TTN format)
            if (is_array($response) && isset($response[0]) && is_array($response[0])) {
                // Direct array format (Zego)
                $tours = $response;
            } elseif (
                is_array($response) && !empty($response)
                && !isset($response['data']) && !isset($response['tours']) && !isset($response['items'])
                && $this->isNumericStringKeyedObjectOfArrays($response)
            ) {
                // Object with numeric-string keys (e.g. TTN: {"1":{...},"2":{...},...})
                $tours = array_values($response);
            } else {
                // Wrapped format
                $tours = $response['data'] ?? $response['tours'] ?? $response['items'] ?? [];
                
                // Handle double-wrapped: if $tours is still an assoc array with nested 'data' key
                // e.g., { data: { data: [...], meta: {...} } } → extract inner data
                if (is_array($tours) && !isset($tours[0]) && isset($tours['data']) && is_array($tours['data'])) {
                    // Hoist inner meta/pagination info to response level for pagination detection
                    if (isset($tours['meta']) && !isset($response['meta'])) {
                        $response['meta'] = $tours['meta'];
                    }
                    $tours = $tours['data'];
                }
            }

            // Detect pagination info from response
            $nextCursor = null;
            $hasMore = false;
            $responsePage = null;
            $responseLastPage = null;
            $responsePerPage = null;

            switch ($paginationType) {
                case 'page':
                    $perPage = (int) ($paginationConfig['per_page'] ?? 0);
                    $currentPage = $cursor ? (int) $cursor : 1;

                    // Try to detect last_page / total_pages from various response formats
                    $responseLastPage = $response['last_page']
                        ?? $response['meta']['last_page']
                        ?? $response['meta']['totalPages']
                        ?? $response['pagination']['last_page']
                        ?? $response['pagination']['total_pages']
                        ?? $response['totalPage']
                        ?? $response['total_pages']
                        ?? null;

                    // Also try to compute from total count if last_page not available
                    if ($responseLastPage === null) {
                        $totalCount = $response['total']
                            ?? $response['meta']['total']
                            ?? $response['pagination']['total']
                            ?? $response['totalRecord']
                            ?? $response['count']
                            ?? null;
                        if ($totalCount !== null && $perPage > 0) {
                            $responseLastPage = (int) ceil((int) $totalCount / $perPage);
                        } elseif ($totalCount !== null && count($tours) > 0) {
                            // per_page not set — infer from actual items returned
                            $responseLastPage = (int) ceil((int) $totalCount / count($tours));
                        }
                    }

                    $responsePage = $currentPage;
                    $responsePerPage = $perPage > 0 ? $perPage : count($tours);

                    if ($responseLastPage !== null) {
                        $hasMore = $currentPage < (int) $responseLastPage;
                    } else {
                        // Fallback: if we got items, assume there might be more
                        $hasMore = count($tours) > 0 && $perPage > 0 && count($tours) >= $perPage;
                    }
                    $nextCursor = $hasMore ? (string) ($currentPage + 1) : null;
                    break;

                case 'offset':
                    $limit = (int) ($paginationConfig['limit'] ?? 50);
                    $currentOffset = $cursor ? (int) $cursor : 0;
                    $total = $response['total'] ?? $response['meta']['total'] ?? $response['pagination']['total'] ?? null;

                    if ($total !== null) {
                        $hasMore = ($currentOffset + $limit) < (int) $total;
                    } else {
                        $hasMore = count($tours) >= $limit;
                    }
                    $nextCursor = $hasMore ? (string) ($currentOffset + $limit) : null;
                    break;

                case 'cursor':
                    $nextCursor = $response['next_cursor'] ?? $response['cursor'] ?? $response['pagination']['next'] ?? null;
                    $hasMore = $response['has_more'] ?? $response['hasMore'] ?? ($nextCursor !== null);
                    break;

                case 'post_bulk':
                    // Single request fetched everything — no more pages
                    $hasMore = false;
                    $nextCursor = null;
                    break;

                default: // 'none'
                    $hasMore = false;
                    $nextCursor = null;
                    break;
            }

            return SyncResult::success($tours, $nextCursor, $hasMore, $responsePage, $responseLastPage ? (int) $responseLastPage : null, $responsePerPage);

        } catch (\Exception $e) {
            return SyncResult::failed($e->getMessage(), (string) $e->getCode());
        }
    }

    /**
     * Detect responses shaped like { "1": {...}, "2": {...}, ... } where the
     * top-level object uses numeric-string keys (not zero-indexed) and every
     * value is an array (a tour record). Used to support APIs such as TTN
     * that return object-of-tours instead of a JSON array.
     */
    protected function isNumericStringKeyedObjectOfArrays(array $response): bool
    {
        foreach ($response as $key => $value) {
            if (!ctype_digit((string) $key) || !is_array($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Fetch single tour detail
     */
    public function fetchTourDetail(string $code): ?array
    {
        try {
            // Try periods endpoint first (for two_phase mode with detail URL)
            // Then tour_detail, then fallback to /{code}
            $endpoint = $this->endpoints['periods'] ?? $this->endpoints['tour_detail'] ?? '/{code}';
            
            // Replace various placeholder formats
            $endpoint = str_replace(['{code}', '{tour_id}', '{id}'], $code, $endpoint);
            
            $response = $this->request('GET', $endpoint, [], 'fetch_detail');

            return $response['data'] ?? $response['tour'] ?? $response;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch periods/schedules for a specific tour (Two-Phase Sync)
     * 
     * @param string $endpoint The full endpoint path with placeholders already replaced
     * @return PeriodsResult
     */
    public function fetchPeriods(string $endpoint): PeriodsResult
    {
        try {
            $response = $this->request('GET', $endpoint, [], 'fetch_periods');

            // Handle various response formats:
            // 1. Direct array: [{...}, {...}]
            // 2. Wrapped: { data: [...] } or { schedules: [...] } or { periods: [...] }
            
            // Determine raw data (full response or data wrapper)
            // API response: { status: ..., data: { tour_id: ..., tour_daily: [...], ... } }
            // or: { status: ..., data: [{ tour_id: ..., tour_daily: [...], ... }] }
            $rawData = $response['data'] ?? $response;
            
            // If data is an indexed array with single item, unwrap it
            if (is_array($rawData) && isset($rawData[0]) && is_array($rawData[0]) && count($rawData) === 1) {
                $rawData = $rawData[0];
            }
            
            if (!is_array($rawData)) {
                $rawData = [];
            }
            
            // Extract periods from various possible locations
            if (is_array($response) && isset($response[0]) && is_array($response[0])) {
                // Direct array format
                $periods = $response;
            } else {
                // Wrapped format
                $periods = $response['data'] ?? $response['schedules'] ?? $response['periods'] ?? $response['departures'] ?? [];
            }

            return PeriodsResult::success($periods, $rawData);

        } catch (\Exception $e) {
            return PeriodsResult::failed($e->getMessage(), (string) $e->getCode());
        }
    }

    /**
     * Fetch itineraries for a specific tour (Two-Phase Sync)
     * 
     * @param string $endpoint The full endpoint path with placeholders already replaced
     * @return ItinerariesResult
     */
    public function fetchItineraries(string $endpoint): ItinerariesResult
    {
        try {
            $response = $this->request('GET', $endpoint, [], 'fetch_itineraries');

            // Handle various response formats:
            // 1. Direct array: [{...}, {...}]
            // 2. Wrapped: { data: [...] } or { itineraries: [...] } or { days: [...] }
            if (is_array($response) && isset($response[0]) && is_array($response[0])) {
                // Direct array format
                $itineraries = $response;
            } else {
                // Wrapped format
                $itineraries = $response['data'] ?? $response['itineraries'] ?? $response['days'] ?? $response['programs'] ?? [];
            }

            return ItinerariesResult::success($itineraries);

        } catch (\Exception $e) {
            return ItinerariesResult::failed($e->getMessage(), (string) $e->getCode());
        }
    }

    /**
     * Check availability
     */
    public function checkAvailability(
        string $code,
        string $date,
        int $paxAdult,
        int $paxChild = 0
    ): AvailabilityResult {
        if (!$this->config->supports_availability_check) {
            return AvailabilityResult::error('Availability check not supported');
        }

        try {
            $endpoint = str_replace('{code}', $code, $this->endpoints['availability']);
            
            $response = $this->request('GET', $endpoint, [
                'date' => $date,
                'pax_adult' => $paxAdult,
                'pax_child' => $paxChild,
            ], 'check_availability');

            $available = $response['available'] ?? ($response['remaining_seats'] ?? 0) > 0;
            $seats = $response['remaining_seats'] ?? $response['seats'] ?? 0;
            $priceAdult = $response['price_adult'] ?? $response['price'] ?? 0;
            $priceChild = $response['price_child'] ?? 0;

            if ($available) {
                return AvailabilityResult::available($seats, $priceAdult, $priceChild);
            }

            return AvailabilityResult::unavailable($response['message'] ?? null);

        } catch (\Exception $e) {
            return AvailabilityResult::error($e->getMessage(), (string) $e->getCode());
        }
    }

    /**
     * Hold booking
     */
    public function holdBooking(
        string $code,
        string $date,
        int $paxAdult,
        int $paxChild = 0
    ): HoldResult {
        if (!$this->config->supports_hold_booking) {
            return HoldResult::failed('Hold booking not supported');
        }

        try {
            $response = $this->request('POST', $this->endpoints['hold'], [
                'tour_code' => $code,
                'date' => $date,
                'pax_adult' => $paxAdult,
                'pax_child' => $paxChild,
            ], 'hold');

            $holdId = $response['hold_id'] ?? $response['id'] ?? null;
            $ttl = $response['ttl_minutes'] ?? $response['expires_in'] ?? 15;

            if ($holdId) {
                return HoldResult::success($holdId, $ttl, $response);
            }

            return HoldResult::failed($response['message'] ?? 'Hold failed');

        } catch (\Exception $e) {
            return HoldResult::failed($e->getMessage(), (string) $e->getCode());
        }
    }

    /**
     * Confirm booking
     */
    public function confirmBooking(
        string $holdId,
        array $passengers,
        array $paymentInfo
    ): BookingResult {
        try {
            $response = $this->request('POST', $this->endpoints['confirm'], [
                'hold_id' => $holdId,
                'passengers' => $passengers,
                'payment' => $paymentInfo,
            ], 'confirm');

            $bookingRef = $response['booking_ref'] ?? $response['reference'] ?? $response['id'] ?? null;
            $confirmationNo = $response['confirmation_number'] ?? $response['confirmation_no'] ?? null;

            if ($bookingRef) {
                return BookingResult::success($bookingRef, $confirmationNo, 'confirmed', $response);
            }

            return BookingResult::failed($response['message'] ?? 'Confirmation failed');

        } catch (\Exception $e) {
            return BookingResult::failed($e->getMessage(), (string) $e->getCode());
        }
    }

    /**
     * Cancel booking
     */
    public function cancelBooking(string $bookingRef, string $reason): BookingResult
    {
        try {
            $endpoint = str_replace('{ref}', $bookingRef, $this->endpoints['cancel']);
            
            $response = $this->request('POST', $endpoint, [
                'reason' => $reason,
            ], 'cancel');

            $success = $response['success'] ?? $response['cancelled'] ?? false;
            
            if ($success) {
                return BookingResult::cancelled($bookingRef, $response['refund'] ?? null);
            }

            return BookingResult::failed($response['message'] ?? 'Cancellation failed');

        } catch (\Exception $e) {
            return BookingResult::failed($e->getMessage(), (string) $e->getCode());
        }
    }

    /**
     * Modify booking
     */
    public function modifyBooking(string $bookingRef, array $changes): BookingResult
    {
        if (!$this->config->supports_modify_booking) {
            return BookingResult::failed('Modify booking not supported');
        }

        try {
            $endpoint = str_replace('{ref}', $bookingRef, $this->endpoints['modify']);
            
            $response = $this->request('PUT', $endpoint, $changes, 'modify');

            $success = $response['success'] ?? isset($response['booking_ref']);
            
            if ($success) {
                return BookingResult::success($bookingRef, null, 'modified', $response);
            }

            return BookingResult::failed($response['message'] ?? 'Modification failed');

        } catch (\Exception $e) {
            return BookingResult::failed($e->getMessage(), (string) $e->getCode());
        }
    }

    /**
     * Acknowledge synced tours
     */
    public function acknowledgeSynced(array $tourCodes, string $syncId): bool
    {
        // Only if wholesaler uses ACK callback method
        if ($this->config->sync_method !== 'ack_callback') {
            return true;
        }

        try {
            $response = $this->request('POST', $this->endpoints['ack'], [
                'sync_id' => $syncId,
                'tour_codes' => $tourCodes,
                'status' => 'success',
                'received_at' => now()->toIso8601String(),
            ], 'ack_sync');

            return $response['accepted'] ?? $response['success'] ?? true;

        } catch (\Exception $e) {
            return false;
        }
    }
}
