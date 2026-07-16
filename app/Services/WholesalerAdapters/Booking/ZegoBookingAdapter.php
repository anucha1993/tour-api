<?php

namespace App\Services\WholesalerAdapters\Booking;

use App\Services\WholesalerAdapters\Contracts\DTOs\BookingResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\QuoteResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Zego Travel — Custom Booking API v1.5
 *
 * Flow: GET product → GET period → POST booking-submit
 *
 * Docs: tour-api/docs/Zego-Booking-API.md
 */
class ZegoBookingAdapter extends BaseBookingAdapter
{
    public static function getProviderCode(): string
    {
        return 'zego';
    }

    public static function getProviderName(): string
    {
        return 'Zego Travel — Custom Booking API';
    }

    public static function getConfigSchema(): array
    {
        return [
            [
                'key' => 'public_key',
                'type' => 'password',
                'label' => 'Public Key',
                'required' => true,
                'help' => 'ดูได้ที่ https://www.zegotravel.com/AgencyProfile',
                'group' => 'Credentials',
            ],
            [
                'key' => 'base_url',
                'type' => 'url',
                'label' => 'Base URL',
                'required' => true,
                'default' => 'https://www.zegoapi.com/v1.5/booking',
                'group' => 'Endpoints',
            ],
            [
                'key' => 'redirect_url',
                'type' => 'url',
                'label' => 'Redirect URL (after booking success)',
                'required' => false,
                'group' => 'Endpoints',
            ],
            [
                'key' => 'max_pax',
                'type' => 'number',
                'label' => 'Max passengers per booking',
                'required' => false,
                'default' => 10,
                'help' => 'Zego limit is 10',
                'group' => 'Limits',
            ],
        ];
    }

    protected static function supportedFeatures(): array
    {
        // No real hold, no cancel, no modify on Zego side.
        return ['multi_room', 'remark'];
    }

    private function baseUrl(): string
    {
        return rtrim($this->get('base_url', 'https://www.zegoapi.com/v1.5/booking'), '/');
    }

    private function publicKey(): string
    {
        return (string) $this->get('public_key', '');
    }

    public function createQuote(array $request): QuoteResult
    {
        $productCode = $request['product_code'] ?? null;
        $travelDate = $request['travel_date'] ?? null;
        $paxAdult = (int) ($request['pax_adult'] ?? 0);
        $paxChild = (int) ($request['pax_child'] ?? 0);
        $paxChildNB = (int) ($request['pax_child_nb'] ?? 0);
        $paxInfant = (int) ($request['pax_infant'] ?? 0);
        $totalPax = $paxAdult + $paxChild + $paxChildNB + $paxInfant;

        if (!$productCode || !$travelDate) {
            return QuoteResult::failed('product_code and travel_date are required', 'INVALID_REQUEST');
        }
        if ($paxAdult < 1) {
            return QuoteResult::failed('At least 1 adult is required', 'BOOKING_DETAIL_MA_REQUIRED');
        }
        $maxPax = (int) $this->get('max_pax', 10);
        if ($totalPax > $maxPax) {
            return QuoteResult::failed("Max {$maxPax} passengers per booking", 'PAX_LIMIT_EXCEEDED');
        }

        // Step 1: GET product
        $step1 = Http::timeout(15)->connectTimeout(10)
            ->get($this->baseUrl() . "/product/{$this->publicKey()}/{$productCode}");

        if (!$step1->successful()) {
            return QuoteResult::failed('Product not found', 'PRODUCT_NOT_FOUND');
        }
        $body1 = $step1->json();
        if (!($body1['status'] ?? false)) {
            return QuoteResult::failed($body1['code'] ?? 'PRODUCT_ERROR', $body1['code'] ?? null);
        }

        $periods = $body1['data']['datapackage']['periods'] ?? [];
        $period = collect($periods)->first(fn($p) => ($p['periodStartDate'] ?? null) === $travelDate);
        if (!$period) {
            return QuoteResult::failed('No period available for the selected date', 'NO_PERIOD');
        }
        if (in_array($period['periodStatus'] ?? '', ['Soldout', 'Close Group'], true)) {
            return QuoteResult::failed('Period is closed', 'PERIOD_NOT_AVAILABLE');
        }
        $seat = (int) ($period['seat'] ?? 0);
        if ($seat < $totalPax) {
            return QuoteResult::failed("Only {$seat} seats remaining", 'PERIOD_FULL');
        }

        $periodId = $period['periodId'];

        // Step 2: GET period (issues a fresh uuid)
        $step2 = Http::timeout(15)->connectTimeout(10)
            ->get($this->baseUrl() . "/period/{$this->publicKey()}/{$periodId}");

        if (!$step2->successful()) {
            return QuoteResult::failed('Failed to fetch period details', 'PERIOD_FETCH_FAILED');
        }
        $body2 = $step2->json();
        if (!($body2['status'] ?? false)) {
            return QuoteResult::failed($body2['code'] ?? 'PERIOD_ERROR', $body2['code'] ?? null);
        }

        $pkg = $body2['data']['datapackage'];
        $selected = $pkg['selectedPeriod'] ?? $period;
        $bookingDetailsTpl = $pkg['bookingDetails'] ?? [];
        $roomDetailsTpl = $pkg['roomDetails'] ?? [];

        // Compute total price using template prices (per pax type)
        $priceAdult = (float) ($selected['price'] ?? $period['price'] ?? 0);
        $priceChild = (float) ($selected['priceChild'] ?? $period['priceChild'] ?? 0);
        $priceChildNB = (float) ($selected['priceChildNB'] ?? $period['priceChildNB'] ?? 0);
        $priceInfant = (float) ($selected['priceInfant'] ?? $period['priceInfant'] ?? 0);

        $totalPrice =
            $paxAdult * $priceAdult +
            $paxChild * $priceChild +
            $paxChildNB * $priceChildNB +
            $paxInfant * $priceInfant;

        // Add room upgrade prices if requested rooms specify codes that exist in template
        $roomUpgrades = 0;
        foreach (($request['rooms'] ?? []) as $room) {
            $tpl = collect($roomDetailsTpl)->firstWhere('code', $room['code'] ?? null);
            if ($tpl) {
                $roomUpgrades += ((float) ($tpl['price'] ?? 0)) * (int) ($room['num'] ?? 0);
            }
        }
        $totalPrice += $roomUpgrades;

        // Cache the session (uuid is one-shot)
        $quoteId = 'zego_' . Str::random(28);
        Cache::put("booking_quote:{$quoteId}", [
            'provider' => 'zego',
            'wholesaler_id' => $this->config->wholesaler_id,
            'uuid' => $body2['data']['uuid'],
            'period_id' => $periodId,
            'product_code' => $productCode,
            'travel_date' => $travelDate,
            'pax' => compact('paxAdult', 'paxChild', 'paxChildNB', 'paxInfant'),
            'booking_details_template' => $bookingDetailsTpl,
            'room_details_template' => $roomDetailsTpl,
            'total_price' => $totalPrice,
        ], $this->holdTtl());

        return QuoteResult::success(
            quoteId: $quoteId,
            ttlSeconds: $this->holdTtl(),
            totalPrice: $totalPrice,
            breakdown: [
                ['label' => 'Adult', 'qty' => $paxAdult, 'unit_price' => $priceAdult, 'subtotal' => $paxAdult * $priceAdult],
                ['label' => 'Child (with bed)', 'qty' => $paxChild, 'unit_price' => $priceChild, 'subtotal' => $paxChild * $priceChild],
                ['label' => 'Child (no bed)', 'qty' => $paxChildNB, 'unit_price' => $priceChildNB, 'subtotal' => $paxChildNB * $priceChildNB],
                ['label' => 'Infant', 'qty' => $paxInfant, 'unit_price' => $priceInfant, 'subtotal' => $paxInfant * $priceInfant],
                ['label' => 'Room upgrades', 'qty' => 1, 'unit_price' => $roomUpgrades, 'subtotal' => $roomUpgrades],
            ],
            passengerTypes: $bookingDetailsTpl,
            roomTypes: $roomDetailsTpl,
            isRealHold: false,
            metadata: [
                'period_id' => $periodId,
                'period_code' => $selected['periodCode'] ?? null,
                'deposit' => $selected['deposit'] ?? null,
            ],
        );
    }

    public function submitBooking(string $quoteId, array $booking): BookingResult
    {
        $session = Cache::get("booking_quote:{$quoteId}");
        if (!$session || ($session['provider'] ?? null) !== 'zego') {
            return BookingResult::failed('Quote session expired or invalid', 'INVALID_BOOKING_SESSION');
        }

        $name = $this->sanitizeName((string) ($booking['customer_name'] ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($booking['customer_phone'] ?? ''));
        if (strlen($phone) !== 10) {
            return BookingResult::failed('Phone number must be exactly 10 digits', 'INVALID_CUSTOMER_PHONE');
        }
        if ($name === '') {
            return BookingResult::failed('Customer name is required', 'CUSTOMER_NAME_REQUIRED');
        }

        // Default bookingDetails from pax counts if not explicitly provided
        $bookingDetails = $booking['passengers'] ?? $this->paxToBookingDetails($session['pax']);
        $roomDetails = $booking['rooms'] ?? [];

        $payload = [
            'booking' => [
                'customerName' => $name,
                'customerPhone' => $phone,
                'remark' => (string) ($booking['remark'] ?? ''),
            ],
            'bookingDetails' => array_values(array_filter(
                array_map(fn($d) => ['code' => $d['code'], 'num' => (int) $d['num']], $bookingDetails),
                fn($d) => ($d['num'] ?? 0) > 0
            )),
            'roomDetails' => array_values(array_filter(
                array_map(fn($r) => ['code' => $r['code'], 'num' => (int) $r['num']], $roomDetails),
                fn($r) => ($r['num'] ?? 0) > 0
            )),
        ];

        $res = Http::timeout(30)->connectTimeout(10)
            ->withHeaders([
                'x-public-key' => $this->publicKey(),
                'x-uuid' => $session['uuid'],
            ])
            ->post($this->baseUrl() . '/booking-submit', $payload);

        $body = $res->json() ?? [];

        if (!$res->successful() || !($body['status'] ?? false)) {
            Log::error('Zego booking-submit failed', [
                'http_status' => $res->status(),
                'code' => $body['code'] ?? null,
                'body' => $body,
            ]);
            return BookingResult::failed(
                $body['code'] ?? 'BOOKING_SUBMIT_FAILED',
                $body['code'] ?? null,
            );
        }

        // uuid is consumed — invalidate the quote
        Cache::forget("booking_quote:{$quoteId}");

        $data = $body['data'] ?? [];
        // As of Zego API v1.5 (2026-07 update) the successful booking-submit
        // response nests booking metadata under `data.booking`. Older/other
        // shapes flatten the fields onto `data` directly. Support both.
        $bookingBlock = is_array($data['booking'] ?? null) ? $data['booking'] : [];

        $bookingRef = (string) (
            $bookingBlock['id']
            ?? $bookingBlock['bookingId']
            ?? $bookingBlock['number']
            ?? $bookingBlock['bookingNo']
            ?? $data['bookingId']
            ?? $data['bookingNo']
            ?? $data['booking_id']
            ?? $data['booking_no']
            ?? $data['id']
            ?? ''
        );

        $confirmationNumber = $bookingBlock['number']
            ?? $bookingBlock['bookingNo']
            ?? $data['bookingNo']
            ?? $data['booking_no']
            ?? null;

        // ISO-8601 timestamps returned by Zego (UTC, e.g. "2026-07-09T10:05:53.000Z")
        $bookingDate = $bookingBlock['bookingDate']
            ?? $data['bookingDate']
            ?? null;
        $expiresAt = $bookingBlock['bookingExpireDate']
            ?? $data['bookingExpireDate']
            ?? null;

        // Zego sometimes does NOT return bookingId/bookingNo in the submit
        // response — look it up via the audit API by customer phone.
        $auditLookup = null;
        if ($bookingRef === '') {
            $auditLookup = $this->findRecentAuditBookingByPhone(
                preg_replace('/\D/', '', (string) ($booking['customer_phone'] ?? ''))
            );
            if ($auditLookup) {
                $bookingRef = (string) ($auditLookup['bookingNo'] ?? $auditLookup['bookingId'] ?? '');
                $confirmationNumber = $confirmationNumber ?? ($auditLookup['bookingNo'] ?? null);
            }
        }

        Log::info('Zego booking-submit success', [
            'http_status' => $res->status(),
            'booking_ref' => $bookingRef,
            'confirmation_number' => $confirmationNumber,
            'expires_at' => $expiresAt,
            'data' => $data,
            'raw_body' => $body,
            'audit_lookup' => $auditLookup,
        ]);

        return BookingResult::success(
            bookingRef: $bookingRef,
            confirmationNumber: $confirmationNumber,
            status: 'confirmed',
            metadata: array_merge($data, [
                'booking_date' => $bookingDate,
                'expires_at' => $expiresAt,
                'raw_response' => $body,
                'audit_lookup' => $auditLookup,
            ]),
        );
    }

    public function cancelBooking(string $bookingRef, string $reason): BookingResult
    {
        return BookingResult::failed(
            'Zego Booking API does not support cancellation — please contact the wholesaler directly',
            'NOT_SUPPORTED',
        );
    }

    private function sanitizeName(string $name): string
    {
        // Zego rejects: @ $ - _ and emoji
        $clean = preg_replace('/[@$\-_]/u', ' ', $name);
        $clean = preg_replace(
            '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{27BF}\x{1F900}-\x{1F9FF}]/u',
            '',
            $clean
        );
        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    /**
     * Default mapping from pax counts → Zego booking detail codes.
     * Adjust if the wholesaler uses non-standard codes.
     */
    private function paxToBookingDetails(array $pax): array
    {
        $details = [];
        if (($pax['paxAdult'] ?? 0) > 0)   $details[] = ['code' => 'MA',     'num' => $pax['paxAdult']];
        if (($pax['paxChild'] ?? 0) > 0)   $details[] = ['code' => 'CH',     'num' => $pax['paxChild']];
        if (($pax['paxChildNB'] ?? 0) > 0) $details[] = ['code' => 'CHNB',   'num' => $pax['paxChildNB']];
        if (($pax['paxInfant'] ?? 0) > 0)  $details[] = ['code' => 'IF',     'num' => $pax['paxInfant']];
        return $details;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Zego Audit API (v1.5) — list/inspect bookings already submitted
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /agency/audit/bookings — list past bookings (default 7 days).
     *
     * Params: page, limit, dateFrom (Y-m-d), dateTo (Y-m-d),
     *         status, bookingNo, customerPhone
     */
    public function listAuditBookings(array $params = []): array
    {
        return $this->auditGet('/agency/audit/bookings', $params);
    }

    /**
     * GET /agency/audit/bookings/{bookingNo} — booking detail log.
     */
    public function getAuditBooking(string $bookingNo): array
    {
        return $this->auditGet("/agency/audit/bookings/{$bookingNo}");
    }

    /**
     * GET /agency/audit/bookings/{bookingNo}/assignment — who handles it.
     */
    public function getBookingAssignment(string $bookingNo): array
    {
        return $this->auditGet("/agency/audit/bookings/{$bookingNo}/assignment");
    }

    /**
     * GET /agency/audit/failures — failed booking attempts.
     */
    public function listAuditFailures(array $params = []): array
    {
        return $this->auditGet('/agency/audit/failures', $params);
    }

    private function auditGet(string $path, array $params = []): array
    {
        $res = Http::timeout(15)->connectTimeout(10)
            ->withHeaders(['x-public-key' => $this->publicKey()])
            ->get($this->baseUrl() . $path, $params);

        $body = $res->json() ?? [];
        if (!$res->successful() || !($body['status'] ?? false)) {
            Log::warning('Zego audit GET failed', [
                'path' => $path,
                'http_status' => $res->status(),
                'code' => $body['code'] ?? null,
                'body' => $body,
            ]);
        }
        return $body;
    }

    /**
     * Look up the most recent successful booking for a given customer phone
     * within the last 24 hours. Returns the matched booking row or null.
     */
    private function findRecentAuditBookingByPhone(string $phone): ?array
    {
        if ($phone === '') return null;

        $body = $this->listAuditBookings([
            'customerPhone' => $phone,
            'limit' => 10,
            'page' => 1,
            'dateFrom' => now()->subDay()->format('Y-m-d'),
            'dateTo' => now()->format('Y-m-d'),
        ]);

        $rows = $body['data']['list'] ?? $body['data']['items'] ?? $body['data'] ?? [];
        if (!is_array($rows) || empty($rows)) return null;

        // Return the first (most recent) row that has a bookingNo
        foreach ($rows as $row) {
            if (!empty($row['bookingNo']) || !empty($row['bookingId'])) {
                return $row;
            }
        }
        return null;
    }
}
