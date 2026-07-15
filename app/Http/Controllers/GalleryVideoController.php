<?php

namespace App\Http\Controllers;

use App\Models\GalleryVideo;
use App\Models\Country;
use App\Models\City;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GalleryVideoController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * List videos with filters & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GalleryVideo::with(['country:id,iso2,name_en,name_th', 'city:id,name_en,name_th']);

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }
        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }
        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->tag);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('video_url', 'like', "%{$search}%")
                  ->orWhereJsonContains('tags', $search);
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->get('per_page', 20), 100);
        $videos = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $videos->items(),
            'meta' => [
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
                'per_page' => $videos->perPage(),
                'total' => $videos->total(),
            ],
        ]);
    }

    /**
     * Store a new video (URL + optional thumbnail upload).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_url' => 'required|url|max:500',
            'orientation' => 'nullable|in:landscape,portrait',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'thumbnail' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,bmp|max:10240',
            'country_id' => 'nullable|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $thumbnailUrl = null;
        $thumbnailCloudflareId = null;

        // Upload thumbnail to Cloudflare if provided
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $customId = 'video-thumb-' . uniqid() . '-' . time();
            $metadata = ['type' => 'video_thumbnail'];

            $result = $this->cloudflare->uploadFromFile($file, $customId, $metadata);
            if ($result) {
                $thumbnailCloudflareId = $result['id'];
                $thumbnailUrl = $this->cloudflare->getDisplayUrl($result['id'], 'public');
            }
        }

        $video = GalleryVideo::create([
            'video_url' => $validated['video_url'],
            'orientation' => $validated['orientation'] ?? GalleryVideo::detectOrientationFromUrl($validated['video_url']),
            'thumbnail_cloudflare_id' => $thumbnailCloudflareId,
            'thumbnail_url' => $thumbnailUrl,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'country_id' => $validated['country_id'] ?? null,
            'city_id' => $validated['city_id'] ?? null,
            'tags' => $validated['tags'] ?? [],
            'is_active' => true,
        ]);

        $video->load(['country:id,iso2,name_en,name_th', 'city:id,name_en,name_th']);

        return response()->json([
            'success' => true,
            'data' => $video,
            'message' => 'เพิ่มวิดีโอสำเร็จ',
        ], 201);
    }

    /**
     * Show single video.
     */
    public function show(GalleryVideo $galleryVideo): JsonResponse
    {
        $galleryVideo->load(['country:id,iso2,name_en,name_th', 'city:id,name_en,name_th']);

        return response()->json([
            'success' => true,
            'data' => $galleryVideo,
        ]);
    }

    /**
     * Update video metadata (and optionally replace thumbnail).
     */
    public function update(Request $request, GalleryVideo $galleryVideo): JsonResponse
    {
        $validated = $request->validate([
            'video_url' => 'nullable|url|max:500',
            'orientation' => 'nullable|in:landscape,portrait',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'thumbnail' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,bmp|max:10240',
            'country_id' => 'nullable|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Replace thumbnail if new one uploaded
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail from Cloudflare
            if ($galleryVideo->thumbnail_cloudflare_id) {
                $this->cloudflare->delete($galleryVideo->thumbnail_cloudflare_id);
            }
            $file = $request->file('thumbnail');
            $customId = 'video-thumb-' . uniqid() . '-' . time();
            $metadata = ['type' => 'video_thumbnail'];

            $result = $this->cloudflare->uploadFromFile($file, $customId, $metadata);
            if ($result) {
                $validated['thumbnail_cloudflare_id'] = $result['id'];
                $validated['thumbnail_url'] = $this->cloudflare->getDisplayUrl($result['id'], 'public');
            }
        }

        // Remove 'thumbnail' key from validated since it's a file, not a DB column
        unset($validated['thumbnail']);

        $galleryVideo->update($validated);
        $galleryVideo->load(['country:id,iso2,name_en,name_th', 'city:id,name_en,name_th']);

        return response()->json([
            'success' => true,
            'data' => $galleryVideo,
            'message' => 'อัพเดทวิดีโอสำเร็จ',
        ]);
    }

    /**
     * Delete a video and its thumbnail.
     */
    public function destroy(GalleryVideo $galleryVideo): JsonResponse
    {
        // Delete thumbnail from Cloudflare
        if ($galleryVideo->thumbnail_cloudflare_id) {
            $deleted = $this->cloudflare->delete($galleryVideo->thumbnail_cloudflare_id);
            if (!$deleted) {
                Log::warning("Failed to delete video thumbnail from Cloudflare: {$galleryVideo->thumbnail_cloudflare_id}");
            }
        }

        $galleryVideo->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบวิดีโอสำเร็จ',
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(GalleryVideo $galleryVideo): JsonResponse
    {
        $galleryVideo->update(['is_active' => !$galleryVideo->is_active]);

        return response()->json([
            'success' => true,
            'data' => $galleryVideo,
            'message' => $galleryVideo->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว',
        ]);
    }

    /**
     * Get all unique tags.
     */
    public function tags(): JsonResponse
    {
        $tags = GalleryVideo::active()
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
        $total = GalleryVideo::count();
        $active = GalleryVideo::active()->count();
        $byCountry = GalleryVideo::active()
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

    /**
     * Replace thumbnail image.
     */
    public function replaceThumbnail(Request $request, GalleryVideo $galleryVideo): JsonResponse
    {
        $request->validate([
            'thumbnail' => 'required|file|mimes:jpeg,jpg,png,gif,webp,bmp|max:10240',
        ]);

        // Delete old thumbnail
        if ($galleryVideo->thumbnail_cloudflare_id) {
            $this->cloudflare->delete($galleryVideo->thumbnail_cloudflare_id);
        }

        $file = $request->file('thumbnail');
        $customId = 'video-thumb-' . uniqid() . '-' . time();
        $metadata = ['type' => 'video_thumbnail'];

        $result = $this->cloudflare->uploadFromFile($file, $customId, $metadata);
        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'อัพโหลดภาพปกไม่สำเร็จ',
            ], 500);
        }

        $galleryVideo->update([
            'thumbnail_cloudflare_id' => $result['id'],
            'thumbnail_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
        ]);

        $galleryVideo->load(['country:id,iso2,name_en,name_th', 'city:id,name_en,name_th']);

        return response()->json([
            'success' => true,
            'data' => $galleryVideo,
            'message' => 'เปลี่ยนภาพปกสำเร็จ',
        ]);
    }
}
