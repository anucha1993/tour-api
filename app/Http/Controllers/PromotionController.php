<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Services\CloudflareImagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PromotionController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * List all promotions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::query();

        // Filter by active status (default: show all)
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
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
            'link_url' => 'nullable|string|max:500',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => 'nullable|string|max:20',
        ]);

        $promotion = Promotion::create($validated);

        return response()->json([
            'success' => true,
            'data' => $promotion,
            'message' => 'สร้างโปรโมชั่นสำเร็จ',
        ], 201);
    }

    /**
     * Show a promotion.
     */
    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $promotion,
        ]);
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
            'link_url' => 'nullable|string|max:500',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => 'nullable|string|max:20',
        ]);

        $promotion->update($validated);

        return response()->json([
            'success' => true,
            'data' => $promotion,
            'message' => 'อัปเดตโปรโมชั่นสำเร็จ',
        ]);
    }

    /**
     * Delete a promotion.
     */
    public function destroy(Promotion $promotion): JsonResponse
    {
        // Delete banner from Cloudflare if exists
        if ($promotion->cloudflare_id) {
            try {
                $this->cloudflare->delete($promotion->cloudflare_id);
            } catch (\Exception $e) {
                Log::warning('Failed to delete promotion banner from Cloudflare', [
                    'cloudflare_id' => $promotion->cloudflare_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $promotion->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบโปรโมชั่นสำเร็จ',
        ]);
    }

    /**
     * Upload banner image for promotion.
     */
    public function uploadBanner(Request $request, Promotion $promotion): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max
        ]);

        try {
            $file = $request->file('image');
            
            // Delete old image if exists
            if ($promotion->cloudflare_id) {
                try {
                    $this->cloudflare->delete($promotion->cloudflare_id);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old promotion banner', [
                        'cloudflare_id' => $promotion->cloudflare_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Upload to Cloudflare with unique ID
            $uniqueId = "promotions/{$promotion->id}-" . time();
            $result = $this->cloudflare->uploadFromFile($file, $uniqueId);
            
            if (!$result || !isset($result['id'])) {
                throw new \Exception('Cloudflare upload failed');
            }

            // Generate URL
            $bannerUrl = $this->cloudflare->getDisplayUrl($result['id']);

            // Update promotion
            $promotion->update([
                'cloudflare_id' => $result['id'],
                'banner_url' => $bannerUrl,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'อัปโหลดรูปภาพสำเร็จ',
                'banner_url' => $bannerUrl,
                'cloudflare_id' => $result['id'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload promotion banner', [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถอัปโหลดรูปภาพได้: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete banner image.
     */
    public function deleteBanner(Promotion $promotion): JsonResponse
    {
        if (!$promotion->cloudflare_id) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบรูปภาพ',
            ], 404);
        }

        try {
            $this->cloudflare->delete($promotion->cloudflare_id);
        } catch (\Exception $e) {
            Log::warning('Failed to delete promotion banner from Cloudflare', [
                'cloudflare_id' => $promotion->cloudflare_id,
                'error' => $e->getMessage(),
            ]);
        }

        $promotion->update([
            'cloudflare_id' => null,
            'banner_url' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ลบรูปภาพสำเร็จ',
        ]);
    }

    /**
     * Toggle promotion status.
     */
    public function toggleStatus(Promotion $promotion): JsonResponse
    {
        $promotion->update([
            'is_active' => !$promotion->is_active,
        ]);

        return response()->json([
            'success' => true,
            'data' => $promotion,
            'message' => $promotion->is_active ? 'เปิดใช้งานโปรโมชั่นแล้ว' : 'ปิดใช้งานโปรโมชั่นแล้ว',
        ]);
    }

    /**
     * Reorder promotions.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:promotions,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            Promotion::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'จัดเรียงลำดับสำเร็จ',
        ]);
    }

    /**
     * Public list of active promotions for website display.
     */
    public function publicList(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 10);
        $type = $request->get('type');

        $query = Promotion::active()
            ->ordered()
            ->whereNotNull('banner_url');

        // Filter by date range if start/end dates are set
        $query->where(function ($q) {
            $q->whereNull('start_date')
              ->orWhere('start_date', '<=', now());
        })->where(function ($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now());
        });

        // Filter by type if provided
        if ($type) {
            $query->where('type', $type);
        }

        $promotions = $query->take($limit)->get([
            'id',
            'name',
            'code',
            'description',
            'type',
            'discount_value',
            'banner_url',
            'link_url',
            'start_date',
            'end_date',
            'badge_text',
            'badge_color',
        ]);

        return response()->json([
            'success' => true,
            'data' => $promotions,
        ]);
    }
}
