<?php

namespace App\Http\Controllers;

use App\Models\OurClient;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OurClientController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * Display a listing of our clients.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OurClient::query();

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('filename', 'like', "%{$search}%")
                  ->orWhere('alt', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $clients = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $clients->items(),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    /**
     * Get public active clients for website display
     */
    public function publicList(): JsonResponse
    {
        $clients = OurClient::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Store a new client logo/image.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|file|mimes:webp|max:100', // WebP เท่านั้น, 100KB max
            'name' => 'required|string|max:255',
            'alt' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'website_url' => 'nullable|url|max:500',
            'custom_filename' => 'nullable|string|max:200',
        ]);

        $file = $request->file('image');

        // Validate WebP format
        $mimeType = $file->getMimeType();
        if ($mimeType !== 'image/webp') {
            return response()->json([
                'success' => false,
                'message' => 'รองรับเฉพาะไฟล์ WebP เท่านั้น',
            ], 422);
        }

        // Get original image info
        $imageInfo = getimagesize($file->getRealPath());
        $originalWidth = $imageInfo[0] ?? 0;
        $originalHeight = $imageInfo[1] ?? 0;

        // Generate custom ID
        $customId = 'our-client-' . uniqid() . '-' . time();

        // Prepare metadata
        $metadata = [
            'type' => 'our-client',
            'name' => $validated['name'],
        ];

        // Upload to Cloudflare
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
        $maxSortOrder = OurClient::max('sort_order') ?? 0;

        // Create record
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = !empty($validated['custom_filename'])
            ? $validated['custom_filename'] . '.webp'
            : $originalName . '.webp';

        $client = OurClient::create([
            'cloudflare_id' => $result['id'],
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'filename' => $filename,
            'name' => $validated['name'],
            'alt' => $validated['alt'] ?? null,
            'description' => $validated['description'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'width' => $originalWidth,
            'height' => $originalHeight,
            'file_size' => $file->getSize(),
            'is_active' => true,
            'sort_order' => $maxSortOrder + 1,
        ]);

        return response()->json([
            'success' => true,
            'data' => $client,
            'message' => 'เพิ่มลูกค้าของเราสำเร็จ',
        ], 201);
    }

    /**
     * Display the specified client.
     */
    public function show(OurClient $ourClient): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $ourClient,
        ]);
    }

    /**
     * Update the specified client.
     */
    public function update(Request $request, OurClient $ourClient): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'alt' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'website_url' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $ourClient->update($validated);

        return response()->json([
            'success' => true,
            'data' => $ourClient,
            'message' => 'อัพเดทข้อมูลลูกค้าสำเร็จ',
        ]);
    }

    /**
     * Replace image of the specified client.
     */
    public function replaceImage(Request $request, OurClient $ourClient): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|mimes:webp|max:100', // WebP เท่านั้น, 100KB max
        ]);

        $file = $request->file('image');

        // Validate WebP format
        $mimeType = $file->getMimeType();
        if ($mimeType !== 'image/webp') {
            return response()->json([
                'success' => false,
                'message' => 'รองรับเฉพาะไฟล์ WebP เท่านั้น',
            ], 422);
        }

        // Get original image info
        $imageInfo = getimagesize($file->getRealPath());
        $originalWidth = $imageInfo[0] ?? 0;
        $originalHeight = $imageInfo[1] ?? 0;

        // Delete old image from Cloudflare
        if ($ourClient->cloudflare_id) {
            try {
                $this->cloudflare->delete($ourClient->cloudflare_id);
            } catch (\Exception $e) {
                Log::warning("Failed to delete old our-client image: {$ourClient->cloudflare_id}", ['error' => $e->getMessage()]);
            }
        }

        // Generate new custom ID
        $customId = 'our-client-' . uniqid() . '-' . time();

        // Upload new image to Cloudflare
        $result = $this->cloudflare->uploadFromFile($file, $customId, ['type' => 'our-client']);

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

        $ourClient->update([
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
            'data' => $ourClient->fresh(),
            'message' => 'เปลี่ยนรูปลูกค้าสำเร็จ',
        ]);
    }

    /**
     * Toggle client status.
     */
    public function toggleStatus(OurClient $ourClient): JsonResponse
    {
        $ourClient->update(['is_active' => !$ourClient->is_active]);

        return response()->json([
            'success' => true,
            'data' => $ourClient,
            'message' => $ourClient->is_active ? 'เปิดใช้งานลูกค้าแล้ว' : 'ปิดใช้งานลูกค้าแล้ว',
        ]);
    }

    /**
     * Reorder clients.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'clients' => 'required|array',
            'clients.*.id' => 'required|integer|exists:our_clients,id',
            'clients.*.sort_order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->clients as $item) {
                OurClient::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'จัดเรียงลูกค้าสำเร็จ',
        ]);
    }

    /**
     * Remove the specified client.
     */
    public function destroy(OurClient $ourClient): JsonResponse
    {
        // Delete from Cloudflare
        if ($ourClient->cloudflare_id) {
            try {
                $this->cloudflare->delete($ourClient->cloudflare_id);
            } catch (\Exception $e) {
                Log::warning("Failed to delete our-client image: {$ourClient->cloudflare_id}", ['error' => $e->getMessage()]);
            }
        }

        $ourClient->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบข้อมูลลูกค้าสำเร็จ',
        ]);
    }

    /**
     * Get statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => OurClient::count(),
            'active' => OurClient::where('is_active', true)->count(),
            'inactive' => OurClient::where('is_active', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
