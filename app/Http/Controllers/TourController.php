<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\Country;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class TourController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * Display a listing of tours.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tour::with(['primaryCountry:id,iso2,name_en,name_th', 'countries:id,iso2,name_en,name_th', 'cities:id,name_en,name_th,country_id', 'wholesaler:id,name', 'transports.transport:id,code,name,type,image', 'periods:id,tour_id,start_date,end_date,status']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('tour_code', 'like', "%{$search}%")
                  ->orWhere('highlights', 'like', "%{$search}%")
                  ->orWhereJsonContains('hashtags', $search);
            });
        }

        // Filter by country (searches all countries through pivot)
        if ($request->filled('country_id')) {
            $countryId = $request->country_id;
            $query->whereHas('countries', fn($q) => $q->where('countries.id', $countryId));
        }

        // Filter by region
        if ($request->filled('region')) {
            $query->where('region', $request->region);
        }

        // Filter by tour type
        if ($request->filled('tour_type')) {
            $query->where('tour_type', $request->tour_type);
        }

        // Filter by theme
        if ($request->filled('theme')) {
            $query->whereJsonContains('themes', $request->theme);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by data_source (api / manual)
        if ($request->filled('data_source')) {
            $query->where('data_source', $request->data_source);
        }

        // Filter by promotion_type (fire_sale / normal / none)
        if ($request->filled('promotion_type')) {
            $query->where('promotion_type', $request->promotion_type);
        }

        // Filter by published
        if ($request->has('is_published')) {
            $query->where('is_published', filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN));
        }

        // Price range
        if ($request->filled('min_price')) {
            $query->where('display_price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('display_price', '<=', $request->max_price);
        }

        // Duration
        if ($request->filled('duration_days')) {
            $query->where('duration_days', $request->duration_days);
        }

        // Has promotion / discount
        if ($request->has('has_promotion')) {
            $query->where('has_promotion', filter_var($request->has_promotion, FILTER_VALIDATE_BOOLEAN));
        }

        // Hotel star rating
        if ($request->filled('hotel_star')) {
            $star = (int) $request->hotel_star;
            $query->where(function($q) use ($star) {
                $q->where('hotel_star_min', '<=', $star)
                  ->where('hotel_star_max', '>=', $star);
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        
        $allowedSorts = ['created_at', 'title', 'display_price', 'min_price', 'next_departure_date', 'popularity_score', 'duration_days'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = $request->get('per_page', 20);
        $tours = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tours->items(),
            'meta' => [
                'current_page' => $tours->currentPage(),
                'last_page' => $tours->lastPage(),
                'per_page' => $tours->perPage(),
                'total' => $tours->total(),
            ],
        ]);
    }

    /**
     * Get tour counts for sidebar menu
     */
    public function counts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total' => Tour::count(),
                'by_data_source' => [
                    'api' => Tour::where('data_source', 'api')->count(),
                    'manual' => Tour::where('data_source', 'manual')->count(),
                ],
                'by_status' => [
                    'active' => Tour::where('status', 'active')->count(),
                    'draft' => Tour::where('status', 'draft')->count(),
                    'inactive' => Tour::where('status', 'inactive')->count(),
                ],
                'by_promotion_type' => [
                    'fire_sale' => Tour::where('promotion_type', 'fire_sale')->count(),
                    'normal' => Tour::where('promotion_type', 'normal')->count(),
                    'none' => Tour::where('promotion_type', 'none')->count(),
                ],
            ],
        ]);
    }

    /**
     * Store a newly created tour.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wholesaler_id' => 'required|exists:wholesalers,id',
            'external_id' => 'nullable|string|max:50',
            'tour_code' => 'nullable|string|max:50|unique:tours,tour_code',
            'wholesaler_tour_code' => 'nullable|string|max:100',
            'title' => 'required|string|max:255',
            'tour_type' => 'nullable|in:join,incentive,collective',
            'country_ids' => 'required|array|min:1',
            'country_ids.*' => 'exists:countries,id',
            'city_ids' => 'nullable|array',
            'city_ids.*' => 'exists:cities,id',
            'region' => 'nullable|string|max:50',
            'sub_region' => 'nullable|string|max:50',
            'duration_days' => 'required|integer|min:1|max:30',
            'duration_nights' => 'required|integer|min:0|max:30',
            'highlights' => 'nullable|array',
            'highlights.*' => 'string|max:255',
            'shopping_highlights' => 'nullable|array',
            'shopping_highlights.*' => 'string|max:255',
            'food_highlights' => 'nullable|array',
            'food_highlights.*' => 'string|max:255',
            'special_highlights' => 'nullable|array',
            'special_highlights.*' => 'string|max:255',
            'hotel_star_min' => 'nullable|integer|min:1|max:5',
            'hotel_star_max' => 'nullable|integer|min:1|max:5',
            'inclusions' => 'nullable|string',
            'exclusions' => 'nullable|string',
            'conditions' => 'nullable|string',
            'slug' => 'nullable|string|max:255|unique:tours,slug',
            'meta_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:300',
            'keywords' => 'nullable|array',
            'hashtags' => 'nullable|array',
            'cover_image_url' => 'nullable|string|max:500',
            'cover_image_alt' => 'nullable|string|max:255',
            'pdf_url' => 'nullable|string|max:500',
            'themes' => 'nullable|array',
            'suitable_for' => 'nullable|array',
            'departure_airports' => 'nullable|array',
            'badge' => 'nullable|string|max:20',
            'tour_category' => 'nullable|in:budget,premium',
            'status' => 'nullable|in:draft,active,inactive',
        ]);

        // Generate tour code if not provided: NT + YYMMDD + XXXX
        if (empty($validated['tour_code'])) {
            $validated['tour_code'] = Tour::generateTourCode();
        }

        // Generate external_id if not provided
        if (empty($validated['external_id'])) {
            $validated['external_id'] = 'EXT-' . $validated['tour_code'];
        }

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
            $originalSlug = $validated['slug'];
            $count = 1;
            while (Tour::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $originalSlug . '-' . $count++;
            }
        }

        // Extract country_ids and set first as primary
        $countryIds = $validated['country_ids'] ?? [];
        unset($validated['country_ids']);
        
        // Extract city_ids
        $cityIds = $validated['city_ids'] ?? [];
        unset($validated['city_ids']);
        
        // First country is primary
        $validated['primary_country_id'] = $countryIds[0] ?? null;

        // Get region from primary country if not provided
        if (empty($validated['region']) && !empty($validated['primary_country_id'])) {
            $country = Country::find($validated['primary_country_id']);
            if ($country) {
                $validated['region'] = $country->region;
            }
        }

        $tour = Tour::create($validated);

        // Sync countries (first is primary)
        $countriesToSync = [];
        foreach ($countryIds as $idx => $cid) {
            $countriesToSync[$cid] = [
                'is_primary' => $idx === 0,
                'sort_order' => $idx,
            ];
        }
        if (!empty($countriesToSync)) {
            $tour->countries()->sync($countriesToSync);
        }

        // Sync cities
        if (!empty($cityIds)) {
            $citiesToSync = [];
            foreach ($cityIds as $idx => $cityId) {
                $city = \App\Models\City::find($cityId);
                if ($city) {
                    $citiesToSync[$cityId] = [
                        'country_id' => $city->country_id,
                        'sort_order' => $idx,
                    ];
                }
            }
            $tour->cities()->sync($citiesToSync);
        }

        $tour->load(['primaryCountry:id,iso2,name_en,name_th', 'countries:id,iso2,name_en,name_th', 'cities:id,name_en,name_th,country_id', 'wholesaler:id,name']);

        return response()->json([
            'success' => true,
            'data' => $tour,
            'message' => 'Tour created successfully',
        ], 201);
    }

    /**
     * Display the specified tour.
     */
    public function show(Tour $tour): JsonResponse
    {
        $tour->load([
            'primaryCountry:id,iso2,name_en,name_th',
            'countries:id,iso2,name_en,name_th',
            'cities:id,name_en,name_th,country_id',
            'wholesaler:id,name',
            'locations.city:id,name_en,name_th',
            'gallery',
            'transports.transport:id,code,name,type,image',
            'itineraries',
            'periods' => function ($query) {
                $query->where('start_date', '>=', now()->toDateString())
                      ->where('status', 'open')
                      ->orderBy('start_date')
                      ->with('offer.promotions');
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => $tour,
        ]);
    }

    /**
     * Update the specified tour.
     */
    public function update(Request $request, Tour $tour): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'wholesaler_tour_code' => 'nullable|string|max:100',
            'tour_type' => 'nullable|in:join,incentive,collective',
            'country_ids' => 'nullable|array|min:1',
            'country_ids.*' => 'exists:countries,id',
            'city_ids' => 'nullable|array',
            'city_ids.*' => 'exists:cities,id',
            'region' => 'nullable|string|max:50',
            'sub_region' => 'nullable|string|max:50',
            'duration_days' => 'sometimes|required|integer|min:1|max:30',
            'duration_nights' => 'sometimes|required|integer|min:0|max:30',
            'highlights' => 'nullable|array',
            'highlights.*' => 'string|max:255',
            'shopping_highlights' => 'nullable|array',
            'shopping_highlights.*' => 'string|max:255',
            'food_highlights' => 'nullable|array',
            'food_highlights.*' => 'string|max:255',
            'special_highlights' => 'nullable|array',
            'special_highlights.*' => 'string|max:255',
            'hotel_star' => 'nullable|integer|min:1|max:5',
            'hotel_star_min' => 'nullable|integer|min:1|max:5',
            'hotel_star_max' => 'nullable|integer|min:1|max:5',
            'inclusions' => 'nullable|string',
            'exclusions' => 'nullable|string',
            'conditions' => 'nullable|string',
            'description' => 'nullable|string',
            'slug' => 'nullable|string|max:255|unique:tours,slug,' . $tour->id,
            'meta_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:300',
            'keywords' => 'nullable|array',
            'hashtags' => 'nullable|array',
            'cover_image_url' => 'nullable|string|max:500',
            'cover_image_alt' => 'nullable|string|max:255',
            'og_image_url' => 'nullable|string|max:500',
            'pdf_url' => 'nullable|string|max:500',
            'docx_url' => 'nullable|string|max:500',
            'themes' => 'nullable|array',
            'suitable_for' => 'nullable|array',
            'departure_airports' => 'nullable|array',
            'badge' => 'nullable|string|max:20',
            'tour_category' => 'nullable|in:budget,premium',
            'transport_id' => 'nullable|integer|exists:transports,id',
            'price_adult' => 'nullable|numeric|min:0',
            'discount_adult' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|in:draft,active,inactive',
            'promotion_type' => 'nullable|in:none,normal,fire_sale',
            'sync_locked' => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
        ]);

        // Handle publish
        if (isset($validated['is_published']) && $validated['is_published'] && !$tour->is_published) {
            $validated['published_at'] = now();
        }

        // Extract country_ids before updating
        $countryIds = null;
        if (array_key_exists('country_ids', $validated)) {
            $countryIds = $validated['country_ids'];
            unset($validated['country_ids']);
            
            // First country is primary
            if (!empty($countryIds)) {
                $validated['primary_country_id'] = $countryIds[0];
            }
        }

        // Extract city_ids before updating
        $cityIds = null;
        if (array_key_exists('city_ids', $validated)) {
            $cityIds = $validated['city_ids'];
            unset($validated['city_ids']);
        }

        $tour->update($validated);

        // Sync countries if provided
        if ($countryIds !== null && !empty($countryIds)) {
            $countriesToSync = [];
            foreach ($countryIds as $idx => $cid) {
                $countriesToSync[$cid] = [
                    'is_primary' => $idx === 0,
                    'sort_order' => $idx,
                ];
            }
            $tour->countries()->sync($countriesToSync);
        }

        // Sync cities if provided
        if ($cityIds !== null) {
            if (!empty($cityIds)) {
                $citiesToSync = [];
                foreach ($cityIds as $idx => $cityId) {
                    $city = \App\Models\City::find($cityId);
                    if ($city) {
                        $citiesToSync[$cityId] = [
                            'country_id' => $city->country_id,
                            'sort_order' => $idx,
                        ];
                    }
                }
                $tour->cities()->sync($citiesToSync);
            } else {
                $tour->cities()->detach();
            }
        }

        $tour->load(['primaryCountry:id,iso2,name_en,name_th', 'countries:id,iso2,name_en,name_th', 'cities:id,name_en,name_th,country_id', 'wholesaler:id,name']);

        return response()->json([
            'success' => true,
            'data' => $tour,
            'message' => 'Tour updated successfully',
        ]);
    }

    /**
     * Remove the specified tour.
     */
    public function destroy(Tour $tour): JsonResponse
    {
        // Delete PDF file from R2 if exists
        if ($tour->pdf_url && str_contains($tour->pdf_url, 'r2.dev')) {
            $this->deleteR2File($tour->pdf_url, 'pdf', $tour->id);
        }

        // Delete cover image from Cloudflare if exists
        if ($tour->cover_image_url && str_contains($tour->cover_image_url, 'imagedelivery.net')) {
            $this->deleteCloudflareImage($tour->cover_image_url, 'cover', $tour->id);
        }

        // Delete gallery images from Cloudflare
        foreach ($tour->gallery as $galleryItem) {
            if ($galleryItem->url && str_contains($galleryItem->url, 'imagedelivery.net')) {
                $this->deleteCloudflareImage($galleryItem->url, 'gallery', $galleryItem->id);
            }
        }

        $tour->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tour deleted successfully',
        ]);
    }

    /**
     * Delete file from R2 storage by URL
     */
    protected function deleteR2File(string $url, string $type, int $id): void
    {
        try {
            // Extract path from R2 URL
            // Format: https://pub-xxx.r2.dev/pdfs/zg/2026/01/xxx.pdf
            $r2Url = env('R2_URL');
            if ($r2Url && str_starts_with($url, $r2Url)) {
                $path = str_replace(rtrim($r2Url, '/') . '/', '', $url);
                Storage::disk('r2')->delete($path);
                Log::info("Deleted R2 {$type} file", ['id' => $id, 'path' => $path]);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to delete R2 {$type} file", [
                'id' => $id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete image from Cloudflare by URL
     */
    protected function deleteCloudflareImage(string $url, string $type, int $id): void
    {
        // Extract image ID from Cloudflare URL
        // Format: https://imagedelivery.net/{account_hash}/{image_id}/{variant}
        $parts = explode('/', $url);
        if (count($parts) >= 2) {
            $imageId = $parts[count($parts) - 2]; // Get the image ID (second to last)
            try {
                $this->cloudflare->delete($imageId);
            } catch (\Exception $e) {
                Log::warning("Failed to delete Cloudflare {$type} image", [
                    'id' => $id,
                    'image_id' => $imageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Toggle tour status.
     */
    public function toggleStatus(Tour $tour): JsonResponse
    {
        $newStatus = match($tour->status) {
            'active' => 'inactive',
            'inactive' => 'active',
            'draft' => 'active',
        };

        $tour->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'data' => $tour,
            'message' => "Tour status changed to {$newStatus}",
        ]);
    }

    /**
     * Toggle tour publish status.
     */
    public function togglePublish(Tour $tour): JsonResponse
    {
        $tour->update([
            'is_published' => !$tour->is_published,
            'published_at' => !$tour->is_published ? now() : $tour->published_at,
        ]);

        return response()->json([
            'success' => true,
            'data' => $tour,
            'message' => $tour->is_published ? 'Tour published' : 'Tour unpublished',
        ]);
    }

    /**
     * Debug - Get raw tour data for debugging.
     */
    public function debug(Tour $tour): JsonResponse
    {
        // Load all relationships
        $tour->load([
            'primaryCountry',
            'countries',
            'cities',
            'wholesaler',
            'locations.city',
            'gallery',
            'transports.transport',
            'itineraries',
            'periods.offer.promotions',
        ]);

        // Get raw database attributes
        $rawAttributes = $tour->getRawOriginal();

        // Get model attributes (with casts applied)
        $modelAttributes = $tour->getAttributes();

        // Build payload structure that frontend should send for update
        $expectedPayload = [
            'title' => $tour->title,
            'wholesaler_tour_code' => $tour->wholesaler_tour_code,
            'tour_type' => $tour->tour_type,
            'country_ids' => $tour->countries->pluck('id')->toArray(),
            'city_ids' => $tour->cities->pluck('id')->toArray(),
            'region' => $tour->region,
            'sub_region' => $tour->sub_region,
            'duration_days' => $tour->duration_days,
            'duration_nights' => $tour->duration_nights,
            'highlights' => $tour->highlights,
            'shopping_highlights' => $tour->shopping_highlights,
            'food_highlights' => $tour->food_highlights,
            'special_highlights' => $tour->special_highlights,
            'hotel_star' => $tour->hotel_star,
            'hotel_star_min' => $tour->hotel_star_min,
            'hotel_star_max' => $tour->hotel_star_max,
            'inclusions' => $tour->inclusions,
            'exclusions' => $tour->exclusions,
            'conditions' => $tour->conditions,
            'description' => $tour->description,
            'slug' => $tour->slug,
            'meta_title' => $tour->meta_title,
            'meta_description' => $tour->meta_description,
            'keywords' => $tour->keywords,
            'hashtags' => $tour->hashtags,
            'cover_image_url' => $tour->cover_image_url,
            'cover_image_alt' => $tour->cover_image_alt,
            'og_image_url' => $tour->og_image_url,
            'pdf_url' => $tour->pdf_url,
            'docx_url' => $tour->docx_url,
            'themes' => $tour->themes,
            'suitable_for' => $tour->suitable_for,
            'departure_airports' => $tour->departure_airports,
            'badge' => $tour->badge,
            'tour_category' => $tour->tour_category,
            'transport_id' => $tour->transports->first()?->transport_id,
            'price_adult' => $tour->display_price,
            'discount_adult' => $tour->discount_amount,
            'status' => $tour->status,
            'is_published' => $tour->is_published,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'tour_id' => $tour->id,
                'tour_code' => $tour->tour_code,
                'api_response' => $tour, // What show() returns
                'raw_database' => $rawAttributes, // Raw DB values
                'expected_payload' => $expectedPayload, // What frontend should send
                'relationships' => [
                    'countries' => $tour->countries,
                    'cities' => $tour->cities,
                    'transports' => $tour->transports,
                    'periods_count' => $tour->periods->count(),
                    'periods' => $tour->periods->take(5), // First 5 periods
                ],
                'timestamps' => [
                    'created_at' => $tour->created_at?->toISOString(),
                    'updated_at' => $tour->updated_at?->toISOString(),
                    'published_at' => $tour->published_at?->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Get tour statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Tour::count(),
            'active' => Tour::where('status', 'active')->count(),
            'published' => Tour::where('is_published', true)->count(),
            'with_promotion' => Tour::where('has_promotion', true)->count(),
            'by_region' => Tour::selectRaw('region, COUNT(*) as count')
                ->whereNotNull('region')
                ->groupBy('region')
                ->pluck('count', 'region'),
            'by_status' => Tour::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get regions list.
     */
    public function regions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Tour::REGIONS,
        ]);
    }

    /**
     * Get themes list.
     */
    public function themes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Tour::THEMES,
        ]);
    }

    /**
     * Get tour types list.
     */
    public function tourTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Tour::TOUR_TYPES,
        ]);
    }

    /**
     * Get suitable for list.
     */
    public function suitableFor(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Tour::SUITABLE_FOR,
        ]);
    }

    /**
     * Recalculate tour aggregates.
     */
    public function recalculate(Tour $tour): JsonResponse
    {
        $tour->recalculateAggregates();
        $tour->refresh();

        return response()->json([
            'success' => true,
            'data' => $tour,
            'message' => 'Tour aggregates recalculated',
        ]);
    }

    /**
     * Upload cover image for tour.
     * Resize to 600x600 and convert to WebP format.
     */
    public function uploadCoverImage(Request $request, Tour $tour): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'], // 10MB max (before processing)
            'custom_name' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
        ], [
            'image.required' => 'กรุณาเลือกรูปภาพ',
            'image.image' => 'ไฟล์ต้องเป็นรูปภาพ',
            'image.max' => 'ขนาดไฟล์ต้องไม่เกิน 10MB',
        ]);

        // Delete old image from Cloudflare if exists
        $oldImageUrl = $tour->cover_image_url;
        $oldImageId = null;
        if ($oldImageUrl && str_contains($oldImageUrl, 'imagedelivery.net')) {
            // Extract image ID from URL: https://imagedelivery.net/{hash}/{imageId}/{variant}
            $parts = explode('/', $oldImageUrl);
            if (count($parts) >= 5) {
                $oldImageId = $parts[count($parts) - 2]; // Get the image ID
            }
        }

        $file = $request->file('image');
        
        // Process image: resize to 600x600 and convert to WebP
        $image = Image::read($file->getRealPath());
        
        // Cover fit - resize and crop to exact 600x600
        $image->cover(600, 600);
        
        // Convert to WebP with quality 85
        $webpData = $image->toWebp(85)->toString();
        
        // Create temp file for upload
        $tempPath = sys_get_temp_dir() . '/' . uniqid('tour_cover_') . '.webp';
        file_put_contents($tempPath, $webpData);
        
        // สร้างชื่อไฟล์จาก custom_name หรือ tour_code เช่น NT202601003-FUKUOKA KUMAMOTO.webp
        $customName = $request->input('custom_name');
        $altText = $request->input('alt');
        
        if ($customName) {
            // ทำความสะอาดชื่อไฟล์ - เอาเฉพาะตัวอักษร ตัวเลข ภาษาไทย และ - _ เว้นวรรค
            $cleanName = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $customName);
            $cleanName = preg_replace('/\s+/', ' ', $cleanName); // ลด spaces ซ้ำ
            $cleanName = trim($cleanName);
            $customId = "tours/{$cleanName}";
        } else {
            $customId = "tours/{$tour->tour_code}-cover-" . time();
        }

        // Upload to Cloudflare (uploadFromFile accepts path string)
        $result = $this->cloudflare->uploadFromFile(
            $tempPath,
            $customId,
            [
                'folder' => 'tours',
                'type' => 'cover',
                'tour_id' => $tour->id,
                'format' => 'webp',
                'size' => '600x600',
            ]
        );
        
        // Clean up temp file
        @unlink($tempPath);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'อัปโหลดรูปภาพล้มเหลว',
            ], 500);
        }

        // Delete old image from Cloudflare after successful upload
        if ($oldImageId) {
            $this->cloudflare->delete($oldImageId);
        }

        // Update tour cover image URL and alt
        $tour->cover_image_url = $this->cloudflare->getDisplayUrl($result['id']);
        $tour->cover_image_alt = $altText ?: ($customName ?: $tour->tour_code);
        $tour->save();

        return response()->json([
            'success' => true,
            'message' => 'อัปโหลดรูปภาพสำเร็จ (600x600 WebP)',
            'data' => [
                'cover_image_url' => $tour->cover_image_url,
                'cover_image_alt' => $tour->cover_image_alt,
            ],
        ]);
    }

    /**
     * Upload PDF for tour.
     */
    public function uploadPdf(Request $request, Tour $tour): JsonResponse
    {
        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'], // 10MB max
        ], [
            'pdf.required' => 'กรุณาเลือกไฟล์ PDF',
            'pdf.mimes' => 'ไฟล์ต้องเป็น PDF เท่านั้น',
            'pdf.max' => 'ขนาดไฟล์ต้องไม่เกิน 10MB',
        ]);

        $file = $request->file('pdf');
        $filename = Str::slug($tour->tour_code) . '-' . time() . '.pdf';
        $path = "tours/pdf/{$filename}";

        // Upload to R2
        try {
            Storage::disk('r2')->put($path, file_get_contents($file->getRealPath()), [
                'visibility' => 'public',
                'ContentType' => 'application/pdf',
            ]);

            // Get the public URL
            $pdfUrl = config('filesystems.disks.r2.url') . '/' . $path;

            // Update tour PDF URL
            $tour->pdf_url = $pdfUrl;
            $tour->save();

            return response()->json([
                'success' => true,
                'message' => 'อัปโหลด PDF สำเร็จ',
                'data' => [
                    'pdf_url' => $tour->pdf_url,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'อัปโหลด PDF ล้มเหลว: ' . $e->getMessage(),
            ], 500);
        }
    }
}
