<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MemberLevel;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebMemberPointController extends Controller
{
    public function __construct(private PointService $pointService) {}

    /**
     * Get current member's point summary
     */
    public function summary(Request $request): JsonResponse
    {
        $member = $request->user();
        $summary = $this->pointService->getSummary($member);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get point transaction history
     */
    public function history(Request $request): JsonResponse
    {
        $member = $request->user();

        $query = PointTransaction::with('rule:id,action,name,icon')
            ->where('member_id', $member->id);

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $transactions = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Get active point rules for display
     */
    public function rules(): JsonResponse
    {
        $rules = PointRule::active()->orderBy('action')->get()->map(fn($rule) => [
            'id' => $rule->id,
            'action' => $rule->action,
            'name' => $rule->name,
            'description' => $rule->description,
            'icon' => $rule->icon,
            'calc_type' => $rule->calc_type,
            'points' => $rule->points,
            'percent_of_amount' => (float) $rule->percent_of_amount,
            'max_points_per_day' => $rule->max_points_per_day,
            'max_points_per_action' => $rule->max_points_per_action,
            'cooldown_minutes' => $rule->cooldown_minutes,
        ]);

        return response()->json(['success' => true, 'data' => $rules]);
    }

    /**
     * Get all member levels for display
     */
    public function levels(): JsonResponse
    {
        $levels = MemberLevel::active()->ordered()->get()->map(fn($level) => [
            'id' => $level->id,
            'name' => $level->name,
            'slug' => $level->slug,
            'icon' => $level->icon,
            'color' => $level->color,
            'min_spending' => (float) $level->min_spending,
            'discount_percent' => (float) $level->discount_percent,
            'point_multiplier' => (float) $level->point_multiplier,
            'redemption_rate' => (float) $level->redemption_rate,
            'benefits' => $level->benefits,
        ]);

        return response()->json(['success' => true, 'data' => $levels]);
    }

    /**
     * Simulate point redemption (preview discount)
     */
    public function previewRedeem(Request $request): JsonResponse
    {
        $member = $request->user();

        $data = $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        if ($data['points'] > $member->total_points) {
            return response()->json([
                'success' => false,
                'message' => 'คะแนนไม่เพียงพอ',
            ], 422);
        }

        $level = $member->level ?? MemberLevel::getDefault();
        $rate = $level?->redemption_rate ?? 1.0;
        $discount = $data['points'] * $rate;

        return response()->json([
            'success' => true,
            'data' => [
                'points' => $data['points'],
                'redemption_rate' => (float) $rate,
                'discount_amount' => round($discount, 2),
                'remaining_points' => $member->total_points - $data['points'],
            ],
        ]);
    }

    /**
     * Redeem points for discount
     */
    public function redeem(Request $request): JsonResponse
    {
        $member = $request->user();

        $data = $request->validate([
            'points' => 'required|integer|min:1',
            'booking_code' => 'nullable|string|max:50',
        ]);

        if ($data['points'] > $member->total_points) {
            return response()->json([
                'success' => false,
                'message' => 'คะแนนไม่เพียงพอ',
            ], 422);
        }

        $redemption = $this->pointService->spendPoints(
            $member,
            $data['points'],
            'แลกส่วนลด',
            $data['booking_code'] ?? null
        );

        if (!$redemption) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถแลกคะแนนได้',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'redemption_id' => $redemption->id,
                'points_used' => $redemption->points_used,
                'discount_amount' => (float) $redemption->discount_amount,
                'remaining_points' => $member->fresh()->total_points,
            ],
            'message' => "แลกส่วนลด ฿" . number_format($redemption->discount_amount, 2) . " สำเร็จ",
        ]);
    }
}
