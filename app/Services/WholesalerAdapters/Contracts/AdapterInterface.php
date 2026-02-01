<?php

namespace App\Services\WholesalerAdapters\Contracts;

use App\Services\WholesalerAdapters\Contracts\DTOs\AvailabilityResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\BookingResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\HoldResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\SyncResult;

/**
 * Contract for Wholesaler API Adapters
 * 
 * All wholesaler adapters must implement this interface.
 * It defines both INBOUND (receiving data) and OUTBOUND (sending data) operations.
 */
interface AdapterInterface
{
    // ═══════════════════════════════════════════════════════════
    // INBOUND OPERATIONS - Receiving data from Wholesaler
    // ═══════════════════════════════════════════════════════════

    /**
     * Fetch tours from wholesaler API
     * 
     * @param string|null $cursor Cursor for pagination (optional)
     * @return SyncResult Contains tours data and next cursor
     */
    public function fetchTours(?string $cursor = null): SyncResult;

    /**
     * Fetch single tour detail
     * 
     * @param string $code Tour code from wholesaler
     * @return array|null Tour details or null if not found
     */
    public function fetchTourDetail(string $code): ?array;

    // ═══════════════════════════════════════════════════════════
    // OUTBOUND OPERATIONS - Sending data to Wholesaler
    // ═══════════════════════════════════════════════════════════

    /**
     * Acknowledge that tours have been synced successfully
     * Used to prevent wholesaler from re-sending same tours
     * 
     * @param array $tourCodes List of synced tour codes
     * @param string $syncId Unique sync batch ID
     * @return bool Success status
     */
    public function acknowledgeSynced(array $tourCodes, string $syncId): bool;

    /**
     * Check real-time availability for a tour
     * 
     * @param string $code Tour code
     * @param string $date Travel date (Y-m-d)
     * @param int $paxAdult Number of adults
     * @param int $paxChild Number of children
     * @return AvailabilityResult Contains seats and prices
     */
    public function checkAvailability(
        string $code,
        string $date,
        int $paxAdult,
        int $paxChild = 0
    ): AvailabilityResult;

    /**
     * Hold booking temporarily (with TTL)
     * Customer has limited time to complete booking
     * 
     * @param string $code Tour code
     * @param string $date Travel date (Y-m-d)
     * @param int $paxAdult Number of adults
     * @param int $paxChild Number of children
     * @return HoldResult Contains hold_id and expiry time
     */
    public function holdBooking(
        string $code,
        string $date,
        int $paxAdult,
        int $paxChild = 0
    ): HoldResult;

    /**
     * Confirm a booking (after payment)
     * 
     * @param string $holdId Hold ID from holdBooking
     * @param array $passengers Passenger information
     * @param array $paymentInfo Payment details
     * @return BookingResult Contains booking reference
     */
    public function confirmBooking(
        string $holdId,
        array $passengers,
        array $paymentInfo
    ): BookingResult;

    /**
     * Cancel a booking
     * 
     * @param string $bookingRef Booking reference
     * @param string $reason Cancellation reason
     * @return BookingResult Contains cancellation status
     */
    public function cancelBooking(string $bookingRef, string $reason): BookingResult;

    /**
     * Modify an existing booking
     * 
     * @param string $bookingRef Booking reference
     * @param array $changes Changes to apply
     * @return BookingResult Contains modification status
     */
    public function modifyBooking(string $bookingRef, array $changes): BookingResult;

    // ═══════════════════════════════════════════════════════════
    // UTILITY OPERATIONS
    // ═══════════════════════════════════════════════════════════

    /**
     * Check if API is healthy/accessible
     * 
     * @return bool True if API is responding correctly
     */
    public function healthCheck(): bool;

    /**
     * Get adapter configuration
     * 
     * @return array Configuration data
     */
    public function getConfig(): array;

    /**
     * Get wholesaler ID
     * 
     * @return int Wholesaler ID
     */
    public function getWholesalerId(): int;
}
