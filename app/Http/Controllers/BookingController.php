<?php

namespace App\Http\Controllers;

use App\Jobs\SendBookingToInvoice;
use App\Models\Booking;
use App\Models\Period;
use App\Models\PointTransaction;
use App\Services\BookingEmailService;
use App\Services\Booking\BookingService;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    /**
     * Apply visibility filter based on the current user's role.
     *
     * Business rule (2026-07-17):
     *   role = 'sale' → see ONLY bookings assigned to them (bookings.sale_code
     *                   equals their name) OR bookings with no sale assigned
     *                   yet (sale_code IS NULL or empty).
     *   role = 'admin' / 'it' / anything else → no filter (see everything).
     *
     * NOTE: `sale_code` currently stores the sale user's NAME (string), not an
     * FK to users.id. See docs/proposals/2026-07-13-member-promotion-addon-sales.md
     * for the future migration plan to a proper foreign key.
     */
    protected function applySaleVisibilityFilter($query): void
    {
        $user = Auth::user();
        if (!$user) return; // Unauth: rely on route middleware
        if (($user->role ?? null) !== 'sale') return; // admin/it: no restriction

        $saleName = trim((string) ($user->name ?? ''));

        $query->where(function ($q) use ($saleName) {
            // Bookings not yet assigned to any sale
            $q->whereNull('sale_code')
              ->orWhere('sale_code', '');
            if ($saleName !== '') {
                // Bookings assigned to this sale (exact-match on name)
                $q->orWhere('sale_code', $saleName);
            }
        });
    }

    /**
     * Whether the current user is allowed to access the given booking. Same
     * rule as applySaleVisibilityFilter() but for a single-row check.
     */
    protected function currentUserCanAccessBooking(Booking $booking): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if (($user->role ?? null) !== 'sale') return true; // admin/it

        $saleName = trim((string) ($user->name ?? ''));
        $bookingSale = trim((string) ($booking->sale_code ?? ''));

        // Unassigned or matches the sale's own name
        return $bookingSale === '' || ($saleName !== '' && $bookingSale === $saleName);
    }

    /**
     * List all bookings (admin)
     */
    public function index(Request $request)
    {
        $query = Booking::with([
            'member:id,first_name,last_name,email,phone',
            'tour:id,title,slug,tour_code,wholesaler_id,badge,has_promotion,discount_label,max_discount_percent',
            'tour.wholesaler:id,name,code',
            'period:id,start_date,end_date',
            'flashSaleItem:id,flash_price,discount_percent,flash_sale_id',
        ]);

        // Role-based visibility (sales only see own + unassigned)
        $this->applySaleVisibilityFilter($query);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by source
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Search by booking_code, name, phone, email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $bookings = $query->paginate($request->get('per_page', 20));

        return response()->json($bookings);
    }

    /**
     * Get single booking detail
     */
    public function show(int $id)
    {
        $booking = Booking::with([
            'member:id,first_name,last_name,email,phone,avatar',
            'tour:id,title,slug,tour_code,duration_days,duration_nights,wholesaler_id,badge,has_promotion,discount_label,max_discount_percent',
            'tour.wholesaler:id,name,code',
            'tour.transports:id,tour_id,transport_name,sort_order',
            'period:id,start_date,end_date,capacity,booked',
            'flashSaleItem:id,flash_price,original_price,discount_percent,flash_sale_id',
            'flashSaleItem.flashSale:id,title',
        ])->find($id);

        if (!$booking) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        // Role-based access: sales can only view own + unassigned bookings.
        // Return 404 (not 403) so sales don't learn the booking exists.
        if (!$this->currentUserCanAccessBooking($booking)) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        return response()->json($booking);
    }

    /**
     * List event timeline for a booking (admin)
     */
    public function events(int $id)
    {
        $booking = Booking::select('id')->find($id);
        if (!$booking) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        $events = $booking->events()->get(['id', 'event_type', 'status', 'source', 'message', 'payload', 'user_id', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Update booking status
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,paid,cancelled,completed',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        $oldStatus = $booking->status;
        $booking->status = $request->status;

        // Track who cancelled
        if ($request->status === 'cancelled' && $oldStatus !== 'cancelled') {
            $booking->cancelled_by = 'admin';
        } elseif ($request->status !== 'cancelled') {
            $booking->cancelled_by = null;
        }

        if ($request->filled('admin_note')) {
            $booking->admin_note = $request->admin_note;
        }

        $booking->save();

        // Send status update email to customer
        if ($oldStatus !== $request->status) {
            try {
                BookingEmailService::sendStatusUpdate($booking);
            } catch (\Exception $e) {
                // Don't fail the status update if email fails
            }
        }

        // If cancelled and was flash sale → decrement quantity_sold
        if ($request->status === 'cancelled' && $oldStatus !== 'cancelled' && $booking->flash_sale_item_id) {
            $booking->flashSaleItem?->decrement('quantity_sold');
        }

        // Award member points when payment is confirmed
        $this->awardBookingPointsIfPaid($booking, $oldStatus);

        return response()->json([
            'success' => true,
            'message' => 'อัปเดตสถานะการจองสำเร็จ',
            'booking' => $booking->fresh(['member', 'tour', 'period', 'flashSaleItem']),
        ]);
    }

    /**
     * Delete a booking (admin only)
     */
    public function destroy(int $id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        // If active flash-sale booking, roll back the sold count
        if ($booking->flash_sale_item_id && $booking->status !== 'cancelled') {
            $booking->flashSaleItem?->decrement('quantity_sold');
        }

        $code = $booking->booking_code;
        $booking->delete();

        Log::info('Admin deleted booking', [
            'booking_id' => $id,
            'booking_code' => $code,
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ลบใบจองสำเร็จ',
        ]);
    }

    /**
     * Re-run the outbound (wholesaler API) booking flow for a booking that
     * previously failed. Idempotent — safe to call multiple times. Returns
     * the refreshed booking so the UI can inspect the new provider_status.
     */
    public function retryOutbound(int $id)
    {
        $booking = Booking::with('period')->find($id);
        if (!$booking) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        $period = $booking->period;
        if (!$period || !BookingService::isOutboundEnabledForPeriod($period)) {
            return response()->json([
                'success' => false,
                'message' => 'ทัวร์นี้ไม่ได้เปิดใช้งาน Outbound Booking API',
            ], 422);
        }

        // Only allow retry from a terminal/pending state — don't clobber an
        // in-flight or already-confirmed provider booking.
        if (in_array($booking->provider_status, ['confirmed'], true) && $booking->provider_booking_ref) {
            return response()->json([
                'success' => false,
                'message' => 'ใบจองนี้ยืนยันกับ provider แล้ว (ref: ' . $booking->provider_booking_ref . ')',
            ], 422);
        }

        try {
            $bookingService = app(BookingService::class);
            $booking = $bookingService->runOutboundForBooking($booking);
        } catch (\Throwable $e) {
            Log::error('Retry outbound booking failed', [
                'booking_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'ยิง API ไม่สำเร็จ: ' . $e->getMessage(),
            ], 500);
        }

        $confirmed = $booking->provider_status === 'confirmed' && $booking->provider_booking_ref;

        return response()->json([
            'success' => true,
            'message' => $confirmed
                ? 'ยิง API สำเร็จ (ref: ' . $booking->provider_booking_ref . ')'
                : 'ยิง API แล้ว — สถานะ: ' . ($booking->provider_status ?? 'unknown'),
            'is_confirmed_by_provider' => $confirmed,
            'booking' => $booking->fresh(['member', 'tour', 'period', 'flashSaleItem']),
        ]);
    }

    /**
     * Manually (re)send a confirmed booking snapshot to the nexttrip-invoice
     * webhook. This is a fallback for when the automatic queued job
     * (SendBookingToInvoice, dispatched on confirm) fails or the queue
     * worker is down. Runs synchronously so the admin gets immediate
     * success/failure feedback. Idempotent on the invoice side (keyed by
     * bookingCode), so it's safe to click even if a previous attempt
     * partially succeeded.
     */
    public function resendToInvoice(int $id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        if ($booking->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'ส่งไปยัง Invoice ได้เฉพาะใบจองที่ยืนยันแล้วเท่านั้น',
            ], 422);
        }

        try {
            SendBookingToInvoice::dispatchSync($booking->id);
        } catch (\Throwable $e) {
            Log::error('Manual resend booking to invoice failed', [
                'booking_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'ส่งไปยัง Invoice ไม่สำเร็จ: ' . $e->getMessage(),
            ], 500);
        }

        $booking = $booking->fresh(['member', 'tour', 'period', 'flashSaleItem']);

        return response()->json([
            'success' => true,
            'message' => $booking->invoice_sent_at
                ? 'ส่งไปยัง Invoice สำเร็จ'
                : 'ส่งคำขอแล้ว แต่ยังไม่ยืนยันผล — ตรวจสอบสถานะอีกครั้ง',
            'booking' => $booking,
        ]);
    }

    /**
     * Get booking statistics
     */
    public function statistics()
    {
        // Role-based visibility — wrap each count in the same visibility
        // filter so the counters shown on the dashboard match what the user
        // actually sees in the list.
        $scoped = fn () => tap(Booking::query(), fn ($q) => $this->applySaleVisibilityFilter($q));

        return response()->json([
            'total'           => $scoped()->count(),
            'pending'         => $scoped()->where('status', 'pending')->count(),
            'confirmed'       => $scoped()->where('status', 'confirmed')->count(),
            'paid'            => $scoped()->where('status', 'paid')->count(),
            'cancelled'       => $scoped()->where('status', 'cancelled')->count(),
            'completed'       => $scoped()->where('status', 'completed')->count(),
            'from_flash_sale' => $scoped()->where('source', 'flash_sale')->count(),
            'from_website'    => $scoped()->where('source', 'website')->count(),
        ]);
    }

    /**
     * Create new booking (manual - admin)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tour_id' => 'required|exists:tours,id',
            'period_id' => 'required|exists:tour_periods,id',
            'web_member_id' => 'nullable|integer|exists:web_members,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'required|string|max:20',
            'qty_adult' => 'required|integer|min:1',
            'qty_adult_single' => 'integer|min:0',
            'qty_child_bed' => 'integer|min:0',
            'qty_child_nobed' => 'integer|min:0',
            'qty_infant' => 'integer|min:0',
            'qty_triple' => 'integer|min:0',
            'qty_twin' => 'integer|min:0',
            'qty_double' => 'integer|min:0',
            'price_adult' => 'required|numeric|min:0',
            'price_single' => 'numeric|min:0',
            'price_child_bed' => 'numeric|min:0',
            'price_child_nobed' => 'numeric|min:0',
            'price_infant' => 'numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'sale_code' => 'nullable|string|max:50',
            'special_request' => 'nullable|string|max:1000',
            'admin_note' => 'nullable|string|max:1000',
            'status' => 'in:pending,confirmed,paid,cancelled,completed',
        ]);

        $booking = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'tour_id' => $validated['tour_id'],
            'period_id' => $validated['period_id'],
            'web_member_id' => $validated['web_member_id'] ?? null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'qty_adult' => $validated['qty_adult'],
            'qty_adult_single' => $validated['qty_adult_single'] ?? 0,
            'qty_child_bed' => $validated['qty_child_bed'] ?? 0,
            'qty_child_nobed' => $validated['qty_child_nobed'] ?? 0,
            'qty_infant' => $validated['qty_infant'] ?? 0,
            'qty_triple' => $validated['qty_triple'] ?? 0,
            'qty_twin' => $validated['qty_twin'] ?? 0,
            'qty_double' => $validated['qty_double'] ?? 0,
            'price_adult' => $validated['price_adult'],
            'price_single' => $validated['price_single'] ?? 0,
            'price_child_bed' => $validated['price_child_bed'] ?? 0,
            'price_child_nobed' => $validated['price_child_nobed'] ?? 0,
            'price_infant' => $validated['price_infant'] ?? 0,
            'total_amount' => $validated['total_amount'],
            'sale_code' => $validated['sale_code'] ?? null,
            'special_request' => $validated['special_request'] ?? null,
            'admin_note' => $validated['admin_note'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'source' => 'manual', // Admin manual creation
        ]);

        // Auto-push to provider when the period's wholesaler has booking
        // integration enabled. Same behaviour as the customer flow.
        $period = Period::find($booking->period_id);
        $outboundAttempted = $period ? BookingService::isOutboundEnabledForPeriod($period) : false;
        if ($outboundAttempted) {
            try {
                $bookingService = app(BookingService::class);
                $booking = $bookingService->runOutboundForBooking($booking);
            } catch (\Throwable $e) {
                Log::warning('Admin booking outbound failed (kept as manual)', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Award member points immediately if booking is created as paid
        $this->awardBookingPointsIfPaid($booking, null);

        return response()->json([
            'success' => true,
            'message' => 'สร้างใบจองสำเร็จ',
            'booking' => $booking->fresh(['tour', 'period']),
            'outbound_attempted' => $outboundAttempted,
            'is_confirmed_by_provider' => $booking->provider_status === 'confirmed' && $booking->provider_booking_ref,
        ], 201);
    }

    /**
     * Update booking (admin)
     */
    public function update(Request $request, int $id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
            'email' => 'email|max:100',
            'phone' => 'string|max:20',
            'qty_adult' => 'integer|min:1',
            'qty_adult_single' => 'integer|min:0',
            'qty_child_bed' => 'integer|min:0',
            'qty_child_nobed' => 'integer|min:0',
            'qty_infant' => 'integer|min:0',
            'qty_triple' => 'integer|min:0',
            'qty_twin' => 'integer|min:0',
            'qty_double' => 'integer|min:0',
            'price_adult' => 'numeric|min:0',
            'price_single' => 'numeric|min:0',
            'price_child_bed' => 'numeric|min:0',
            'price_child_nobed' => 'numeric|min:0',
            'price_infant' => 'numeric|min:0',
            'total_amount' => 'numeric|min:0',
            'sale_code' => 'nullable|string|max:50',
            'special_request' => 'nullable|string|max:1000',
            'admin_note' => 'nullable|string|max:1000',
            'status' => 'in:pending,confirmed,paid,cancelled,completed',
            'period_id' => 'exists:tour_periods,id',
        ]);

        // Handle flash sale cancellation
        $oldStatus = $booking->status;
        
        // Track who cancelled
        if (isset($validated['status'])) {
            if ($validated['status'] === 'cancelled' && $oldStatus !== 'cancelled') {
                $validated['cancelled_by'] = 'admin';
            } elseif ($validated['status'] !== 'cancelled') {
                $validated['cancelled_by'] = null;
            }
        }

        $booking->fill($validated);
        $booking->save();

        // Send status update email if status changed
        if (isset($validated['status']) && $oldStatus !== $validated['status']) {
            try {
                BookingEmailService::sendStatusUpdate($booking);
            } catch (\Exception $e) {
                // Don't fail the update if email fails
            }
        }

        // If cancelled and was flash sale → decrement quantity_sold
        if (isset($validated['status']) && $validated['status'] === 'cancelled' && $oldStatus !== 'cancelled' && $booking->flash_sale_item_id) {
            $booking->flashSaleItem?->decrement('quantity_sold');
        }

        // Award member points when payment is confirmed
        $this->awardBookingPointsIfPaid($booking, $oldStatus);

        return response()->json([
            'success' => true,
            'message' => 'แก้ไขใบจองสำเร็จ',
            'booking' => $booking->fresh(['member', 'tour', 'period', 'flashSaleItem']),
        ]);
    }

    /**
     * Award member points when a booking transitions to "paid".
     *
     * - Fires only when current status === 'paid' and oldStatus !== 'paid'
     * - Idempotent: skipped if a PointTransaction already exists for this booking
     * - Also updates lifetime_spending (drives auto level upgrade)
     * - Guest bookings (no web_member_id) are ignored
     */
    private function awardBookingPointsIfPaid(Booking $booking, ?string $oldStatus): void
    {
        if ($booking->status !== 'paid' || $oldStatus === 'paid') {
            return;
        }
        if (!$booking->web_member_id) {
            return;
        }

        $member = \App\Models\WebMember::find($booking->web_member_id);
        if (!$member) {
            return;
        }

        // Idempotency guard: don't award twice for the same booking
        $already = PointTransaction::where('source_type', Booking::class)
            ->where('source_id', $booking->id)
            ->where('type', 'earn')
            ->exists();
        if ($already) {
            return;
        }

        $amount = (float) $booking->total_amount;

        try {
            $service = app(PointService::class);
            // 1) Update lifetime_spending + auto level upgrade
            $service->recordSpending($member, $amount);
            // 2) Earn points via `booking` rule (calc_type=percent, 1pt per 100฿)
            $service->earnPoints(
                $member,
                'booking',
                $amount,
                Booking::class,
                $booking->id,
                "ชำระเงินการจอง {$booking->booking_code}"
            );

            Log::info('Booking points awarded', [
                'booking_id' => $booking->id,
                'member_id'  => $member->id,
                'amount'     => $amount,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to award booking points', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
