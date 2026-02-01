<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\Country;
use App\Models\City;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GalleryImageController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * Display a listing of gallery images.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GalleryImage::with(['country:id,iso2,name_en,name_th', 'city:id,name_en,name_th']);

        // Filter by country
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        // Filter by city
        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        // Filter by tags
        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->tag);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('filename', 'like', "%{$search}%")
                  ->orWhere('alt', 'like', "%{$search}%")
                  ->orWhere('caption', 'like', "%{$search}%")
                  ->orWhereJsonContains('tags', $search);
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $images = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $images->items(),
            'meta' => [
                'current_page' => $images->currentPage(),
                'last_page' => $images->lastPage(),
                'per_page' => $images->perPage(),
                'total' => $images->total(),
            ],
        ]);
    }

    /**
     * Store a new gallery image.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp,bmp|max:10240', // 10MB max before conversion
            'alt' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'country_id' => 'nullable|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'custom_filename' => 'nullable|string|max:200',
        ]);

        $file = $request->file('image');
        
        // Get original image info
        $imageInfo = getimagesize($file->getRealPath());
        $originalWidth = $imageInfo[0] ?? 0;
        $originalHeight = $imageInfo[1] ?? 0;

        // Generate custom ID
        $customId = 'gallery-' . uniqid() . '-' . time();

        // Prepare metadata
        $metadata = [
            'type' => 'gallery',
            'country_id' => $validated['country_id'] ?? null,
            'city_id' => $validated['city_id'] ?? null,
        ];

        // Upload to Cloudflare (will auto-convert to WebP)
        $result = $this->cloudflare->uploadFromFile($file, $customId, $metadata);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'อัพโหลดรูปไป Cloudflare ไม่สำเร็จ',
            ], 500);
        }

        // Get URL
        // Get URL - use 'public' for both (flexible variants not enabled in Cloudflare)
        $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');
        $thumbnailUrl = $this->cloudflare->getDisplayUrl($result['id'], 'public');

        // Create record
        // Use custom filename if provided, otherwise use original filename with .webp extension
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = !empty($validated['custom_filename']) 
            ? $validated['custom_filename'] . '.webp'
            : $originalName . '.webp';
            
        $galleryImage = GalleryImage::create([
            'cloudflare_id' => $result['id'],
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'filename' => $filename,
            'alt' => $validated['alt'] ?? null,
            'caption' => $validated['caption'] ?? null,
            'country_id' => $validated['country_id'] ?? null,
            'city_id' => $validated['city_id'] ?? null,
            'tags' => $validated['tags'] ?? [],
            'width' => $originalWidth,
            'height' => $originalHeight,
            'file_size' => $file->getSize(),
            'is_active' => true,
        ]);

        $galleryImage->load(['country:id,iso2,name_en,name_th', 'city:id,name_en,name_th']);

        return response()->json([
            'success' => true,
            'data' => $galleryImage,
            'message' => 'อัพโหลดรูปสำเร็จ (แปลงเป็น WebP แล้ว)',
        ], 201);
    }

    /**
     * Display the specified gallery image.
     */
    public function show(GalleryImage $gallery): JsonResponse
    {
        $gallery->load(['country:id,iso2,name_en,name_th', 'city:id,name_en,name_th']);

        return response()->json([
            'success' => true,
            'data' => $gallery,
        ]);
    }

    /**
     * Update the specified gallery image.
     */
    public function update(Request $request, GalleryImage $gallery): JsonResponse
    {
        $validated = $request->validate([
            'alt' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'country_id' => 'nullable|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $gallery->update($validated);
        $gallery->load(['country:id,iso2,name_en,name_th', 'city:id,name_en,name_th']);

        return response()->json([
            'success' => true,
            'data' => $gallery,
            'message' => 'อัพเดทรูปสำเร็จ',
        ]);
    }

    /**
     * Remove the specified gallery image.
     */
    public function destroy(GalleryImage $gallery): JsonResponse
    {
        // Delete from Cloudflare
        if ($gallery->cloudflare_id) {
            $deleted = $this->cloudflare->delete($gallery->cloudflare_id);
            if (!$deleted) {
                Log::warning("Failed to delete image from Cloudflare: {$gallery->cloudflare_id}");
            }
        }

        $gallery->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบรูปสำเร็จ',
        ]);
    }

    /**
     * Toggle image active status.
     */
    public function toggleStatus(GalleryImage $gallery): JsonResponse
    {
        $gallery->update(['is_active' => !$gallery->is_active]);

        return response()->json([
            'success' => true,
            'data' => $gallery,
            'message' => $gallery->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว',
        ]);
    }

    /**
     * Bulk upload images.
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        $request->validate([
            'images' => 'required|array|min:1|max:20',
            'images.*' => 'file|mimes:jpeg,jpg,png,gif,webp,bmp|max:10240',
            'country_id' => 'nullable|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
            'tags' => 'nullable|array',
        ]);

        $uploaded = [];
        $failed = [];
        
        // Get country and city names for filename generation
        $country = $request->country_id ? Country::find($request->country_id) : null;
        $city = $request->city_id ? City::find($request->city_id) : null;
        $tags = $request->tags ?? [];
        $baseTimestamp = substr(time(), -6);

        foreach ($request->file('images') as $index => $file) {
            try {
                $customId = 'gallery-' . uniqid() . '-' . time() . '-' . $index;
                
                $metadata = [
                    'type' => 'gallery',
                    'country_id' => $request->country_id,
                    'city_id' => $request->city_id,
                ];

                $result = $this->cloudflare->uploadFromFile($file, $customId, $metadata);

                if ($result) {
                    $imageInfo = getimagesize($file->getRealPath());
                    $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');
                    $thumbnailUrl = $this->cloudflare->getDisplayUrl($result['id'], 'public');

                    // Generate SEO-friendly filename: country-city-tag-timestamp-index.webp
                    $filenameParts = [];
                    if ($country) {
                        $filenameParts[] = strtolower(str_replace(' ', '-', $country->name_en));
                    }
                    if ($city) {
                        $filenameParts[] = strtolower(str_replace(' ', '-', $city->name_en ?? ''));
                    }
                    if (!empty($tags) && isset($tags[0])) {
                        $filenameParts[] = strtolower(str_replace(' ', '-', $tags[0]));
                    }
                    $filenameParts[] = $baseTimestamp . '-' . $index;
                    $filename = implode('-', array_filter($filenameParts)) . '.webp';
                    
                    // Generate alt text: Tag เมือง ประเทศ (Thai)
                    $altParts = [];
                    if (!empty($tags) && isset($tags[0])) {
                        $altParts[] = $tags[0];
                    }
                    if ($city) {
                        $altParts[] = $city->name_th ?? $city->name_en;
                    }
                    if ($country) {
                        $altParts[] = $country->name_th ?? $country->name_en;
                    }
                    $alt = implode(' ', array_filter($altParts));
                    
                    $galleryImage = GalleryImage::create([
                        'cloudflare_id' => $result['id'],
                        'url' => $url,
                        'thumbnail_url' => $thumbnailUrl,
                        'filename' => $filename,
                        'alt' => $alt ?: null,
                        'country_id' => $request->country_id,
                        'city_id' => $request->city_id,
                        'tags' => $request->tags ?? [],
                        'width' => $imageInfo[0] ?? 1200,
                        'height' => $imageInfo[1] ?? 800,
                        'file_size' => $file->getSize(),
                        'is_active' => true,
                    ]);

                    $uploaded[] = $galleryImage;
                } else {
                    $failed[] = $file->getClientOriginalName();
                }
            } catch (\Exception $e) {
                Log::error("Bulk upload failed for file: " . $file->getClientOriginalName(), [
                    'error' => $e->getMessage(),
                ]);
                $failed[] = $file->getClientOriginalName();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'uploaded' => $uploaded,
                'failed' => $failed,
            ],
            'message' => count($uploaded) . ' รูปอัพโหลดสำเร็จ' . (count($failed) > 0 ? ', ' . count($failed) . ' รูปล้มเหลว' : ''),
        ]);
    }

    /**
     * Get images matching tour criteria (for frontend).
     */
    public function getForTour(Request $request): JsonResponse
    {
        $request->validate([
            'city_ids' => 'nullable|array',
            'city_ids.*' => 'integer',
            'country_ids' => 'nullable|array',
            'country_ids.*' => 'integer',
            'hashtags' => 'nullable|array',
            'hashtags.*' => 'string',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $cityIds = $request->input('city_ids', []);
        $countryIds = $request->input('country_ids', []);
        $hashtags = $request->input('hashtags', []);
        $limit = $request->input('limit', 6);

        $images = GalleryImage::getForTour($cityIds, $countryIds, $hashtags, $limit);

        return response()->json([
            'success' => true,
            'data' => $images->map(fn($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'thumbnail_url' => $img->thumbnail_url,
                'alt' => $img->alt,
                'caption' => $img->caption,
            ]),
        ]);
    }

    /**
     * Get all unique tags.
     */
    public function tags(): JsonResponse
    {
        $tags = GalleryImage::active()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    /**
     * Get statistics.
     */
    public function statistics(): JsonResponse
    {
        $total = GalleryImage::count();
        $active = GalleryImage::active()->count();
        $byCountry = GalleryImage::active()
            ->selectRaw('country_id, COUNT(*) as count')
            ->groupBy('country_id')
            ->with('country:id,name_th')
            ->get()
            ->map(fn($item) => [
                'country' => $item->country?->name_th ?? 'ไม่ระบุ',
                'count' => $item->count,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $total - $active,
                'by_country' => $byCountry,
            ],
        ]);
    }
}
