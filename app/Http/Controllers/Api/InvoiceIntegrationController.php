<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\WebMember;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * InvoiceIntegrationController
 * -----------------------------------------------------------------------------
 * Read-only endpoints consumed by the nexttrip-invoice app (server-to-server,
 * authenticated with the invoice service Sanctum token). tour-api stays the
 * master for PERSON identity; the invoice keeps its own billing customers and
 * links back via (externalSource, externalId).
 */
class InvoiceIntegrationController extends Controller
{
    /**
     * Unified customer search for the invoice quotation flow.
     *
     * Merges web_members (durable person records) with GUEST bookings
     * (bookings without a web_member_id). Members win on de-duplication so a
     * member who also has guest-style bookings only appears once.
     *
     * Query: ?q=<name|email|phone>  (min 2 chars)
     * Returns: { success, data: [ { source, externalId, name, email, phone } ] }
     */
    public function searchCustomers(Request $request)
    {
        $q = trim((string) ($request->input('q') ?? $request->input('search') ?? ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $digits = preg_replace('/\D/', '', $q);
        $limit = 15;

        $applySearch = function ($query) use ($q, $digits) {
            $query->where(function ($w) use ($q, $digits) {
                $w->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhereRaw(
                        "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?",
                        ["%{$q}%"]
                    )
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");

                // Digits-only phone match: "0830868988", "66830868988" and
                // "830 868 988" all resolve to the same stored MSISDN.
                if ($digits !== '' && strlen($digits) >= 4) {
                    $w->orWhere('phone', 'like', "%{$digits}%");
                    if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
                        $w->orWhere('phone', 'like', '%66' . substr($digits, 1) . '%');
                    }
                }
            });
        };

        $results = [];
        $seen = [];

        // --- Web members (person master) ---
        $memberQuery = WebMember::query()
            ->where(function ($w) {
                $w->whereNull('status')->orWhere('status', '!=', 'deleted');
            });
        $applySearch($memberQuery);

        $members = $memberQuery
            ->orderBy('first_name')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        foreach ($members as $m) {
            $name = trim(($m->first_name ?? '') . ' ' . ($m->last_name ?? ''));
            $results[] = [
                'source' => 'member',
                'externalId' => (int) $m->id,
                'name' => $name !== '' ? $name : ($m->email ?: $m->phone ?: ('Member ' . $m->id)),
                'email' => $m->email,
                'phone' => $m->phone,
            ];
            if ($m->phone) {
                $seen['p:' . $this->phoneKey($m->phone)] = true;
            }
            if ($m->email) {
                $seen['e:' . mb_strtolower(trim($m->email))] = true;
            }
        }

        // --- Guest bookings (no linked member) ---
        $bookingQuery = Booking::query()->whereNull('web_member_id');
        $applySearch($bookingQuery);

        $bookings = $bookingQuery
            ->orderByDesc('id')
            ->limit($limit * 2)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        foreach ($bookings as $b) {
            $pk = $b->phone ? 'p:' . $this->phoneKey($b->phone) : null;
            $ek = $b->email ? 'e:' . mb_strtolower(trim($b->email)) : null;

            // Skip anyone already represented (by a member or an earlier booking).
            if (($pk && isset($seen[$pk])) || ($ek && isset($seen[$ek]))) {
                continue;
            }

            $name = trim(($b->first_name ?? '') . ' ' . ($b->last_name ?? ''));
            $results[] = [
                'source' => 'booking',
                'externalId' => (int) $b->id,
                'name' => $name !== '' ? $name : ($b->email ?: $b->phone ?: ('Booking ' . $b->id)),
                'email' => $b->email,
                'phone' => $b->phone,
            ];
            if ($pk) {
                $seen[$pk] = true;
            }
            if ($ek) {
                $seen[$ek] = true;
            }

            if (count($results) >= $limit * 2) {
                break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => array_slice($results, 0, 30),
        ]);
    }

    /**
     * Reduce a phone number to a comparable key: digits only, drop the Thai
     * "0" trunk prefix or the "66" country code, keep the last 9 digits.
     */
    private function phoneKey(?string $phone): string
    {
        $d = preg_replace('/\D/', '', (string) $phone);
        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '66')) {
            $d = substr($d, 2);
        } elseif (str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }
        return substr($d, -9);
    }

    /**
     * Callback FROM nexttrip-invoice reporting the invoice-side lifecycle of a
     * booking (its generated quotation number and current status). Called
     * server-to-server with the invoice's Sanctum service token.
     *
     * PATCH /api/integrations/bookings/{id}/invoice-status
     * Body: { quotationId, quotationNumber, status, invoiceNumber?, note? }
     * status one of: quotation_created | invoiced | paid | cancelled
     */
    public function updateInvoiceStatus(Request $request, int $id)
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return response()->json(['success' => false, 'error' => 'Booking not found'], 404);
        }

        $validated = $request->validate([
            'quotationId' => ['nullable', 'integer'],
            'quotationNumber' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['quotation_created', 'invoiced', 'paid', 'cancelled'])],
            'invoiceNumber' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking->invoice_quotation_id = $validated['quotationId'] ?? $booking->invoice_quotation_id;
        $booking->invoice_quotation_number = $validated['quotationNumber'] ?? $booking->invoice_quotation_number;
        $booking->invoice_status = $validated['status'];
        $booking->invoice_status_updated_at = now();

        // ใบเสนอราคาฝั่ง invoice ถูกลบ/ยกเลิกทิ้ง -> รีเซ็ต booking กลับเป็น
        // "ยังไม่ได้ส่ง" เพื่อให้ป้าย "Invoice ✓" หายไปและกดส่งใหม่ (resend) ได้อีกครั้ง
        // มิเช่นนั้น invoice_sent_at ที่เคยตั้งไว้ตอน sync ครั้งแรกจะค้างอยู่ตลอดไป
        // แม้ข้อมูลฝั่ง invoice จะไม่มีอยู่จริงแล้วก็ตาม
        if ($validated['status'] === 'cancelled') {
            $booking->invoice_sent_at = null;
            $booking->invoice_quotation_id = null;
            $booking->invoice_quotation_number = null;
        }

        $booking->save();

        BookingEvent::log(
            $booking->id,
            'invoice_status_update',
            'info',
            $validated['note'] ?? "Invoice status: {$validated['status']}",
            $validated,
            'nexttrip-invoice',
        );

        return response()->json(['success' => true]);
    }

    /**
     * Read-only lookup of a booking's promo/flash-sale discount detail, used
     * by nexttrip-invoice's one-off backfill script to fill in
     * tourDiscountLabel/tourDiscountPercent on quotations created before those
     * columns existed. Mirrors the exact same logic as
     * SendBookingToInvoice::buildPayload() so the backfilled values match what
     * a fresh webhook delivery would have sent.
     *
     * GET /api/integrations/bookings/{id}/tour-discount
     * Returns: { success, data: { tourType, tourDiscountLabel, tourDiscountPercent } }
     */
    public function getBookingTourDiscount(int $id)
    {
        $booking = Booking::with(['tour', 'flashSaleItem'])->find($id);
        if (! $booking) {
            return response()->json(['success' => false, 'error' => 'Booking not found'], 404);
        }

        $tour = $booking->tour;
        $tourType = 'NORMAL';
        $tourDiscountLabel = null;
        $tourDiscountPercent = null;

        if ($booking->source === 'flash_sale') {
            $tourType = 'FLASH_SALE';
            $tourDiscountPercent = $booking->flashSaleItem?->discount_percent !== null
                ? (float) $booking->flashSaleItem->discount_percent
                : null;
        } elseif ($tour?->has_promotion || $tour?->badge === 'PROMOTION') {
            $tourType = 'PROMOTION';
            $tourDiscountLabel = $tour?->discount_label;
            $tourDiscountPercent = $tour?->max_discount_percent > 0 ? (float) $tour->max_discount_percent : null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tourType' => $tourType,
                'tourDiscountLabel' => $tourDiscountLabel,
                'tourDiscountPercent' => $tourDiscountPercent,
            ],
        ]);
    }
}
