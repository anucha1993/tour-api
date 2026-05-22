<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\Period;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;
use App\Services\WholesalerAdapters\Contracts\BookingAdapterInterface;
use App\Services\WholesalerAdapters\Contracts\DTOs\BookingResult;
use App\Services\WholesalerAdapters\Contracts\DTOs\QuoteResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Orchestrates the outbound booking lifecycle:
 *   quote → hold (passengers entered) → confirm → cancel
 *
 * Each step persists the Booking model and delegates the network call to
 * the appropriate BookingAdapter (resolved from the Period's wholesaler).
 */
class BookingService
{
    /**
     * Step 1: Create a quote for a period + pax mix.
     *
     * Creates a draft Booking row and asks the provider for a quote.
     * On success: provider_status = 'quoted', hold_expires_at set.
     * On failure: provider_status = 'failed'.
     *
     * @param array $pax {
     *   adult: int, adult_single: int, child_bed: int, child_nobed: int, infant: int,
     *   rooms?: array<['code'=>string,'num'=>int]>,
     * }
     */
    public function quote(Period $period, array $pax, array $customer = []): Booking
    {
        $tour = $period->tour()->firstOrFail();
        if (!$tour->wholesaler_id) {
            throw new RuntimeException('Tour has no wholesaler assigned');
        }

        $config = WholesalerApiConfig::where('wholesaler_id', $tour->wholesaler_id)->firstOrFail();
        if (!$config->booking_enabled) {
            throw new RuntimeException('Booking is not enabled for this wholesaler');
        }

        $booking = DB::transaction(function () use ($period, $tour, $config, $pax, $customer) {
            return Booking::create([
                'booking_code' => Booking::generateBookingCode(),
                'web_member_id' => $customer['web_member_id'] ?? null,
                'tour_id' => $tour->id,
                'period_id' => $period->id,
                'integration_id' => $config->id,
                'provider' => $config->booking_provider,
                'qty_adult' => (int) ($pax['adult'] ?? 0),
                'qty_adult_single' => (int) ($pax['adult_single'] ?? 0),
                'qty_child_bed' => (int) ($pax['child_bed'] ?? 0),
                'qty_child_nobed' => (int) ($pax['child_nobed'] ?? 0),
                'qty_infant' => (int) ($pax['infant'] ?? 0),
                'first_name' => $customer['first_name'] ?? '',
                'last_name' => $customer['last_name'] ?? '',
                'email' => $customer['email'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'status' => 'pending',
                'provider_status' => 'pending',
                'source' => 'website',
                'currency' => 'THB',
            ]);
        });

        $adapter = AdapterFactory::createBookingAdapter($tour->wholesaler_id);

        $quoteRequest = [
            'product_code' => $tour->wholesaler_tour_code ?? $tour->code,
            'travel_date' => $period->start_date?->format('Y-m-d'),
            'pax_adult' => $booking->qty_adult + $booking->qty_adult_single,
            'pax_child' => $booking->qty_child_bed,
            'pax_child_nb' => $booking->qty_child_nobed,
            'pax_infant' => $booking->qty_infant,
            'rooms' => $pax['rooms'] ?? [],
        ];

        $result = $adapter->createQuote($quoteRequest);

        if (!$result->success) {
            $booking->update([
                'provider_status' => 'failed',
                'admin_note' => 'Quote failed: ' . ($result->errorMessage ?? 'unknown'),
                'provider_payload' => ['quote_error' => $result->toArray()],
            ]);

            throw new RuntimeException($result->errorMessage ?? 'Quote failed', 0);
        }

        $booking->update([
            'provider_quote_ref' => $result->quoteId,
            'provider_status' => 'quoted',
            'hold_expires_at' => $result->expiresAt,
            'total_amount' => $result->totalPrice,
            'currency' => $result->currency,
            'provider_payload' => ['quote' => $result->toArray()],
        ]);

        return $booking->fresh();
    }

    /**
     * Step 2: Attach passenger details and mark as held (UI countdown).
     *
     * For providers without server-side hold (Zego), this is a soft state.
     * For real-hold providers, the hold was already created at quote time.
     *
     * @param array $passengers each: {
     *   type, title, first_name, last_name, dob?, gender?,
     *   passport_no?, nationality?, passport_expiry?, email?, phone?,
     *   is_lead?, room_type?, room_index?
     * }
     */
    public function hold(Booking $booking, array $passengers): Booking
    {
        if (!in_array($booking->provider_status, ['quoted', 'held'], true)) {
            throw new RuntimeException("Cannot hold booking in state '{$booking->provider_status}'");
        }

        DB::transaction(function () use ($booking, $passengers) {
            // Replace existing passengers
            $booking->passengers()->delete();

            foreach ($passengers as $idx => $p) {
                BookingPassenger::create([
                    'booking_id' => $booking->id,
                    'type' => $p['type'] ?? 'adult',
                    'title' => $p['title'] ?? null,
                    'first_name' => $p['first_name'] ?? '',
                    'last_name' => $p['last_name'] ?? '',
                    'first_name_th' => $p['first_name_th'] ?? null,
                    'last_name_th' => $p['last_name_th'] ?? null,
                    'dob' => $p['dob'] ?? null,
                    'gender' => $p['gender'] ?? null,
                    'passport_no' => $p['passport_no'] ?? null,
                    'nationality' => $p['nationality'] ?? null,
                    'passport_expiry' => $p['passport_expiry'] ?? null,
                    'passport_issue_date' => $p['passport_issue_date'] ?? null,
                    'passport_issue_country' => $p['passport_issue_country'] ?? null,
                    'email' => $p['email'] ?? null,
                    'phone' => $p['phone'] ?? null,
                    'special_request' => $p['special_request'] ?? null,
                    'is_lead' => (bool) ($p['is_lead'] ?? ($idx === 0)),
                    'room_type' => $p['room_type'] ?? null,
                    'room_index' => $p['room_index'] ?? null,
                ]);
            }

            // Mirror lead passenger into bookings.first_name etc. for legacy compatibility
            $lead = $booking->passengers()->where('is_lead', true)->first()
                ?? $booking->passengers()->first();
            if ($lead) {
                $booking->update([
                    'first_name' => $lead->first_name ?: $booking->first_name,
                    'last_name' => $lead->last_name ?: $booking->last_name,
                    'email' => $lead->email ?: $booking->email,
                    'phone' => $lead->phone ?: $booking->phone,
                    'provider_status' => 'held',
                ]);
            } else {
                $booking->update(['provider_status' => 'held']);
            }
        });

        return $booking->fresh('passengers');
    }

    /**
     * Step 3: Submit the booking to the provider for confirmation.
     */
    public function confirm(Booking $booking): Booking
    {
        if (!in_array($booking->provider_status, ['quoted', 'held'], true)) {
            throw new RuntimeException("Cannot confirm booking in state '{$booking->provider_status}'");
        }

        if (!$booking->provider_quote_ref) {
            throw new RuntimeException('Booking has no quote reference');
        }

        if ($booking->hold_expires_at && $booking->hold_expires_at->isPast()) {
            $booking->update(['provider_status' => 'failed']);
            throw new RuntimeException('Hold has expired — please request a new quote');
        }

        $adapter = $this->getAdapterFor($booking);

        $passengers = $booking->passengers()->get();
        $leadName = trim(($passengers->firstWhere('is_lead', true)?->full_name) ?? $booking->full_name);

        $payload = [
            'customer_name' => $leadName ?: ($booking->first_name . ' ' . $booking->last_name),
            'customer_phone' => $booking->phone,
            'remark' => $booking->special_request ?? '',
            'passengers' => $this->buildPassengerCodes($booking),
            'rooms' => $this->buildRoomCodes($booking),
        ];

        $result = $adapter->submitBooking($booking->provider_quote_ref, $payload);

        $this->applyBookingResult($booking, $result, 'submit');

        if (!$result->success) {
            throw new RuntimeException($result->errorMessage ?? 'Booking submission failed');
        }

        $booking->update([
            'status' => 'confirmed',
            'provider_status' => 'confirmed',
            'provider_booking_ref' => $result->bookingRef,
        ]);

        return $booking->fresh('passengers');
    }

    /**
     * Cancel a booking on the provider side (if supported).
     */
    public function cancel(Booking $booking, string $reason = ''): Booking
    {
        if (in_array($booking->provider_status, ['cancelled', 'failed'], true)) {
            return $booking;
        }

        // If never sent to provider, just mark cancelled locally
        if (!$booking->provider_booking_ref) {
            $booking->update([
                'status' => 'cancelled',
                'provider_status' => 'cancelled',
                'cancelled_by' => 'admin',
                'admin_note' => trim(($booking->admin_note ?? '') . "\n[cancel] " . $reason),
            ]);
            return $booking->fresh();
        }

        $adapter = $this->getAdapterFor($booking);
        if (!$adapter->supports('cancel')) {
            throw new RuntimeException('Provider does not support cancel');
        }

        $result = $adapter->cancelBooking($booking->provider_booking_ref, $reason);
        $this->applyBookingResult($booking, $result, 'cancel');

        if (!$result->success) {
            throw new RuntimeException($result->errorMessage ?? 'Cancel failed');
        }

        $booking->update([
            'status' => 'cancelled',
            'provider_status' => 'cancelled',
            'cancelled_by' => 'admin',
            'admin_note' => trim(($booking->admin_note ?? '') . "\n[cancel] " . $reason),
        ]);

        return $booking->fresh();
    }

    protected function getAdapterFor(Booking $booking): BookingAdapterInterface
    {
        $config = $booking->integration ?: WholesalerApiConfig::findOrFail($booking->integration_id);
        return AdapterFactory::createBookingAdapter($config->wholesaler_id);
    }

    /**
     * Build provider-compatible passenger codes from the rows in booking_passengers.
     * Falls back to qty_* if no rows exist.
     *
     * Codes are resolved against the quote template
     * (provider_payload.quote.passenger_types) when available, so we always
     * send codes the provider has whitelisted for the period.
     */
    protected function buildPassengerCodes(Booking $booking): array
    {
        $template = $booking->provider_payload['quote']['passenger_types'] ?? [];
        $resolveCode = function (string $semantic) use ($template): ?string {
            // semantic: 'adult' | 'child_bed' | 'child_nobed' | 'infant'
            $defaults = [
                'adult'       => ['MA', 'AD', 'ADT', 'ADULT'],
                'child_bed'   => ['CH', 'CHD', 'CWB', 'CHILD'],
                'child_nobed' => ['CHNB', 'CNB', 'NOBED'],
                'infant'      => ['IF', 'IN', 'INF', 'INFANT'],
            ];
            $contentHints = [
                'adult'       => ['ผู้ใหญ่', 'adult'],
                'child_bed'   => ['เด็ก', 'เสริมเตียง', 'with bed', 'child'],
                'child_nobed' => ['ไม่เสริมเตียง', 'no bed', 'nobed'],
                'infant'      => ['ทารก', 'infant'],
            ];

            // 1) exact code match against template
            foreach ($template as $row) {
                $code = strtoupper((string) ($row['code'] ?? ''));
                if ($code !== '' && in_array($code, $defaults[$semantic] ?? [], true)) {
                    return $row['code'];
                }
            }
            // 2) content keyword match
            foreach ($template as $row) {
                $content = mb_strtolower((string) ($row['content'] ?? ''));
                foreach ($contentHints[$semantic] ?? [] as $hint) {
                    if ($content !== '' && str_contains($content, mb_strtolower($hint))) {
                        // Skip child_nobed rows when looking for child_bed (and vice-versa)
                        if ($semantic === 'child_bed' && str_contains($content, 'ไม่เสริม')) continue;
                        return $row['code'];
                    }
                }
            }
            // 3) fallback to convention
            return $defaults[$semantic][0] ?? null;
        };

        $tally = [
            'adult'       => (int) $booking->qty_adult + (int) $booking->qty_adult_single,
            'child_bed'   => (int) $booking->qty_child_bed,
            'child_nobed' => (int) $booking->qty_child_nobed,
            'infant'      => (int) $booking->qty_infant,
        ];

        // Prefer counts from booking_passengers when rows exist
        $rows = $booking->passengers()->get();
        if ($rows->isNotEmpty()) {
            $tally = ['adult' => 0, 'child_bed' => 0, 'child_nobed' => 0, 'infant' => 0];
            foreach ($rows as $r) {
                if ($r->type === 'infant') $tally['infant']++;
                elseif ($r->type === 'child') $tally['child_bed']++; // assume bed by default
                else $tally['adult']++;
            }
            // Preserve any nobed pax from the booking quantity, since
            // passenger rows don't distinguish bed vs no-bed children.
            if ($booking->qty_child_nobed > 0 && $tally['child_bed'] >= $booking->qty_child_nobed) {
                $tally['child_bed'] -= $booking->qty_child_nobed;
                $tally['child_nobed'] = $booking->qty_child_nobed;
            }
        }

        $out = [];
        foreach ($tally as $semantic => $num) {
            if ($num <= 0) continue;
            $code = $resolveCode($semantic);
            if ($code) $out[] = ['code' => $code, 'num' => $num];
        }
        return $out;
    }

    /**
     * Compute provider room codes from booking qty fields, balancing
     * bed-using pax (adult + child-with-bed) against room capacity.
     *
     * Rules (Zego-compatible):
     *   adult_single → SGL (1 bed)
     *   adult (paired) → TWN (2 beds)  ← must be even
     *   leftover adult or +child_bed → TPL (3 beds) when possible
     *   child_nobed / infant → no bed (not counted)
     *
     * Available room codes are discovered from the cached quote template
     * (room_details_template) when present; otherwise we fall back to the
     * conventional SGL/TWN/TPL codes.
     */
    protected function buildRoomCodes(Booking $booking): array
    {
        $template = $booking->provider_payload['quote']['room_types'] ?? [];

        // Resolve a room code by trying:
        //   1) exact code match (e.g. 'SGL')
        //   2) content keyword match against template (e.g. 'พักเดี่ยว' → 'RA')
        //   3) fallback to the first candidate (when template is empty)
        $resolve = function (array $exactCandidates, array $contentHints) use ($template): ?string {
            foreach ($template as $row) {
                $code = strtoupper((string) ($row['code'] ?? ''));
                if ($code !== '' && in_array($code, $exactCandidates, true)) return $row['code'];
            }
            foreach ($template as $row) {
                $content = mb_strtolower((string) ($row['content'] ?? ''));
                foreach ($contentHints as $hint) {
                    if ($content !== '' && str_contains($content, mb_strtolower($hint))) {
                        return $row['code'];
                    }
                }
            }
            return empty($template) ? ($exactCandidates[0] ?? null) : null;
        };

        $sglCode = $resolve(['SGL', 'SIN', 'SINGLE'], ['พักเดี่ยว', 'single']);
        // Prefer Twin over Double when both exist
        $twnCode = $resolve(['TWN', 'TWIN'], ['twin', 'พักคู่ (twin']);
        if (!$twnCode) {
            $twnCode = $resolve(['DBL', 'DOUBLE'], ['double', 'พักคู่']);
        }
        $tplCode = $resolve(['TPL', 'TRP', 'TRIPLE'], ['triple', 'พัก 3', 'พัก3']);

        $sgl = (int) $booking->qty_adult_single;
        $beds = (int) $booking->qty_adult + (int) $booking->qty_child_bed;

        $twn = 0;
        $tpl = 0;

        if ($tplCode && $booking->qty_child_bed > 0 && $booking->qty_adult >= 2) {
            $triples = min($booking->qty_child_bed, intdiv($booking->qty_adult, 2));
            $tpl = $triples;
            $beds -= $triples * 3;
        }

        if ($twnCode && $beds >= 2) {
            $twn = intdiv($beds, 2);
            $beds -= $twn * 2;
        }

        if ($beds > 0 && $sglCode) {
            $sgl += $beds;
            $beds = 0;
        }

        $rooms = [];
        if ($sgl > 0 && $sglCode) $rooms[] = ['code' => $sglCode, 'num' => $sgl];
        if ($twn > 0 && $twnCode) $rooms[] = ['code' => $twnCode, 'num' => $twn];
        if ($tpl > 0 && $tplCode) $rooms[] = ['code' => $tplCode, 'num' => $tpl];

        return $rooms;
    }

    protected function applyBookingResult(Booking $booking, BookingResult $result, string $stage): void
    {
        $payload = $booking->provider_payload ?? [];
        $payload[$stage] = $result->toArray();
        $booking->update(['provider_payload' => $payload]);

        if (!$result->success) {
            Log::warning("BookingService::{$stage} failed", [
                'booking_id' => $booking->id,
                'error' => $result->errorMessage,
                'code' => $result->errorCode,
            ]);
        }
    }
}
