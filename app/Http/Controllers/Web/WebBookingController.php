<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WebMember;
use App\Models\Period;
use App\Models\Tour;
use App\Services\OtpService;
use Illuminate\Http\Request;
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
     * Uses same logic as register OTP but with 'booking' purpose
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

        // Request OTP with 'booking' purpose
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
     * Submit booking (DEBUG mode - no DB write)
     * Returns submitted data for verification
     */
    public function submit(Request $request)
    {
        $isLoggedIn = $request->user('sanctum') !== null;

        // Validation rules
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
            'sale_code' => 'nullable|string|max:50',
            'special_request' => 'nullable|string|max:1000',
            'consent_terms' => 'required|accepted',
        ];

        // Guest must provide verified OTP
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
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบทัวร์ที่ระบุ',
            ], 404);
        }

        $period = Period::where('id', $request->period_id)
            ->where('tour_id', $request->tour_id)
            ->first();

        if (!$period) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบรอบเดินทางที่ระบุ',
            ], 404);
        }

        // Get offer for pricing
        $offer = $period->offer;

        // Calculate pricing
        $qtyAdult = (int) $request->qty_adult;
        $qtyAdultSingle = (int) ($request->qty_adult_single ?? 0);
        $qtyChildBed = (int) ($request->qty_child_bed ?? 0);
        $qtyChildNoBed = (int) ($request->qty_child_nobed ?? 0);

        $priceAdult = $offer ? ($offer->price_adult - ($offer->discount_adult ?? 0)) : 0;
        $priceSingle = $offer && $offer->price_single ? ($offer->price_single - ($offer->discount_single ?? 0)) : 0;
        $priceChildBed = $offer && $offer->price_child ? ($offer->price_child - ($offer->discount_child_bed ?? 0)) : 0;
        $priceChildNoBed = $offer && $offer->price_child_nobed ? ($offer->price_child_nobed - ($offer->discount_child_nobed ?? 0)) : 0;

        $totalAdult = ($qtyAdult - $qtyAdultSingle) * $priceAdult;
        $totalSingle = $qtyAdultSingle * ($priceAdult + $priceSingle);
        $totalChildBed = $qtyChildBed * $priceChildBed;
        $totalChildNoBed = $qtyChildNoBed * $priceChildNoBed;
        $grandTotal = $totalAdult + $totalSingle + $totalChildBed + $totalChildNoBed;

        // Check member matching for guest bookings
        $matchedMember = null;
        if (!$isLoggedIn) {
            // Try to normalize phone
            try {
                $normalizedPhone = WebMember::normalizePhone($request->phone);
                $matchedMember = WebMember::where('first_name', $request->first_name)
                    ->where('last_name', $request->last_name)
                    ->where('phone', $normalizedPhone)
                    ->first();
            } catch (\Exception $e) {
                // Phone normalization failed, skip matching
            }
        }

        // DEBUG RESPONSE - no DB write
        $debugResponse = [
            'success' => true,
            'debug' => true,
            'message' => '🔍 DEBUG: ข้อมูลการจองทัวร์ (ยังไม่บันทึกลง DB)',
            'booking_data' => [
                'tour' => [
                    'id' => $tour->id,
                    'tour_code' => $tour->tour_code,
                    'title' => $tour->title,
                ],
                'period' => [
                    'id' => $period->id,
                    'start_date' => $period->start_date,
                    'end_date' => $period->end_date,
                    'capacity' => $period->capacity,
                    'booked' => $period->booked,
                    'available' => $period->available,
                ],
                'customer' => [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'is_logged_in' => $isLoggedIn,
                    'member_id' => $isLoggedIn ? $request->user('sanctum')->id : null,
                    'matched_existing_member' => $matchedMember ? [
                        'id' => $matchedMember->id,
                        'email' => $matchedMember->email,
                        'note' => 'ชื่อ, นามสกุล, เบอร์โทรตรงกับสมาชิกที่มีอยู่ — จะ Update ข้อมูล',
                    ] : null,
                ],
                'quantities' => [
                    'adult' => $qtyAdult,
                    'adult_single' => $qtyAdultSingle,
                    'child_bed' => $qtyChildBed,
                    'child_nobed' => $qtyChildNoBed,
                ],
                'pricing' => [
                    'price_adult' => $priceAdult,
                    'price_single' => $priceSingle,
                    'price_child_bed' => $priceChildBed,
                    'price_child_nobed' => $priceChildNoBed,
                    'total_adult' => $totalAdult,
                    'total_single' => $totalSingle,
                    'total_child_bed' => $totalChildBed,
                    'total_child_nobed' => $totalChildNoBed,
                    'grand_total' => $grandTotal,
                ],
                'extras' => [
                    'sale_code' => $request->sale_code,
                    'special_request' => $request->special_request,
                    'consent_terms' => true,
                ],
                'otp' => !$isLoggedIn ? [
                    'otp_request_id' => $request->otp_request_id,
                    'otp_verified' => $request->otp_verified,
                ] : 'ไม่ต้องใช้ OTP (Login แล้ว)',
            ],
        ];

        Log::info('Booking Debug Submit', $debugResponse);

        return response()->json($debugResponse);
    }
}
