<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Period;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Outbound booking lifecycle endpoints.
 *
 *   POST /api/bookings/outbound/quote
 *   POST /api/bookings/outbound/{id}/hold
 *   POST /api/bookings/outbound/{id}/confirm
 *   POST /api/bookings/outbound/{id}/cancel
 *
 * Listing + detail re-use the existing /api/bookings endpoints
 * (App\Http\Controllers\BookingController), which already return all columns
 * including the new provider_* fields.
 */
class BookingController extends Controller
{
    public function __construct(protected BookingService $service)
    {
    }

    public function quote(Request $r): JsonResponse
    {
        $data = $r->validate([
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'pax' => ['required', 'array'],
            'pax.adult' => ['integer', 'min:0'],
            'pax.adult_single' => ['integer', 'min:0'],
            'pax.child_bed' => ['integer', 'min:0'],
            'pax.child_nobed' => ['integer', 'min:0'],
            'pax.infant' => ['integer', 'min:0'],
            'pax.rooms' => ['array'],
            'pax.rooms.*.code' => ['required_with:pax.rooms', 'string'],
            'pax.rooms.*.num' => ['required_with:pax.rooms', 'integer', 'min:1'],
            'customer' => ['array'],
            'customer.first_name' => ['nullable', 'string', 'max:100'],
            'customer.last_name' => ['nullable', 'string', 'max:100'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:20'],
            'customer.web_member_id' => ['nullable', 'integer'],
        ]);

        $totalPax = (int) ($data['pax']['adult'] ?? 0)
            + (int) ($data['pax']['adult_single'] ?? 0)
            + (int) ($data['pax']['child_bed'] ?? 0)
            + (int) ($data['pax']['child_nobed'] ?? 0)
            + (int) ($data['pax']['infant'] ?? 0);

        if ($totalPax < 1) {
            return response()->json(['message' => 'At least one passenger is required'], 422);
        }

        $period = Period::findOrFail($data['period_id']);

        try {
            $booking = $this->service->quote($period, $data['pax'], $data['customer'] ?? []);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($booking->fresh(['passengers']), 201);
    }

    public function hold(Request $r, int $id): JsonResponse
    {
        $data = $r->validate([
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.type' => ['required', 'in:adult,child,infant'],
            'passengers.*.title' => ['nullable', 'string', 'max:20'],
            'passengers.*.first_name' => ['required', 'string', 'max:100'],
            'passengers.*.last_name' => ['required', 'string', 'max:100'],
            'passengers.*.first_name_th' => ['nullable', 'string', 'max:100'],
            'passengers.*.last_name_th' => ['nullable', 'string', 'max:100'],
            'passengers.*.dob' => ['nullable', 'date'],
            'passengers.*.gender' => ['nullable', 'in:male,female,other'],
            'passengers.*.passport_no' => ['nullable', 'string', 'max:50'],
            'passengers.*.nationality' => ['nullable', 'string', 'max:50'],
            'passengers.*.passport_expiry' => ['nullable', 'date'],
            'passengers.*.passport_issue_date' => ['nullable', 'date'],
            'passengers.*.passport_issue_country' => ['nullable', 'string', 'max:50'],
            'passengers.*.email' => ['nullable', 'email', 'max:255'],
            'passengers.*.phone' => ['nullable', 'string', 'max:30'],
            'passengers.*.special_request' => ['nullable', 'string'],
            'passengers.*.is_lead' => ['nullable', 'boolean'],
            'passengers.*.room_type' => ['nullable', 'string', 'max:20'],
            'passengers.*.room_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $booking = Booking::findOrFail($id);

        try {
            $booking = $this->service->hold($booking, $data['passengers']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($booking);
    }

    public function confirm(int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        try {
            $booking = $this->service->confirm($booking);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($booking);
    }

    public function cancel(Request $r, int $id): JsonResponse
    {
        $data = $r->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $booking = Booking::findOrFail($id);

        try {
            $booking = $this->service->cancel($booking, $data['reason'] ?? '');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($booking);
    }
}
