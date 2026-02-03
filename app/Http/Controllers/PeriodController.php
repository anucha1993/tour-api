<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PeriodController extends Controller
{
    /**
     * Display periods for a tour.
     */
    public function index(Request $request, Tour $tour): JsonResponse
    {
        $query = $tour->periods()->with(['offer.promotion']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        // Future only
        if ($request->has('future_only') && filter_var($request->future_only, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('start_date', '>=', now()->toDateString());
        }

        $periods = $query->orderBy('start_date')->get();

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }

    /**
     * Store a new period.
     */
    public function store(Request $request, Tour $tour): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => 'nullable|string|max:50',
            'period_code' => 'nullable|string|max:50',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'capacity' => 'required|integer|min:1',
            'booked' => 'nullable|integer|min:0',
            'status' => 'nullable|in:open,closed,sold_out,cancelled',
            'is_visible' => 'nullable|boolean',
            'sale_status' => 'nullable|in:available,booking,sold_out',
            // Offer data
            'price_adult' => 'required|numeric|min:0',
            'discount_adult' => 'nullable|numeric|min:0',
            'price_child' => 'nullable|numeric|min:0',
            'discount_child_bed' => 'nullable|numeric|min:0',
            'price_child_nobed' => 'nullable|numeric|min:0',
            'discount_child_nobed' => 'nullable|numeric|min:0',
            'price_infant' => 'nullable|numeric|min:0',
            'price_joinland' => 'nullable|numeric|min:0',
            'price_single' => 'nullable|numeric|min:0',
            'discount_single' => 'nullable|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'cancellation_policy' => 'required|string',
            'notes' => 'nullable|string',
            // Promo
            'promo_name' => 'nullable|string|max:255',
            'promo_start_date' => 'nullable|date',
            'promo_end_date' => 'nullable|date|after_or_equal:promo_start_date',
            'promo_quota' => 'nullable|integer|min:0',
            'promotion_id' => 'nullable|exists:promotions,id',
        ]);

        // Generate codes if not provided
        if (empty($validated['external_id'])) {
            $validated['external_id'] = 'PD-' . $tour->tour_code . '-' . now()->format('ymdHis');
        }
        if (empty($validated['period_code'])) {
            $validated['period_code'] = $tour->tour_code . '-' . date('ymd', strtotime($validated['start_date']));
        }

        // Calculate available seats
        $booked = $validated['booked'] ?? 0;
        $validated['available'] = $validated['capacity'] - $booked;
        $validated['booked'] = $booked;

        // Extract offer data
        $offerData = [
            'price_adult' => $validated['price_adult'],
            'discount_adult' => $validated['discount_adult'] ?? 0,
            'price_child' => $validated['price_child'] ?? null,
            'discount_child_bed' => $validated['discount_child_bed'] ?? 0,
            'price_child_nobed' => $validated['price_child_nobed'] ?? null,
            'discount_child_nobed' => $validated['discount_child_nobed'] ?? 0,
            'price_infant' => $validated['price_infant'] ?? null,
            'price_joinland' => $validated['price_joinland'] ?? null,
            'price_single' => $validated['price_single'] ?? null,
            'discount_single' => $validated['discount_single'] ?? 0,
            'deposit' => $validated['deposit'] ?? null,
            'cancellation_policy' => $validated['cancellation_policy'],
            'notes' => $validated['notes'] ?? null,
            'currency' => 'THB',
            'promo_name' => $validated['promo_name'] ?? null,
            'promo_start_date' => $validated['promo_start_date'] ?? null,
            'promo_end_date' => $validated['promo_end_date'] ?? null,
            'promo_quota' => $validated['promo_quota'] ?? null,
            'promotion_id' => $validated['promotion_id'] ?? null,
        ];

        unset(
            $validated['price_adult'],
            $validated['discount_adult'],
            $validated['price_child'],
            $validated['discount_child_bed'],
            $validated['price_child_nobed'],
            $validated['discount_child_nobed'],
            $validated['price_infant'],
            $validated['price_joinland'],
            $validated['price_single'],
            $validated['discount_single'],
            $validated['deposit'],
            $validated['cancellation_policy'],
            $validated['notes'],
            $validated['promo_name'],
            $validated['promo_start_date'],
            $validated['promo_end_date'],
            $validated['promo_quota'],
            $validated['promotion_id']
        );

        $period = $tour->periods()->create($validated);

        // Create offer
        $period->offer()->create($offerData);

        // Recalculate tour aggregates
        $tour->recalculateAggregates();

        $period->load('offer.promotion');

        return response()->json([
            'success' => true,
            'data' => $period,
            'message' => 'Period created successfully',
        ], 201);
    }

    /**
     * Display a period.
     */
    public function show(Tour $tour, Period $period): JsonResponse
    {
        $period->load('offer.promotion');

        return response()->json([
            'success' => true,
            'data' => $period,
        ]);
    }

    /**
     * Update a period.
     */
    public function update(Request $request, Tour $tour, Period $period): JsonResponse
    {
        $validated = $request->validate([
            'period_code' => 'nullable|string|max:50',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'capacity' => 'sometimes|required|integer|min:1',
            'booked' => 'nullable|integer|min:0',
            'status' => 'nullable|in:open,closed,sold_out,cancelled',
            'is_visible' => 'nullable|boolean',
            'sale_status' => 'nullable|in:available,booking,sold_out',
            // Offer data
            'price_adult' => 'sometimes|required|numeric|min:0',
            'discount_adult' => 'nullable|numeric|min:0',
            'price_child' => 'nullable|numeric|min:0',
            'discount_child_bed' => 'nullable|numeric|min:0',
            'price_child_nobed' => 'nullable|numeric|min:0',
            'discount_child_nobed' => 'nullable|numeric|min:0',
            'price_infant' => 'nullable|numeric|min:0',
            'price_joinland' => 'nullable|numeric|min:0',
            'price_single' => 'nullable|numeric|min:0',
            'discount_single' => 'nullable|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'cancellation_policy' => 'nullable|string',
            'notes' => 'nullable|string',
            // Promo
            'promo_name' => 'nullable|string|max:255',
            'promo_start_date' => 'nullable|date',
            'promo_end_date' => 'nullable|date|after_or_equal:promo_start_date',
            'promo_quota' => 'nullable|integer|min:0',
            'promotion_id' => 'nullable|exists:promotions,id',
        ]);

        // Calculate available seats if capacity or booked changed
        if (isset($validated['capacity']) || isset($validated['booked'])) {
            $capacity = $validated['capacity'] ?? $period->capacity;
            $booked = $validated['booked'] ?? $period->booked;
            $validated['available'] = $capacity - $booked;
        }

        // Extract offer data
        $offerFields = ['price_adult', 'discount_adult', 'price_child', 'discount_child_bed', 'price_child_nobed', 'discount_child_nobed', 'price_infant', 'price_joinland', 'price_single', 'discount_single', 'deposit', 'cancellation_policy', 'notes', 'promo_name', 'promo_start_date', 'promo_end_date', 'promo_quota', 'promotion_id'];
        $offerData = [];
        foreach ($offerFields as $field) {
            if (array_key_exists($field, $validated)) {
                $offerData[$field] = $validated[$field];
                unset($validated[$field]);
            }
        }

        $period->update($validated);

        // Update offer if data provided
        if (!empty($offerData) && $period->offer) {
            $period->offer->update($offerData);
        }

        // Recalculate tour aggregates
        $tour->recalculateAggregates();

        $period->load('offer.promotion');

        return response()->json([
            'success' => true,
            'data' => $period,
            'message' => 'Period updated successfully',
        ]);
    }

    /**
     * Delete a period.
     */
    public function destroy(Tour $tour, Period $period): JsonResponse
    {
        $period->delete();

        // Recalculate tour aggregates
        $tour->recalculateAggregates();

        return response()->json([
            'success' => true,
            'message' => 'Period deleted successfully',
        ]);
    }

    /**
     * Toggle period status.
     */
    public function toggleStatus(Tour $tour, Period $period): JsonResponse
    {
        $newStatus = match($period->status) {
            'open' => 'closed',
            'closed' => 'open',
            'sold_out' => 'closed',
            'cancelled' => 'closed',
        };

        $period->update(['status' => $newStatus]);

        // Recalculate tour aggregates
        $tour->recalculateAggregates();

        return response()->json([
            'success' => true,
            'data' => $period,
            'message' => "Period status changed to {$newStatus}",
        ]);
    }

    /**
     * Bulk update periods.
     */
    public function bulkUpdate(Request $request, Tour $tour): JsonResponse
    {
        $validated = $request->validate([
            'period_ids' => 'required|array',
            'period_ids.*' => 'exists:periods,id',
            'updates' => 'required|array',
            'updates.is_visible' => 'nullable|boolean',
            'updates.sale_status' => 'nullable|in:available,booking,sold_out',
            'updates.status' => 'nullable|in:open,closed,sold_out,cancelled',
        ]);

        $updateData = [];
        if (isset($validated['updates']['is_visible'])) {
            $updateData['is_visible'] = $validated['updates']['is_visible'];
        }
        if (isset($validated['updates']['sale_status'])) {
            $updateData['sale_status'] = $validated['updates']['sale_status'];
        }
        if (isset($validated['updates']['status'])) {
            $updateData['status'] = $validated['updates']['status'];
        }

        if (!empty($updateData)) {
            Period::whereIn('id', $validated['period_ids'])
                ->where('tour_id', $tour->id)
                ->update($updateData);
        }

        // Recalculate tour aggregates
        $tour->recalculateAggregates();

        return response()->json([
            'success' => true,
            'message' => 'Periods updated successfully',
        ]);
    }

    /**
     * Mass update promo for periods.
     */
    public function massUpdatePromo(Request $request, Tour $tour): JsonResponse
    {
        $validated = $request->validate([
            'period_ids' => 'required|array',
            'period_ids.*' => 'exists:periods,id',
            'promotion_id' => 'nullable|exists:promotions,id',
            'promo_name' => 'nullable|string|max:255',
            'promo_start_date' => 'nullable|date',
            'promo_end_date' => 'nullable|date|after_or_equal:promo_start_date',
            'promo_quota' => 'nullable|integer|min:0',
        ]);

        $periods = Period::whereIn('id', $validated['period_ids'])
            ->where('tour_id', $tour->id)
            ->with('offer')
            ->get();

        $updateData = [];
        if (array_key_exists('promotion_id', $validated)) {
            $updateData['promotion_id'] = $validated['promotion_id'];
        }
        if (isset($validated['promo_name'])) {
            $updateData['promo_name'] = $validated['promo_name'];
        }
        if (isset($validated['promo_start_date'])) {
            $updateData['promo_start_date'] = $validated['promo_start_date'];
        }
        if (isset($validated['promo_end_date'])) {
            $updateData['promo_end_date'] = $validated['promo_end_date'];
        }
        if (isset($validated['promo_quota'])) {
            $updateData['promo_quota'] = $validated['promo_quota'];
        }

        foreach ($periods as $period) {
            if ($period->offer && !empty($updateData)) {
                $period->offer->update($updateData);
            }
        }

        // Recalculate tour aggregates
        $tour->recalculateAggregates();

        return response()->json([
            'success' => true,
            'message' => 'Promo updated for ' . count($periods) . ' periods',
        ]);
    }

    /**
     * Mass update discount for periods.
     */
    public function massUpdateDiscount(Request $request, Tour $tour): JsonResponse
    {
        $validated = $request->validate([
            'period_ids' => 'required|array',
            'period_ids.*' => 'exists:periods,id',
            'discount_adult' => 'nullable|numeric|min:0',
            'discount_single' => 'nullable|numeric|min:0',
            'discount_child_bed' => 'nullable|numeric|min:0',
            'discount_child_nobed' => 'nullable|numeric|min:0',
        ]);

        $periods = Period::whereIn('id', $validated['period_ids'])
            ->where('tour_id', $tour->id)
            ->with('offer')
            ->get();

        $updateData = [];
        if (isset($validated['discount_adult']) && $validated['discount_adult'] > 0) {
            $updateData['discount_adult'] = $validated['discount_adult'];
        }
        if (isset($validated['discount_single']) && $validated['discount_single'] > 0) {
            $updateData['discount_single'] = $validated['discount_single'];
        }
        if (isset($validated['discount_child_bed']) && $validated['discount_child_bed'] > 0) {
            $updateData['discount_child_bed'] = $validated['discount_child_bed'];
        }
        if (isset($validated['discount_child_nobed']) && $validated['discount_child_nobed'] > 0) {
            $updateData['discount_child_nobed'] = $validated['discount_child_nobed'];
        }

        foreach ($periods as $period) {
            if ($period->offer && !empty($updateData)) {
                $period->offer->update($updateData);
            }
        }

        // Recalculate tour aggregates
        $tour->recalculateAggregates();

        return response()->json([
            'success' => true,
            'message' => 'Discount updated for ' . count($periods) . ' periods',
        ]);
    }
}
