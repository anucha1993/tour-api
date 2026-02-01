<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\TourItinerary;
use App\Services\CloudflareImagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TourItineraryController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

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
                'images.*' => 'string',
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
                'images.*' => 'string',
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

    /**
     * Upload image for itinerary (standalone - returns URL only).
     * Uploads to Cloudflare Images.
     */
    public function uploadImageOnly(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
                'tour_id' => 'nullable|integer',
            ]);

            $file = $request->file('image');
            $tourId = $request->input('tour_id', 0);
            
            // Generate custom ID for Cloudflare
            $customId = 'itinerary_' . $tourId . '_' . time() . '_' . uniqid();
            
            // Upload to Cloudflare Images
            $result = $this->cloudflare->uploadFromFile(
                $file,
                $customId,
                [
                    'type' => 'itinerary',
                    'tour_id' => (string)$tourId,
                    'uploaded_at' => now()->toIso8601String(),
                ]
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload image to Cloudflare',
                ], 500);
            }

            // Get the public URL from Cloudflare response
            $url = $result['variants'][0] ?? null;
            
            if (!$url) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get image URL from Cloudflare',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'url' => $url,
                    'cloudflare_id' => $result['id'] ?? null,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to upload itinerary image', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload image for itinerary.
     * Uploads to Cloudflare Images and updates itinerary.
     */
    public function uploadImage(Request $request, Tour $tour, TourItinerary $itinerary): JsonResponse
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
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            ]);

            $file = $request->file('image');
            
            // Generate custom ID for Cloudflare
            $customId = 'itinerary_' . $tour->id . '_' . $itinerary->id . '_' . time() . '_' . uniqid();
            
            // Upload to Cloudflare Images
            $result = $this->cloudflare->uploadFromFile(
                $file,
                $customId,
                [
                    'type' => 'itinerary',
                    'tour_id' => (string)$tour->id,
                    'itinerary_id' => (string)$itinerary->id,
                    'day_number' => (string)$itinerary->day_number,
                    'uploaded_at' => now()->toIso8601String(),
                ]
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload image to Cloudflare',
                ], 500);
            }

            // Get the public URL from Cloudflare response
            $url = $result['variants'][0] ?? null;
            
            if (!$url) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get image URL from Cloudflare',
                ], 500);
            }

            // Add to itinerary images array
            $images = $itinerary->images ?? [];
            $images[] = $url;
            $itinerary->update(['images' => $images]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'url' => $url,
                    'cloudflare_id' => $result['id'] ?? null,
                    'images' => $images,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to upload itinerary image', [
                'tour_id' => $tour->id,
                'itinerary_id' => $itinerary->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove image from itinerary and delete from Cloudflare.
     */
    public function removeImage(Request $request, Tour $tour, TourItinerary $itinerary): JsonResponse
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
                'url' => 'required|string',
            ]);

            $url = $validated['url'];

            // Delete from Cloudflare
            $this->deleteCloudflareImageByUrl($url);

            // Remove from itinerary images array
            $images = $itinerary->images ?? [];
            $images = array_values(array_filter($images, fn($img) => $img !== $url));
            $itinerary->update(['images' => $images]);

            return response()->json([
                'success' => true,
                'message' => 'Image removed successfully',
                'data' => [
                    'images' => $images,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove itinerary image', [
                'tour_id' => $tour->id,
                'itinerary_id' => $itinerary->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove image',
            ], 500);
        }
    }

    /**
     * Delete image from Cloudflare (standalone - for unsaved itineraries).
     */
    public function deleteImage(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'url' => 'required|string',
            ]);

            $url = $validated['url'];

            // Delete from Cloudflare
            $deleted = $this->deleteCloudflareImageByUrl($url);

            return response()->json([
                'success' => true,
                'message' => $deleted ? 'Image deleted successfully' : 'Image not found or already deleted',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete itinerary image', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
            ], 500);
        }
    }

    /**
     * Extract Cloudflare image ID from URL and delete it.
     */
    protected function deleteCloudflareImageByUrl(string $url): bool
    {
        Log::info('Attempting to delete Cloudflare image', ['url' => $url]);
        
        try {
            // Cloudflare URL format: https://imagedelivery.net/{account_hash}/{image_id}/{variant}
            if (preg_match('/imagedelivery\.net\/[^\/]+\/([^\/]+)/', $url, $matches)) {
                $imageId = $matches[1];
                Log::info('Extracted image ID', ['image_id' => $imageId]);
                
                $result = $this->cloudflare->delete($imageId);
                Log::info('Cloudflare delete result', ['image_id' => $imageId, 'result' => $result]);
                
                if (!$result) {
                    Log::warning('Failed to delete Cloudflare image', ['image_id' => $imageId, 'url' => $url]);
                    return false;
                }
                return true;
            }
            Log::warning('Could not extract Cloudflare image ID from URL', ['url' => $url]);
            return false;
        } catch (\Exception $e) {
            Log::error('Exception deleting Cloudflare image', ['url' => $url, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
