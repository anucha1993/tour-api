<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MemberNotificationRead;
use App\Models\MemberPromotionClaim;
use App\Models\PromotionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebNotificationController extends Controller
{
    /**
     * GET /web/notifications
     * Return notifications for the authenticated member, unread first.
     */
    public function index(Request $request): JsonResponse
    {
        $member = $request->user();

        $notifications = PromotionNotification::active()
            ->forMember($member)
            ->withCount('claims')
            ->with([
                'reads'  => fn($q) => $q->where('member_id', $member->id),
                'claims' => fn($q) => $q->where('member_id', $member->id),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($n) {
                $isRead    = $n->reads->isNotEmpty();
                $isClaimed = $n->claims->isNotEmpty();
                $remaining = $n->max_claims !== null
                    ? max(0, $n->max_claims - $n->claims_count)
                    : null;
                return [
                    'id'               => $n->id,
                    'title'            => $n->title,
                    'description'      => $n->description,
                    'how_to_use'       => $n->how_to_use,
                    'banner_url'       => $n->banner_url,
                    'type'             => $n->type,
                    'is_read'          => $isRead,
                    'is_claimed'       => $isClaimed,
                    'claim_code'       => $isClaimed ? $n->claims->first()?->claim_code : null,
                    'max_claims'       => $n->max_claims,
                    'total_claims'     => $n->claims_count,
                    'remaining_claims' => $remaining,
                    'starts_at'        => $n->starts_at?->toISOString(),
                    'ends_at'          => $n->ends_at?->toISOString(),
                    'created_at'       => $n->created_at->toISOString(),
                ];
            })
            // Sort: unread first, then by latest
            ->sortBy([['is_read', 'asc'], ['created_at', 'desc']])
            ->values();

        $unreadCount = $notifications->where('is_read', false)->count();

        return response()->json([
            'success'      => true,
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * GET /web/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $member = $request->user();

        $total = PromotionNotification::active()->forMember($member)->count();
        $read  = MemberNotificationRead::where('member_id', $member->id)
            ->whereHas('notification', fn($q) => $q->active()->forMember($member))
            ->count();

        return response()->json([
            'success' => true,
            'count'   => max(0, $total - $read),
        ]);
    }

    /**
     * GET /web/notifications/{id}
     * Return full detail of one notification and auto-mark as read.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $member = $request->user();

        $n = PromotionNotification::active()
            ->forMember($member)
            ->withCount('claims')
            ->with([
                'reads'  => fn($q) => $q->where('member_id', $member->id),
                'claims' => fn($q) => $q->where('member_id', $member->id),
            ])
            ->findOrFail($id);

        // Auto-mark as read
        MemberNotificationRead::firstOrCreate(
            ['member_id' => $member->id, 'notification_id' => $n->id],
            ['read_at' => now()]
        );

        $isClaimed = $n->claims->isNotEmpty();
        $remaining = $n->max_claims !== null
            ? max(0, $n->max_claims - $n->claims_count)
            : null;

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $n->id,
                'title'            => $n->title,
                'description'      => $n->description,
                'how_to_use'       => $n->how_to_use,
                'banner_url'       => $n->banner_url,
                'type'             => $n->type,
                'is_read'          => true,
                'is_claimed'       => $isClaimed,
                'claim_code'       => $isClaimed ? $n->claims->first()?->claim_code : null,
                'max_claims'       => $n->max_claims,
                'total_claims'     => $n->claims_count,
                'remaining_claims' => $remaining,
                'starts_at'        => $n->starts_at?->toISOString(),
                'ends_at'          => $n->ends_at?->toISOString(),
                'created_at'       => $n->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * POST /web/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $member = $request->user();

        $notification = PromotionNotification::active()->forMember($member)->findOrFail($id);

        MemberNotificationRead::firstOrCreate(
            ['member_id' => $member->id, 'notification_id' => $notification->id],
            ['read_at' => now()]
        );

        return response()->json(['success' => true]);
    }

    /**
     * POST /web/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $member = $request->user();

        $ids = PromotionNotification::active()
            ->forMember($member)
            ->pluck('id');

        $existing = MemberNotificationRead::where('member_id', $member->id)
            ->whereIn('notification_id', $ids)
            ->pluck('notification_id')
            ->toArray();

        $toInsert = $ids->reject(fn($id) => in_array($id, $existing))
            ->map(fn($id) => [
                'member_id'       => $member->id,
                'notification_id' => $id,
                'read_at'         => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ])
            ->values()
            ->toArray();

        if (!empty($toInsert)) {
            MemberNotificationRead::insert($toInsert);
        }

        return response()->json(['success' => true, 'marked' => count($toInsert)]);
    }

    /**
     * POST /web/notifications/{id}/claim
     * Member claims/accepts the promotion.
     */
    public function claim(Request $request, int $id): JsonResponse
    {
        $member = $request->user();

        $notification = PromotionNotification::active()->forMember($member)->findOrFail($id);

        $already = MemberPromotionClaim::where('member_id', $member->id)
            ->where('notification_id', $notification->id)
            ->exists();

        if ($already) {
            $existingClaim = MemberPromotionClaim::where('member_id', $member->id)
                ->where('notification_id', $notification->id)
                ->first();
            return response()->json([
                'success'    => true,
                'is_claimed' => true,
                'claim_code' => $existingClaim?->claim_code,
                'message'    => 'คุณรับสิทธิ์นี้ไปแล้ว',
            ]);
        }

        // Check claim limit
        if ($notification->max_claims !== null) {
            $currentCount = MemberPromotionClaim::where('notification_id', $notification->id)->count();
            if ($currentCount >= $notification->max_claims) {
                return response()->json([
                    'success' => false,
                    'message' => 'สิทธิ์โปรโมชั่นนี้ถูกรับครบจำนวนแล้ว (' . $notification->max_claims . ' สิทธิ์)',
                    'limit_reached' => true,
                ], 422);
            }
        }

        // Generate unique 8-char uppercase alphanumeric code
        do {
            $code = strtoupper(Str::random(8));
        } while (MemberPromotionClaim::where('claim_code', $code)->exists());

        MemberPromotionClaim::create([
            'member_id'       => $member->id,
            'notification_id' => $notification->id,
            'claim_code'      => $code,
            'status'          => 'claimed',
            'claimed_at'      => now(),
        ]);

        // Also mark as read
        MemberNotificationRead::firstOrCreate(
            ['member_id' => $member->id, 'notification_id' => $notification->id],
            ['read_at' => now()]
        );

        return response()->json([
            'success'    => true,
            'is_claimed' => true,
            'claim_code' => $code,
            'message'    => 'รับสิทธิ์โปรโมชั่นสำเร็จ',
        ]);
    }
}
