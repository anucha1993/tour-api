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
    public function fetchTours(?string $cursor = null): SyncResult
    {
        try {
            $params = [];
            
            if ($cursor) {
                $params['cursor'] = $cursor;
            }

            $response = $this->request('GET', $this->endpoints['tours'], $params, 'fetch_tours');

            // Handle various response formats:
            // 1. Direct array: [{...}, {...}] (Zego format)
            // 2. Wrapped: { data: [...] } or { tours: [...] } or { items: [...] }
            if (is_array($response) && isset($response[0]) && is_array($response[0])) {
                // Direct array format (Zego)
                $tours = $response;
            } else {
                // Wrapped format
                $tours = $response['data'] ?? $response['tours'] ?? $response['items'] ?? [];
            }
            
            $nextCursor = $response['next_cursor'] ?? $response['cursor'] ?? $response['pagination']['next'] ?? null;
            $hasMore = $response['has_more'] ?? $response['hasMore'] ?? ($nextCursor !== null);

            return SyncResult::success($tours, $nextCursor, $hasMore);

        } catch (\Exception $e) {
            return SyncResult::failed($e->getMessage(), (string) $e->getCode());
        }
    }

    /**
     * Fetch single tour detail
     */
    public function fetchTourDetail(string $code): ?array
    {
        try {
            // Try tour_detail endpoint first, fallback to periods endpoint
            $endpoint = $this->endpoints['tour_detail'] ?? $this->endpoints['periods'] ?? '/{code}';
            
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
