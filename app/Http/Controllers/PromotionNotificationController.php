<?php

namespace App\Http\Controllers;

use App\Models\MemberLevel;
use App\Models\MemberPromotionClaim;
use App\Models\PromotionNotification;
use App\Services\CloudflareImagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PromotionNotificationController extends Controller
{
    public function __construct(private CloudflareImagesService $cloudflare) {}
    /**
     * GET /dashboard/promotion-notifications
     */
    public function index(Request $request): JsonResponse
    {
        $query = PromotionNotification::with('targetLevel:id,name,icon');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $notifications = $query
            ->withCount(['reads', 'claims'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($n) => [
            'id'           => $n->id,
            'title'        => $n->title,
            'description'  => $n->description,
            'banner_url'   => $n->banner_url,
            'cloudflare_id'=> $n->cloudflare_id,
            'type'         => $n->type,
            'target_type'  => $n->target_type,
            'target_level' => $n->targetLevel ? ['id' => $n->targetLevel->id, 'name' => $n->targetLevel->name, 'icon' => $n->targetLevel->icon] : null,
            'is_active'    => $n->is_active,
            'starts_at'    => $n->starts_at?->toISOString(),
            'ends_at'      => $n->ends_at?->toISOString(),
            'created_at'   => $n->created_at->toISOString(),
            'read_count'   => $n->reads_count,
            'claim_count'  => $n->claims_count,
            'max_claims'   => $n->max_claims,
            'how_to_use'   => $n->how_to_use,
        ]);

        return response()->json(['success' => true, 'data' => $notifications]);
    }

    /**
     * POST /dashboard/promotion-notifications
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'how_to_use'      => 'nullable|string',
            'max_claims'      => 'nullable|integer|min:1',
            'banner_url'      => 'nullable|string|max:500',
            'type'            => 'required|in:promotion,flash_sale,birthday,special,custom',
            'target_type'     => 'required|in:all,level',
            'target_level_id' => 'nullable|exists:member_levels,id',
            'is_active'       => 'boolean',
            'starts_at'       => 'nullable|date',
            'ends_at'         => 'nullable|date|after_or_equal:starts_at',
        ]);

        $validated['created_by'] = $request->user()?->id;

        $notification = PromotionNotification::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'สร้างการแจ้งเตือนสำเร็จ',
            'data'    => $notification,
        ], 201);
    }

    /**
     * GET /dashboard/promotion-notifications/{id}
     */
    public function show(int $id): JsonResponse
    {
        $notification = PromotionNotification::with('targetLevel:id,name,icon')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $notification]);
    }

    /**
     * PUT /dashboard/promotion-notifications/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $notification = PromotionNotification::findOrFail($id);

        $validated = $request->validate([
            'title'           => 'sometimes|required|string|max:255',
            'description'     => 'nullable|string',
            'how_to_use'      => 'nullable|string',
            'max_claims'      => 'nullable|integer|min:1',
            'banner_url'      => 'nullable|string|max:500',
            'type'            => 'sometimes|in:promotion,flash_sale,birthday,special,custom',
            'target_type'     => 'sometimes|in:all,level',
            'target_level_id' => 'nullable|exists:member_levels,id',
            'is_active'       => 'boolean',
            'starts_at'       => 'nullable|date',
            'ends_at'         => 'nullable|date|after_or_equal:starts_at',
        ]);

        $notification->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'อัปเดตการแจ้งเตือนสำเร็จ',
            'data'    => $notification,
        ]);
    }

    /**
     * DELETE /dashboard/promotion-notifications/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $notification = PromotionNotification::findOrFail($id);
        $notification->reads()->delete();
        $notification->delete();

        return response()->json(['success' => true, 'message' => 'ลบการแจ้งเตือนสำเร็จ']);
    }

    /**
     * PATCH /dashboard/promotion-notifications/{id}/toggle-status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $notification = PromotionNotification::findOrFail($id);
        $notification->update(['is_active' => !$notification->is_active]);

        return response()->json([
            'success'   => true,
            'is_active' => $notification->is_active,
            'message'   => $notification->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว',
        ]);
    }

    /**
     * GET /dashboard/promotion-notifications/meta
     * Return member levels for dropdowns
     */
    public function meta(): JsonResponse
    {
        $levels = MemberLevel::orderBy('min_spending')
            ->get(['id', 'name', 'icon', 'min_spending']);

        return response()->json(['success' => true, 'levels' => $levels]);
    }

    /**
     * POST /dashboard/promotion-notifications/{id}/upload-banner
     */
    public function uploadBanner(Request $request, int $id): JsonResponse
    {
        $notification = PromotionNotification::findOrFail($id);

        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ]);

        try {
            // Delete old image if exists
            if ($notification->cloudflare_id) {
                try {
                    $this->cloudflare->delete($notification->cloudflare_id);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old promotion-notification banner', [
                        'cloudflare_id' => $notification->cloudflare_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $uniqueId = "promotion-notifications/{$notification->id}-" . time();
            $result   = $this->cloudflare->uploadFromFile($request->file('image'), $uniqueId);

            if (!$result || !isset($result['id'])) {
                throw new \Exception('Cloudflare upload failed');
            }

            $bannerUrl = $this->cloudflare->getDisplayUrl($result['id']);

            $notification->update([
                'cloudflare_id' => $result['id'],
                'banner_url'    => $bannerUrl,
            ]);

            return response()->json([
                'success'    => true,
                'message'    => 'อัปโหลดรูปภาพสำเร็จ',
                'banner_url' => $bannerUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload promotion-notification banner', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถอัปโหลดรูปภาพได้: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /dashboard/promotion-notifications/{id}/delete-banner
     */
    public function deleteBanner(int $id): JsonResponse
    {
        $notification = PromotionNotification::findOrFail($id);

        if (!$notification->cloudflare_id) {
            return response()->json(['success' => false, 'message' => 'ไม่พบรูปภาพ'], 404);
        }

        try {
            $this->cloudflare->delete($notification->cloudflare_id);
        } catch (\Exception $e) {
            Log::warning('Failed to delete promotion-notification banner from Cloudflare', [
                'cloudflare_id' => $notification->cloudflare_id,
                'error' => $e->getMessage(),
            ]);
        }

        $notification->update(['cloudflare_id' => null, 'banner_url' => null]);

        return response()->json(['success' => true, 'message' => 'ลบรูปภาพสำเร็จ']);
    }

    /**
     * GET /dashboard/promotion-notifications/{id}/claims
     * Admin: list members who claimed this notification
     */
    public function claims(Request $request, int $id): JsonResponse
    {
        $notification = PromotionNotification::withCount('claims')->findOrFail($id);

        $query = MemberPromotionClaim::with('member:id,first_name,last_name,phone,email')
            ->where('notification_id', $notification->id);

        // Search by name / phone / email / claim_code
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('claim_code', 'like', "%{$search}%")
                  ->orWhereHas('member', function ($m) use ($search) {
                      $m->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name',  'like', "%{$search}%")
                        ->orWhere('phone',      'like', "%{$search}%")
                        ->orWhere('email',      'like', "%{$search}%");
                  });
            });
        }

        $claims = $query->orderByDesc('claimed_at')->get()
            ->map(fn($c) => [
                'id'         => $c->id,
                'claim_code' => $c->claim_code,
                'member'     => $c->member ? [
                    'id'         => $c->member->id,
                    'name'       => $c->member->first_name . ' ' . $c->member->last_name,
                    'phone'      => $c->member->phone,
                    'email'      => $c->member->email,
                ] : null,
                'status'     => $c->status,
                'claimed_at' => $c->claimed_at->toISOString(),
                'used_at'    => $c->used_at?->toISOString(),
            ]);

        return response()->json([
            'success'      => true,
            'data'         => $claims,
            'total_claims' => $notification->claims_count,
            'max_claims'   => $notification->max_claims,
            'remaining'    => $notification->max_claims !== null
                ? max(0, $notification->max_claims - $notification->claims_count)
                : null,
            'filtered'     => $claims->count(),
        ]);
    }

    /**
     * GET /dashboard/promotion-notifications/claims/lookup?code=XXXXXXXX
     * Admin: search a claim by code to quickly verify.
     */
    public function lookupByCode(Request $request): JsonResponse
    {
        $code = strtoupper(trim($request->input('code', '')));

        if (strlen($code) < 3) {
            return response()->json(['success' => false, 'message' => 'กรุณาใส่รหัสอย่างน้อย 3 ตัวอักษร'], 422);
        }

        $claim = MemberPromotionClaim::with([
                'member:id,first_name,last_name,phone,email',
                'notification:id,title,type',
            ])
            ->where('claim_code', $code)
            ->first();

        if (!$claim) {
            return response()->json(['success' => false, 'message' => 'ไม่พบรหัสสิทธิ์นี้'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $claim->id,
                'claim_code'   => $claim->claim_code,
                'status'       => $claim->status,
                'claimed_at'   => $claim->claimed_at->toISOString(),
                'used_at'      => $claim->used_at?->toISOString(),
                'member'       => $claim->member ? [
                    'id'    => $claim->member->id,
                    'name'  => $claim->member->first_name . ' ' . $claim->member->last_name,
                    'phone' => $claim->member->phone,
                    'email' => $claim->member->email,
                ] : null,
                'notification' => $claim->notification ? [
                    'id'    => $claim->notification->id,
                    'title' => $claim->notification->title,
                    'type'  => $claim->notification->type,
                ] : null,
            ],
        ]);
    }

    /**
     * PATCH /dashboard/promotion-notifications/claims/{claimId}/mark-used
     * Admin marks a claim as used.
     */
    public function markClaimUsed(int $claimId): JsonResponse
    {
        $claim = MemberPromotionClaim::findOrFail($claimId);

        if ($claim->status === 'used') {
            return response()->json(['success' => false, 'message' => 'ใช้สิทธิ์ไปแล้ว'], 422);
        }

        $claim->update([
            'status'  => 'used',
            'used_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ยืนยันการใช้สิทธิ์สำเร็จ',
            'data'    => [
                'id'      => $claim->id,
                'status'  => 'used',
                'used_at' => $claim->used_at->toISOString(),
            ],
        ]);
    }
}
