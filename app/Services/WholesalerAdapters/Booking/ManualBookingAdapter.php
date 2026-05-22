<?php

namespace App\Services\WholesalerAdapters\Booking;

use App\Services\WholesalerAdapters\Contracts\DTOs\BookingResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\QuoteResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Manual Booking Adapter — for wholesalers without a booking API.
 *
 * Generates a local quote and sends an email notification to the wholesaler
 * contact email when a booking is submitted. Booking status remains 'pending'
 * until manually confirmed in the admin dashboard.
 */
class ManualBookingAdapter extends BaseBookingAdapter
{
    public static function getProviderCode(): string
    {
        return 'manual';
    }

    public static function getProviderName(): string
    {
        return 'Manual (Email Notification)';
    }

    public static function getConfigSchema(): array
    {
        return [
            [
                'key' => 'contact_email',
                'type' => 'text',
                'label' => 'Contact Email (booking notifications)',
                'required' => true,
                'help' => 'Email address that receives new booking notifications',
                'group' => 'Contact',
            ],
            [
                'key' => 'contact_phone',
                'type' => 'text',
                'label' => 'Contact Phone',
                'required' => false,
                'group' => 'Contact',
            ],
            [
                'key' => 'reply_to',
                'type' => 'text',
                'label' => 'Reply-To Email',
                'required' => false,
                'group' => 'Contact',
            ],
            [
                'key' => 'subject_prefix',
                'type' => 'text',
                'label' => 'Email Subject Prefix',
                'required' => false,
                'default' => '[Booking Request]',
                'group' => 'Email',
            ],
        ];
    }

    protected static function supportedFeatures(): array
    {
        return ['cancel', 'modify', 'remark', 'multi_room'];
    }

    public function createQuote(array $request): QuoteResult
    {
        $totalPax = (int) ($request['pax_adult'] ?? 0)
            + (int) ($request['pax_child'] ?? 0)
            + (int) ($request['pax_child_nb'] ?? 0)
            + (int) ($request['pax_infant'] ?? 0);

        if ($totalPax < 1) {
            return QuoteResult::failed('At least 1 passenger is required', 'INVALID_REQUEST');
        }

        $quoteId = 'manual_' . Str::random(28);
        Cache::put("booking_quote:{$quoteId}", [
            'provider' => 'manual',
            'wholesaler_id' => $this->config->wholesaler_id,
            'request' => $request,
        ], $this->holdTtl());

        return QuoteResult::success(
            quoteId: $quoteId,
            ttlSeconds: $this->holdTtl(),
            totalPrice: 0,
            breakdown: [],
            isRealHold: false,
        );
    }

    public function submitBooking(string $quoteId, array $booking): BookingResult
    {
        $session = Cache::get("booking_quote:{$quoteId}");
        if (!$session || ($session['provider'] ?? null) !== 'manual') {
            return BookingResult::failed('Quote session expired', 'INVALID_BOOKING_SESSION');
        }

        $bookingNo = 'MAN-' . strtoupper(Str::random(10));
        $contactEmail = $this->get('contact_email');

        // Best-effort email — failure does not block the booking creation
        if ($contactEmail) {
            try {
                $prefix = $this->get('subject_prefix', '[Booking Request]');
                $replyTo = $this->get('reply_to');
                Mail::raw(
                    $this->buildEmailBody($session, $booking, $bookingNo),
                    function ($m) use ($contactEmail, $prefix, $bookingNo, $replyTo) {
                        $m->to($contactEmail)
                            ->subject("{$prefix} {$bookingNo}");
                        if ($replyTo) {
                            $m->replyTo($replyTo);
                        }
                    }
                );
            } catch (\Throwable $e) {
                Log::error('Manual booking email failed', [
                    'booking_no' => $bookingNo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Cache::forget("booking_quote:{$quoteId}");

        return BookingResult::success(
            bookingRef: $bookingNo,
            confirmationNumber: $bookingNo,
            status: 'pending',
            metadata: ['session' => $session, 'booking' => $booking],
        );
    }

    public function cancelBooking(string $bookingRef, string $reason): BookingResult
    {
        return BookingResult::cancelled($bookingRef, ['reason' => $reason]);
    }

    private function buildEmailBody(array $session, array $booking, string $bookingNo): string
    {
        $req = $session['request'] ?? [];
        $lines = [
            "New Booking Request: {$bookingNo}",
            str_repeat('=', 50),
            '',
            "Customer: " . ($booking['customer_name'] ?? '-'),
            "Phone:    " . ($booking['customer_phone'] ?? '-'),
            "Remark:   " . ($booking['remark'] ?? '-'),
            '',
            "Product Code: " . ($req['product_code'] ?? '-'),
            "Travel Date:  " . ($req['travel_date'] ?? '-'),
            "Adults:       " . (int) ($req['pax_adult'] ?? 0),
            "Children:     " . (int) ($req['pax_child'] ?? 0),
            "Children NB:  " . (int) ($req['pax_child_nb'] ?? 0),
            "Infants:      " . (int) ($req['pax_infant'] ?? 0),
            '',
            'Please contact the customer to confirm.',
        ];
        return implode("\n", $lines);
    }
}
