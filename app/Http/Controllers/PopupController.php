<?php

namespace App\Http\Controllers;

use App\Models\Popup;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PopupController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * Display a listing of popups.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Popup::query();

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by type
        if ($request->filled('popup_type')) {
            $query->where('popup_type', $request->popup_type);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $popups = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $popups->items(),
            'meta' => [
                'current_page' => $popups->currentPage(),
                'last_page' => $popups->lastPage(),
                'per_page' => $popups->perPage(),
                'total' => $popups->total(),
            ],
        ]);
    }

    /**
     * Get active popups for public display
     */
    public function publicList(): JsonResponse
    {
        $popups = Popup::active()
            ->currentlyValid()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $popups,
        ]);
    }

    /**
     * Store a new popup.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'alt_text' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:100',
            'button_link' => 'nullable|string|max:500',
            'button_color' => 'nullable|string|max:20',
            'popup_type' => 'nullable|in:image,content,promo,newsletter,announcement',
            'display_frequency' => 'nullable|in:always,once_per_session,once_per_day,once_per_week,once',
            'delay_seconds' => 'nullable|integer|min:0|max:60',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'nullable|boolean',
            'show_close_button' => 'nullable|boolean',
            'close_on_overlay' => 'nullable|boolean',
        ]);

        $imageData = [];

        // Upload image if provided
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageInfo = getimagesize($file->getRealPath());

            $customId = 'popup-' . uniqid() . '-' . time();
            $result = $this->cloudflare->uploadFromFile($file, $customId, ['type' => 'popup']);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'อัพโหลดรูปไป Cloudflare ไม่สำเร็จ',
                ], 500);
            }

            $imageData = [
                'cloudflare_id' => $result['id'],
                'image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
                'thumbnail_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
                'width' => $imageInfo[0] ?? 0,
                'height' => $imageInfo[1] ?? 0,
                'file_size' => $file->getSize(),
            ];
        }

        // Get max sort order
        $maxSortOrder = Popup::max('sort_order') ?? 0;

        $popup = Popup::create(array_merge([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'alt_text' => $validated['alt_text'] ?? null,
            'button_text' => $validated['button_text'] ?? null,
            'button_link' => $validated['button_link'] ?? null,
            'button_color' => $validated['button_color'] ?? 'primary',
            'popup_type' => $validated['popup_type'] ?? 'image',
            'display_frequency' => $validated['display_frequency'] ?? 'once_per_session',
            'delay_seconds' => $validated['delay_seconds'] ?? 2,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'show_close_button' => $validated['show_close_button'] ?? true,
            'close_on_overlay' => $validated['close_on_overlay'] ?? true,
            'sort_order' => $maxSortOrder + 1,
        ], $imageData));

        return response()->json([
            'success' => true,
            'data' => $popup,
            'message' => 'สร้าง Popup สำเร็จ',
        ], 201);
    }

    /**
     * Display the specified popup.
     */
    public function show(Popup $popup): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $popup,
        ]);
    }

    /**
     * Update the specified popup.
     */
    public function update(Request $request, Popup $popup): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'alt_text' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:100',
            'button_link' => 'nullable|string|max:500',
            'button_color' => 'nullable|string|max:20',
            'popup_type' => 'nullable|in:image,content,promo,newsletter,announcement',
            'display_frequency' => 'nullable|in:always,once_per_session,once_per_day,once_per_week,once',
            'delay_seconds' => 'nullable|integer|min:0|max:60',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'show_close_button' => 'nullable|boolean',
            'close_on_overlay' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $popup->update($validated);

        return response()->json([
            'success' => true,
            'data' => $popup,
            'message' => 'อัพเดท Popup สำเร็จ',
        ]);
    }

    /**
     * Replace image of the specified popup.
     */
    public function replaceImage(Request $request, Popup $popup): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ]);

        $file = $request->file('image');
        $imageInfo = getimagesize($file->getRealPath());

        // Delete old image from Cloudflare
        if ($popup->cloudflare_id) {
            try {
                $this->cloudflare->delete($popup->cloudflare_id);
            } catch (\Exception $e) {
                Log::warning("Failed to delete old popup image: {$popup->cloudflare_id}", ['error' => $e->getMessage()]);
            }
        }

        $customId = 'popup-' . uniqid() . '-' . time();
        $result = $this->cloudflare->uploadFromFile($file, $customId, ['type' => 'popup']);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'อัพโหลดรูปใหม่ไป Cloudflare ไม่สำเร็จ',
            ], 500);
        }

        $popup->update([
            'cloudflare_id' => $result['id'],
            'image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'thumbnail_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'width' => $imageInfo[0] ?? 0,
            'height' => $imageInfo[1] ?? 0,
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $popup->fresh(),
            'message' => 'เปลี่ยนรูป Popup สำเร็จ',
        ]);
    }

    /**
     * Toggle popup status.
     */
    public function toggleStatus(Popup $popup): JsonResponse
    {
        $popup->update(['is_active' => !$popup->is_active]);

        return response()->json([
            'success' => true,
            'data' => $popup,
            'message' => $popup->is_active ? 'เปิดใช้งาน Popup แล้ว' : 'ปิดใช้งาน Popup แล้ว',
        ]);
    }

    /**
     * Reorder popups.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'popups' => 'required|array',
            'popups.*.id' => 'required|integer|exists:popups,id',
            'popups.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->popups as $item) {
            Popup::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'จัดเรียง Popup สำเร็จ',
        ]);
    }

    /**
     * Remove the specified popup.
     */
    public function destroy(Popup $popup): JsonResponse
    {
        if ($popup->cloudflare_id) {
            try {
                $this->cloudflare->delete($popup->cloudflare_id);
            } catch (\Exception $e) {
                Log::warning("Failed to delete popup image: {$popup->cloudflare_id}", ['error' => $e->getMessage()]);
            }
        }

        $popup->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบ Popup สำเร็จ',
        ]);
    }

    /**
     * Get statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Popup::count(),
            'active' => Popup::where('is_active', true)->count(),
            'inactive' => Popup::where('is_active', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
