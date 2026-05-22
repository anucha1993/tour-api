<?php

namespace App\Services\WholesalerAdapters\Contracts;

use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\Contracts\DTOs\BookingResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\QuoteResult;

/**
 * Contract for outbound Booking adapters.
 *
 * Separate from AdapterInterface (which is for inbound sync) because:
 *  - Providers may use different credentials/endpoints for sync vs booking
 *    (e.g. Zego: sync uses auth-token, booking uses public_key).
 *  - Some sync providers don't expose booking APIs at all.
 *
 * Resolved via AdapterFactory::createBookingAdapter().
 */
interface BookingAdapterInterface
{
    /**
     * Provider short code used in WholesalerApiConfig.booking_provider.
     */
    public static function getProviderCode(): string;

    /**
     * Human-readable provider name shown in admin UI.
     */
    public static function getProviderName(): string;

    /**
     * Declarative schema of the provider config fields.
     * Used by the admin UI to render a dynamic form.
     *
     * Each entry: [
     *   'key'      => string,
     *   'type'     => 'text' | 'password' | 'url' | 'number' | 'boolean' | 'select' | 'textarea',
     *   'label'    => string,
     *   'required' => bool,
     *   'default'  => mixed (optional),
     *   'help'     => string (optional),
     *   'options'  => array (for select),
     *   'group'    => string (optional — to organize fields in UI sections),
     * ]
     */
    public static function getConfigSchema(): array;

    /**
     * Validate the booking_config currently saved on the config.
     * Returns array of human-readable errors (empty = valid).
     */
    public function validateConfig(): array;

    /**
     * Check if provider supports a capability.
     * Known features: 'real_hold', 'cancel', 'modify', 'partial_payment',
     *                  'multi_room', 'remark'.
     */
    public function supports(string $feature): bool;

    /**
     * Create a quote (pseudo-hold or real hold depending on provider).
     *
     * @param array $request {
     *   product_code: string,        // wholesaler product code
     *   travel_date:  string Y-m-d,
     *   pax_adult:    int,
     *   pax_child:    int,
     *   pax_child_nb: int,
     *   pax_infant:   int,
     *   rooms:        array<['code'=>string,'num'=>int]>,
     * }
     */
    public function createQuote(array $request): QuoteResult;

    /**
     * Submit the booking using a previously-issued quoteId.
     *
     * @param string $quoteId
     * @param array  $booking {
     *   customer_name:  string,
     *   customer_phone: string,
     *   remark:         string,
     *   passengers:     array<['code'=>string,'num'=>int]>,
     *   rooms:          array<['code'=>string,'num'=>int]>,
     * }
     */
    public function submitBooking(string $quoteId, array $booking): BookingResult;

    /**
     * Cancel a booking. Return BookingResult::failed('NOT_SUPPORTED')
     * if the provider does not expose a cancel endpoint.
     */
    public function cancelBooking(string $bookingRef, string $reason): BookingResult;

    /**
     * Health-check the booking endpoint.
     */
    public function healthCheck(): bool;
}
