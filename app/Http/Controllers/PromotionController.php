<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    /**
     * List all promotions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::query();

        if ($request->boolean('is_active', true)) {
            $query->active();
        }

        $promotions = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $promotions,
        ]);
    }

    /**
     * Store a new promotion.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:promotions,code',
            'description' => 'nullable|string',
            'type' => 'required|in:discount_amount,discount_percent,free_gift,installment,special',
            'discount_value' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $promotion = Promotion::create($validated);

        return response()->json([
            'success' => true,
            'data' => $promotion,
            'message' => 'Promotion created successfully',
        ], 201);
    }

    /**
     * Update a promotion.
     */
    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => 'nullable|string|max:50|unique:promotions,code,' . $promotion->id,
            'description' => 'nullable|string',
            'type' => 'in:discount_amount,discount_percent,free_gift,installment,special',
            'discount_value' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $promotion->update($validated);

        return response()->json([
            'success' => true,
            'data' => $promotion,
            'message' => 'Promotion updated successfully',
        ]);
    }

    /**
     * Delete a promotion.
     */
    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promotion deleted successfully',
        ]);
    }
}
