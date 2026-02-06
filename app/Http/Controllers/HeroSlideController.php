<?php

namespace App\Http\Controllers;

use App\Models\HeroSlide;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class HeroSlideController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * Display a listing of hero slides.
     */
    public function index(Request $request): JsonResponse
    {
        $query = HeroSlide::query();

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
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('subtitle', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $slides = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $slides->items(),
            'meta' => [
                'current_page' => $slides->currentPage(),
                'last_page' => $slides->lastPage(),
                'per_page' => $slides->perPage(),
                'total' => $slides->total(),
            ],
        ]);
    }

    /**
     * Get public active slides for homepage
     */
    public function publicList(): JsonResponse
    {
        $slides = HeroSlide::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $slides,
        ]);
    }

    /**
     * Store a new hero slide.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp,bmp|max:10240', // 10MB max
            'alt' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'button_text' => 'nullable|string|max:100',
            'button_link' => 'nullable|string|max:500',
            'custom_filename' => 'nullable|string|max:200',
        ]);

        $file = $request->file('image');
        
        // Get original image info
        $imageInfo = getimagesize($file->getRealPath());
        $originalWidth = $imageInfo[0] ?? 0;
        $originalHeight = $imageInfo[1] ?? 0;

        // Generate custom ID
        $customId = 'hero-slide-' . uniqid() . '-' . time();

        // Prepare metadata
        $metadata = [
            'type' => 'hero-slide',
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
        $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');
        $thumbnailUrl = $this->cloudflare->getDisplayUrl($result['id'], 'public');

        // Get max sort order
        $maxSortOrder = HeroSlide::max('sort_order') ?? 0;

        // Create record
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = !empty($validated['custom_filename']) 
            ? $validated['custom_filename'] . '.webp'
            : $originalName . '.webp';
            
        $heroSlide = HeroSlide::create([
            'cloudflare_id' => $result['id'],
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'filename' => $filename,
            'alt' => $validated['alt'] ?? null,
            'title' => $validated['title'] ?? null,
            'subtitle' => $validated['subtitle'] ?? null,
            'button_text' => $validated['button_text'] ?? null,
            'button_link' => $validated['button_link'] ?? null,
            'width' => $originalWidth,
            'height' => $originalHeight,
            'file_size' => $file->getSize(),
            'is_active' => true,
            'sort_order' => $maxSortOrder + 1,
        ]);

        return response()->json([
            'success' => true,
            'data' => $heroSlide,
            'message' => 'อัพโหลด Hero Slide สำเร็จ',
        ], 201);
    }

    /**
     * Display the specified hero slide.
     */
    public function show(HeroSlide $heroSlide): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $heroSlide,
        ]);
    }

    /**
     * Update the specified hero slide.
     */
    public function update(Request $request, HeroSlide $heroSlide): JsonResponse
    {
        $validated = $request->validate([
            'alt' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'button_text' => 'nullable|string|max:100',
            'button_link' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $heroSlide->update($validated);

        return response()->json([
            'success' => true,
            'data' => $heroSlide,
            'message' => 'อัพเดท Hero Slide สำเร็จ',
        ]);
    }

    /**
     * Replace image of the specified hero slide.
     */
    public function replaceImage(Request $request, HeroSlide $heroSlide): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp,bmp|max:10240',
        ]);

        $file = $request->file('image');
        
        // Get original image info
        $imageInfo = getimagesize($file->getRealPath());
        $originalWidth = $imageInfo[0] ?? 0;
        $originalHeight = $imageInfo[1] ?? 0;

        // Delete old image from Cloudflare
        if ($heroSlide->cloudflare_id) {
            try {
                $this->cloudflare->delete($heroSlide->cloudflare_id);
            } catch (\Exception $e) {
                Log::warning("Failed to delete old hero slide image: {$heroSlide->cloudflare_id}", ['error' => $e->getMessage()]);
            }
        }

        // Generate new custom ID
        $customId = 'hero-slide-' . uniqid() . '-' . time();

        // Upload new image to Cloudflare
        $result = $this->cloudflare->uploadFromFile($file, $customId, ['type' => 'hero-slide']);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'อัพโหลดรูปใหม่ไป Cloudflare ไม่สำเร็จ',
            ], 500);
        }

        // Get URL
        $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');
        $thumbnailUrl = $this->cloudflare->getDisplayUrl($result['id'], 'public');

        // Update record
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        $heroSlide->update([
            'cloudflare_id' => $result['id'],
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'filename' => $originalName . '.webp',
            'width' => $originalWidth,
            'height' => $originalHeight,
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $heroSlide->fresh(),
            'message' => 'เปลี่ยนรูป Hero Slide สำเร็จ',
        ]);
    }

    /**
     * Toggle hero slide status.
     */
    public function toggleStatus(HeroSlide $heroSlide): JsonResponse
    {
        $heroSlide->update(['is_active' => !$heroSlide->is_active]);

        return response()->json([
            'success' => true,
            'data' => $heroSlide,
            'message' => $heroSlide->is_active ? 'เปิดใช้งาน Hero Slide แล้ว' : 'ปิดใช้งาน Hero Slide แล้ว',
        ]);
    }

    /**
     * Reorder hero slides.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'slides' => 'required|array',
            'slides.*.id' => 'required|integer|exists:hero_slides,id',
            'slides.*.sort_order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->slides as $item) {
                HeroSlide::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'จัดเรียง Hero Slides สำเร็จ',
        ]);
    }

    /**
     * Remove the specified hero slide.
     */
    public function destroy(HeroSlide $heroSlide): JsonResponse
    {
        // Delete from Cloudflare
        if ($heroSlide->cloudflare_id) {
            try {
                $this->cloudflare->delete($heroSlide->cloudflare_id);
            } catch (\Exception $e) {
                Log::warning("Failed to delete hero slide image: {$heroSlide->cloudflare_id}", ['error' => $e->getMessage()]);
            }
        }

        $heroSlide->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบ Hero Slide สำเร็จ',
        ]);
    }

    /**
     * Get statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => HeroSlide::count(),
            'active' => HeroSlide::where('is_active', true)->count(),
            'inactive' => HeroSlide::where('is_active', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
