<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\TourItinerary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TourItineraryController extends Controller
{
    /**
     * Display a listing of itineraries for a tour.
     */
    public function index(Tour $tour): JsonResponse
    {
        try {
            $itineraries = $tour->itineraries()
                ->orderBy('day_number')
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $itineraries,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch itineraries', [
                'tour_id' => $tour->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch itineraries',
            ], 500);
        }
    }

    /**
     * Store a newly created itinerary.
     */
    public function store(Request $request, Tour $tour): JsonResponse
    {
        try {
            $validated = $request->validate([
                'day_number' => 'required|integer|min:1',
                'title' => 'nullable|string|max:500',
                'description' => 'nullable|string',
                'places' => 'nullable|array',
                'places.*' => 'string|max:255',
                'has_breakfast' => 'nullable|boolean',
                'has_lunch' => 'nullable|boolean',
                'has_dinner' => 'nullable|boolean',
                'meals_note' => 'nullable|string|max:500',
                'accommodation' => 'nullable|string|max:500',
                'hotel_star' => 'nullable|integer|min:1|max:5',
                'images' => 'nullable|array',
                'images.*' => 'string|url',
                'sort_order' => 'nullable|integer',
            ]);

            $itinerary = $tour->itineraries()->create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Itinerary created successfully',
                'data' => $itinerary,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create itinerary', [
                'tour_id' => $tour->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create itinerary',
            ], 500);
        }
    }

    /**
     * Display the specified itinerary.
     */
    public function show(Tour $tour, TourItinerary $itinerary): JsonResponse
    {
        // Ensure itinerary belongs to the tour
        if ($itinerary->tour_id !== $tour->id) {
            return response()->json([
                'success' => false,
                'message' => 'Itinerary not found in this tour',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $itinerary,
        ]);
    }

    /**
     * Update the specified itinerary.
     */
    public function update(Request $request, Tour $tour, TourItinerary $itinerary): JsonResponse
    {
        // Ensure itinerary belongs to the tour
        if ($itinerary->tour_id !== $tour->id) {
            return response()->json([
                'success' => false,
                'message' => 'Itinerary not found in this tour',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'day_number' => 'sometimes|required|integer|min:1',
                'title' => 'nullable|string|max:500',
                'description' => 'nullable|string',
                'places' => 'nullable|array',
                'places.*' => 'string|max:255',
                'has_breakfast' => 'nullable|boolean',
                'has_lunch' => 'nullable|boolean',
                'has_dinner' => 'nullable|boolean',
                'meals_note' => 'nullable|string|max:500',
                'accommodation' => 'nullable|string|max:500',
                'hotel_star' => 'nullable|integer|min:1|max:5',
                'images' => 'nullable|array',
                'images.*' => 'string|url',
                'sort_order' => 'nullable|integer',
            ]);

            $itinerary->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Itinerary updated successfully',
                'data' => $itinerary->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update itinerary', [
                'tour_id' => $tour->id,
                'itinerary_id' => $itinerary->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update itinerary',
            ], 500);
        }
    }

    /**
     * Remove the specified itinerary.
     */
    public function destroy(Tour $tour, TourItinerary $itinerary): JsonResponse
    {
        // Ensure itinerary belongs to the tour
        if ($itinerary->tour_id !== $tour->id) {
            return response()->json([
                'success' => false,
                'message' => 'Itinerary not found in this tour',
            ], 404);
        }

        try {
            $itinerary->delete();

            return response()->json([
                'success' => true,
                'message' => 'Itinerary deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete itinerary', [
                'tour_id' => $tour->id,
                'itinerary_id' => $itinerary->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete itinerary',
            ], 500);
        }
    }

    /**
     * Reorder itineraries.
     */
    public function reorder(Request $request, Tour $tour): JsonResponse
    {
        try {
            $validated = $request->validate([
                'itinerary_ids' => 'required|array',
                'itinerary_ids.*' => 'integer|exists:tour_itineraries,id',
            ]);

            foreach ($validated['itinerary_ids'] as $index => $id) {
                TourItinerary::where('id', $id)
                    ->where('tour_id', $tour->id)
                    ->update(['sort_order' => $index + 1]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Itineraries reordered successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reorder itineraries', [
                'tour_id' => $tour->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder itineraries',
            ], 500);
        }
    }
}
