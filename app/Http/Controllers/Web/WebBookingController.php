<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FlashSaleItem;
use App\Models\WebMember;
use App\Models\Period;
use App\Models\Tour;
use App\Services\OtpService;
use App\Services\BookingEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WebBookingController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Request OTP for booking (guest only)
     */
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->otpService->requestOtp(
            $request->phone,
            'booking',
            $request->ip(),
            $request->userAgent()
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Verify OTP for booking (guest only)
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp_request_id' => 'required|integer',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->otpService->verifyOtp($request->otp_request_id, $request->otp);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Submit regular booking (guest + member) — now saves to DB
     */
    public function submit(Request $request)
    {
        $isLoggedIn = $request->user('sanctum') !== null;

        $rules = [
            'tour_id' => 'required|integer',
            'period_id' => 'required|integer',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|min:10|max:15',
            'qty_adult' => 'required|integer|min:1',
            'qty_adult_single' => 'integer|min:0',
            'qty_child_bed' => 'integer|min:0',
            'qty_child_nobed' => 'integer|min:0',
            'qty_infant' => 'integer|min:0',
            'qty_triple' => 'integer|min:0',
            'qty_twin' => 'integer|min:0',
            'qty_double' => 'integer|min:0',
            'sale_code' => 'nullable|string|max:50',
            'special_request' => 'nullable|string|max:1000',
            'consent_terms' => 'required|accepted',
        ];

        if (!$isLoggedIn) {
            $rules['otp_request_id'] = 'required|integer';
            $rules['otp_verified'] = 'required|boolean';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Load tour and period
        $tour = Tour::find($request->tour_id);
        if (!$tour) {
            return response()->json(['success' => false, 'message' => 'ไม่พบทัวร์ที่ระบุ'], 404);
        }

        $period = Period::where('id', $request->period_id)
            ->where('tour_id', $request->tour_id)
            ->first();

        if (!$period) {
            return response()->json(['success' => false, 'message' => 'ไม่พบรอบเดินทางที่ระบุ'], 404);
        }

        $offer = $period->offer;

        // Calculate pricing
        $qtyAdult = (int) $request->qty_adult;
        $qtyAdultSingle = (int) ($request->qty_adult_single ?? 0);
        $qtyChildBed = (int) ($request->qty_child_bed ?? 0);
        $qtyChildNoBed = (int) ($request->qty_child_nobed ?? 0);
        $qtyInfant = (int) ($request->qty_infant ?? 0);
        $qtyTriple = (int) ($request->qty_triple ?? 0);
        $qtyTwin = (int) ($request->qty_twin ?? 0);
        $qtyDouble = (int) ($request->qty_double ?? 0);

        $priceAdult = $offer ? ($offer->price_adult - ($offer->discount_adult ?? 0)) : 0;
        $priceSingle = $offer && $offer->price_single ? ($offer->price_single - ($offer->discount_single ?? 0)) : 0;
        $priceChildBed = $offer && $offer->price_child ? ($offer->price_child - ($offer->discount_child_bed ?? 0)) : 0;
        $priceChildNoBed = $offer && $offer->price_child_nobed ? ($offer->price_child_nobed - ($offer->discount_child_nobed ?? 0)) : 0;
        $priceInfant = $offer && $offer->price_infant ? $offer->price_infant : 0;

        $totalAdult = ($qtyAdult - $qtyAdultSingle) * $priceAdult;
        $totalSingle = $qtyAdultSingle * ($priceAdult + $priceSingle);
        $totalChildBed = $qtyChildBed * $priceChildBed;
        $totalChildNoBed = $qtyChildNoBed * $priceChildNoBed;
        $totalInfant = $qtyInfant * $priceInfant;
        $grandTotal = $totalAdult + $totalSingle + $totalChildBed + $totalChildNoBed + $totalInfant;

        // Resolve member
        $memberId = null;
        if ($isLoggedIn) {
            $memberId = $request->user('sanctum')->id;
        } else {
            try {
                $normalizedPhone = WebMember::normalizePhone($request->phone);
                $matchedMember = WebMember::where('first_name', $request->first_name)
                    ->where('last_name', $request->last_name)
                    ->where('phone', $normalizedPhone)
                    ->first();
                if ($matchedMember) {
                    $memberId = $matchedMember->id;
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        // If still no member → create one by phone
        if (!$memberId) {
            try {
                $normalizedPhone = WebMember::normalizePhone($request->phone);
                $member = WebMember::firstOrCreate(
                    ['phone' => $normalizedPhone],
                    [
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'email' => $request->email,
                        'password' => bcrypt(str()->random(16)),
                        'phone_verified' => true,
                        'status' => 'active',
                    ]
                );
                $memberId = $member->id;
            } catch (\Exception $e) {
                Log::error('Booking: Failed to create member', ['error' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'ไม่สามารถสร้างข้อมูลสมาชิกได้'], 500);
            }
        }

        try {
            $booking = Booking::create([
                'booking_code' => Booking::generateBookingCode(),
                'web_member_id' => $memberId,
                'tour_id' => $tour->id,
                'period_id' => $period->id,
                'qty_adult' => $qtyAdult,
                'qty_adult_single' => $qtyAdultSingle,
                'qty_child_bed' => $qtyChildBed,
                'qty_child_nobed' => $qtyChildNoBed,
                'qty_infant' => $qtyInfant,
                'qty_triple' => $qtyTriple,
                'qty_twin' => $qtyTwin,
                'qty_double' => $qtyDouble,
                'price_adult' => $priceAdult,
                'price_single' => $priceSingle,
                'price_child_bed' => $priceChildBed,
                'price_child_nobed' => $priceChildNoBed,
                'price_infant' => $priceInfant,
                'total_amount' => $grandTotal,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'sale_code' => $request->sale_code,
                'special_request' => $request->special_request,
                'status' => 'pending',
                'source' => 'website',
            ]);

            Log::info('Booking created', ['booking_code' => $booking->booking_code, 'id' => $booking->id]);

            // Send booking confirmation email (async-safe, never throws)
            try {
                BookingEmailService::sendBookingConfirmation($booking);
            } catch (\Exception $e) {
                Log::warning('Booking email failed but booking is OK', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'จองทัวร์สำเร็จ รอการยืนยันจากเจ้าหน้าที่',
                'booking' => [
                    'id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'tour_title' => $tour->title,
                    'period' => $period->start_date . ' - ' . $period->end_date,
                    'total_amount' => $grandTotal,
                    'status' => $booking->status,
                    'status_label' => $booking->status_label,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Booking creation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการจอง กรุณาลองใหม่'], 500);
        }
    }

    /**
     * Submit flash sale booking (requires login)
     */
    public function submitFlashSale(Request $request)
    {
        $member = $request->user('sanctum');
        if (!$member) {
            return response()->json(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อนจองทัวร์'], 401);
        }

        $validator = Validator::make($request->all(), [
            'flash_sale_item_id' => 'required|integer',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|min:10|max:15',
            'qty_adult' => 'required|integer|min:1',
            'qty_adult_single' => 'integer|min:0',
            'qty_child_bed' => 'integer|min:0',
            'qty_child_nobed' => 'integer|min:0',
            'special_request' => 'nullable|string|max:1000',
            'consent_terms' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Load flash sale item
        $flashItem = FlashSaleItem::with(['flashSale', 'tour', 'period'])
            ->where('id', $request->flash_sale_item_id)
            ->where('is_active', true)
            ->first();

        if (!$flashItem) {
            return response()->json(['success' => false, 'message' => 'ไม่พบรายการ Flash Sale ที่ระบุ'], 404);
        }

        // Check flash sale is still running
        $flashSale = $flashItem->flashSale;
        if (!$flashSale || !$flashSale->is_active || !$flashSale->isRunning()) {
            return response()->json(['success' => false, 'message' => 'Flash Sale นี้ได้สิ้นสุดลงแล้ว'], 400);
        }

        // Check per-item deadline
        if ($flashItem->flash_end_date && $flashItem->flash_end_date->isPast()) {
            return response()->json(['success' => false, 'message' => 'รายการ Flash Sale นี้หมดเวลาแล้ว'], 400);
        }

        // Check stock
        if ($flashItem->isSoldOut()) {
            return response()->json(['success' => false, 'message' => 'รายการ Flash Sale นี้ขายหมดแล้ว'], 400);
        }

        $tour = $flashItem->tour;
        $period = $flashItem->period;

        if (!$tour || !$period) {
            return response()->json(['success' => false, 'message' => 'ไม่พบทัวร์หรือรอบเดินทาง'], 404);
        }

        // Flash price for adult, normal offer prices for child/single
        $flashPrice = (float) $flashItem->flash_price;
        $offer = $period->offer;

        $qtyAdult = (int) $request->qty_adult;
        $qtyAdultSingle = (int) ($request->qty_adult_single ?? 0);
        $qtyChildBed = (int) ($request->qty_child_bed ?? 0);
        $qtyChildNoBed = (int) ($request->qty_child_nobed ?? 0);
        $qtyInfant = (int) ($request->qty_infant ?? 0);
        $qtyTriple = (int) ($request->qty_triple ?? 0);
        $qtyTwin = (int) ($request->qty_twin ?? 0);
        $qtyDouble = (int) ($request->qty_double ?? 0);

        $priceAdult = $flashPrice;
        $priceSingle = $offer && $offer->price_single ? ($offer->price_single - ($offer->discount_single ?? 0)) : 0;
        $priceChildBed = $offer && $offer->price_child ? ($offer->price_child - ($offer->discount_child_bed ?? 0)) : 0;
        $priceChildNoBed = $offer && $offer->price_child_nobed ? ($offer->price_child_nobed - ($offer->discount_child_nobed ?? 0)) : 0;
        $priceInfant = $offer && $offer->price_infant ? $offer->price_infant : 0;

        $totalAdult = ($qtyAdult - $qtyAdultSingle) * $priceAdult;
        $totalSingle = $qtyAdultSingle * ($priceAdult + $priceSingle);
        $totalChildBed = $qtyChildBed * $priceChildBed;
        $totalChildNoBed = $qtyChildNoBed * $priceChildNoBed;
        $totalInfant = $qtyInfant * $priceInfant;
        $grandTotal = $totalAdult + $totalSingle + $totalChildBed + $totalChildNoBed + $totalInfant;

        try {
            $booking = DB::transaction(function () use (
                $member, $tour, $period, $flashItem, $request,
                $qtyAdult, $qtyAdultSingle, $qtyChildBed, $qtyChildNoBed, $qtyInfant,
                $qtyTriple, $qtyTwin, $qtyDouble,
                $priceAdult, $priceSingle, $priceChildBed, $priceChildNoBed, $priceInfant, $grandTotal
            ) {
                // Lock & check stock
                $item = FlashSaleItem::lockForUpdate()->find($flashItem->id);
                if ($item->quantity_limit !== null && $item->quantity_sold >= $item->quantity_limit) {
                    throw new \Exception('SOLD_OUT');
                }
                $item->increment('quantity_sold');

                return Booking::create([
                    'booking_code' => Booking::generateBookingCode(),
                    'web_member_id' => $member->id,
                    'tour_id' => $tour->id,
                    'period_id' => $period->id,
                    'flash_sale_item_id' => $item->id,
                    'qty_adult' => $qtyAdult,
                    'qty_adult_single' => $qtyAdultSingle,
                    'qty_child_bed' => $qtyChildBed,
                    'qty_child_nobed' => $qtyChildNoBed,
                    'qty_infant' => $qtyInfant,
                    'qty_triple' => $qtyTriple,
                    'qty_twin' => $qtyTwin,
                    'qty_double' => $qtyDouble,
                    'price_adult' => $priceAdult,
                    'price_single' => $priceSingle,
                    'price_child_bed' => $priceChildBed,
                    'price_child_nobed' => $priceChildNoBed,
                    'price_infant' => $priceInfant,
                    'total_amount' => $grandTotal,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'special_request' => $request->special_request,
                    'status' => 'pending',
                    'source' => 'flash_sale',
                ]);
            });

            Log::info('Flash Sale Booking created', [
                'booking_code' => $booking->booking_code,
                'flash_sale_item_id' => $flashItem->id,
                'member_id' => $member->id,
            ]);

            // Send booking confirmation email
            try {
                BookingEmailService::sendBookingConfirmation($booking);
            } catch (\Exception $e) {
                Log::warning('Booking email failed but booking is OK', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'จอง Flash Sale สำเร็จ! รอการยืนยันจากเจ้าหน้าที่',
                'booking' => [
                    'id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'tour_title' => $tour->title,
                    'period' => $period->start_date . ' - ' . $period->end_date,
                    'flash_price' => $priceAdult,
                    'total_amount' => $grandTotal,
                    'status' => $booking->status,
                    'status_label' => $booking->status_label,
                ],
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'SOLD_OUT') {
                return response()->json(['success' => false, 'message' => 'รายการ Flash Sale นี้ขายหมดแล้ว'], 400);
            }
            Log::error('Flash Sale Booking failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการจอง กรุณาลองใหม่'], 500);
        }
    }

    /**
     * Get member's bookings
     */
    public function myBookings(Request $request)
    {
        $member = $request->user('sanctum');
        if (!$member) {
            return response()->json(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
        }

        $bookings = Booking::where('web_member_id', $member->id)
            ->with([
                'tour:id,title,slug,tour_code,cover_image_url,custom_cover_image_url,cover_image_source',
                'tour.countries:id,name_th',
                'period:id,start_date,end_date',
                'flashSaleItem:id,flash_price,discount_percent'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Transform to add effective_cover_image_url
        $bookings->getCollection()->transform(function ($booking) {
            if ($booking->tour) {
                $booking->tour->effective_cover_image_url = 
                    ($booking->tour->cover_image_source === 'custom' && $booking->tour->custom_cover_image_url)
                        ? $booking->tour->custom_cover_image_url
                        : $booking->tour->cover_image_url;
                
                // Get first country name
                $booking->tour->destination = $booking->tour->countries->first()?->name_th ?? 'ไม่ระบุ';
                unset($booking->tour->countries);
            }
            return $booking;
        });

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    /**
     * Get a single booking detail for the authenticated member
     */
    public function showBooking(Request $request, $id)
    {
        $member = $request->user('sanctum');
        if (!$member) {
            return response()->json(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
        }

        $booking = Booking::where('id', $id)
            ->where('web_member_id', $member->id)
            ->with([
                'tour:id,title,slug,tour_code,cover_image_url,custom_cover_image_url,cover_image_source',
                'tour.countries:id,name_th',
                'period:id,start_date,end_date',
                'flashSaleItem:id,flash_price,discount_percent'
            ])
            ->first();

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        // Add effective_cover_image_url
        if ($booking->tour) {
            $booking->tour->effective_cover_image_url = 
                ($booking->tour->cover_image_source === 'custom' && $booking->tour->custom_cover_image_url)
                    ? $booking->tour->custom_cover_image_url
                    : $booking->tour->cover_image_url;
            
            $booking->tour->destination = $booking->tour->countries->first()?->name_th ?? 'ไม่ระบุ';
            unset($booking->tour->countries);
        }

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }
}
