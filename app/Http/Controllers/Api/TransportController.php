<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transport;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class TransportController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * Display a listing of transports.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transport::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('code1', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Order: active first, then items with images, then by name
        $query->orderByRaw("CASE WHEN status = 'on' THEN 0 ELSE 1 END")
              ->orderByRaw("CASE WHEN image IS NOT NULL AND image != '' THEN 0 ELSE 1 END")
              ->orderBy('name', 'asc');

        // Paginate
        $perPage = $request->input('per_page', 15);
        $transports = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transports->items(),
            'meta' => [
                'current_page' => $transports->currentPage(),
                'last_page' => $transports->lastPage(),
                'per_page' => $transports->perPage(),
                'total' => $transports->total(),
            ],
        ]);
    }

    /**
     * Store a newly created transport.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'code1' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_keys(Transport::TYPES))],
            'image' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', Rule::in([Transport::STATUS_ON, Transport::STATUS_OFF])],
        ], [
            'code.required' => 'กรุณาระบุรหัส IATA',
            'name.required' => 'กรุณาระบุชื่อ',
            'type.required' => 'กรุณาระบุประเภท',
            'type.in' => 'ประเภทไม่ถูกต้อง',
            'status.required' => 'กรุณาระบุสถานะ',
            'status.in' => 'สถานะไม่ถูกต้อง',
        ]);

        $transport = Transport::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'สร้างข้อมูลการเดินทางสำเร็จ',
            'data' => $transport,
        ], 201);
    }

    /**
     * Display the specified transport.
     */
    public function show(Transport $transport): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $transport,
        ]);
    }

    /**
     * Update the specified transport.
     */
    public function update(Request $request, Transport $transport): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:10'],
            'code1' => ['nullable', 'string', 'max:10'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', Rule::in(array_keys(Transport::TYPES))],
            'image' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'required', 'string', Rule::in([Transport::STATUS_ON, Transport::STATUS_OFF])],
        ], [
            'code.required' => 'กรุณาระบุรหัส IATA',
            'name.required' => 'กรุณาระบุชื่อ',
            'type.in' => 'ประเภทไม่ถูกต้อง',
            'status.in' => 'สถานะไม่ถูกต้อง',
        ]);

        $transport->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'อัปเดตข้อมูลสำเร็จ',
            'data' => $transport,
        ]);
    }

    /**
     * Remove the specified transport.
     */
    public function destroy(Transport $transport): JsonResponse
    {
        // ลบรูปจาก Cloudflare ถ้ามี
        if ($transport->image && str_contains($transport->image, 'imagedelivery.net')) {
            // Extract image ID from URL
            preg_match('/\/([^\/]+)\/public$/', $transport->image, $matches);
            if (!empty($matches[1])) {
                $this->cloudflare->delete("transports/{$matches[1]}");
            }
        }

        $transport->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบข้อมูลสำเร็จ',
        ]);
    }

    /**
     * Toggle transport status.
     */
    public function toggleStatus(Transport $transport): JsonResponse
    {
        $transport->status = $transport->status === Transport::STATUS_ON 
            ? Transport::STATUS_OFF 
            : Transport::STATUS_ON;
        $transport->save();

        return response()->json([
            'success' => true,
            'message' => $transport->status === Transport::STATUS_ON 
                ? 'เปิดใช้งานสำเร็จ' 
                : 'ปิดใช้งานสำเร็จ',
            'data' => $transport,
        ]);
    }

    /**
     * Get transport types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Transport::TYPES,
        ]);
    }

    /**
     * Upload image for transport.
     */
    public function uploadImage(Request $request, Transport $transport): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // 5MB max
        ], [
            'image.required' => 'กรุณาเลือกรูปภาพ',
            'image.image' => 'ไฟล์ต้องเป็นรูปภาพ',
            'image.max' => 'ขนาดไฟล์ต้องไม่เกิน 5MB',
        ]);

        $file = $request->file('image');
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $customId = "transports/{$filename}-" . time();

        // Upload to Cloudflare
        $result = $this->cloudflare->uploadFromUrl(
            $file->getRealPath(),
            $customId,
            [
                'folder' => 'transports',
                'type' => 'transport',
                'transport_id' => $transport->id,
            ]
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'อัปโหลดรูปภาพล้มเหลว',
            ], 500);
        }

        // Update transport image URL
        $transport->image = $this->cloudflare->getDisplayUrl($result['id']);
        $transport->save();

        return response()->json([
            'success' => true,
            'message' => 'อัปโหลดรูปภาพสำเร็จ',
            'data' => $transport,
        ]);
    }
}
