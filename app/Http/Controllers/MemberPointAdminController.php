<?php

namespace App\Http\Controllers;

use App\Models\MemberLevel;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\PointRedemption;
use App\Models\WebMember;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberPointAdminController extends Controller
{
    public function __construct(private PointService $pointService) {}

    // ===== Member Levels CRUD =====

    public function listLevels(): JsonResponse
    {
        $levels = MemberLevel::ordered()->get();
        return response()->json(['success' => true, 'data' => $levels]);
    }

    public function createLevel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:50|unique:member_levels,slug',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:20',
            'min_spending' => 'required|numeric|min:0',
            'discount_percent' => 'required|numeric|min:0|max:100',
            'point_multiplier' => 'required|numeric|min:0.01|max:10',
            'redemption_rate' => 'required|numeric|min:0.01|max:100',
            'benefits' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($data['is_default'])) {
            MemberLevel::where('is_default', true)->update(['is_default' => false]);
        }

        $level = MemberLevel::create($data);
        return response()->json(['success' => true, 'data' => $level], 201);
    }

    public function updateLevel(Request $request, int $id): JsonResponse
    {
        $level = MemberLevel::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:50',
            'slug' => 'sometimes|string|max:50|unique:member_levels,slug,' . $id,
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:20',
            'min_spending' => 'sometimes|numeric|min:0',
            'discount_percent' => 'sometimes|numeric|min:0|max:100',
            'point_multiplier' => 'sometimes|numeric|min:0.01|max:10',
            'redemption_rate' => 'sometimes|numeric|min:0.01|max:100',
            'benefits' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($data['is_default'])) {
            MemberLevel::where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $level->update($data);
        return response()->json(['success' => true, 'data' => $level->fresh()]);
    }

    public function deleteLevel(int $id): JsonResponse
    {
        $level = MemberLevel::findOrFail($id);

        if ($level->is_default) {
            return response()->json(['success' => false, 'message' => 'ไม่สามารถลบระดับเริ่มต้นได้'], 422);
        }

        $memberCount = $level->members()->count();
        if ($memberCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "ไม่สามารถลบได้ มีสมาชิก {$memberCount} คนในระดับนี้"
            ], 422);
        }

        $level->delete();
        return response()->json(['success' => true]);
    }

    // ===== Point Rules CRUD =====

    public function listRules(): JsonResponse
    {
        $rules = PointRule::orderBy('action')->get();
        return response()->json(['success' => true, 'data' => $rules]);
    }

    public function updateRule(Request $request, int $id): JsonResponse
    {
        $rule = PointRule::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
            'calc_type' => 'sometimes|in:fixed,percent',
            'points' => 'sometimes|integer|min:0',
            'percent_of_amount' => 'sometimes|numeric|min:0',
            'max_points_per_day' => 'nullable|integer|min:0',
            'max_points_per_action' => 'nullable|integer|min:0',
            'cooldown_minutes' => 'sometimes|integer|min:0',
            'expire_days' => 'sometimes|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $rule->update($data);
        return response()->json(['success' => true, 'data' => $rule->fresh()]);
    }

    // ===== Member Points Management =====

    public function listMembers(Request $request): JsonResponse
    {
        $query = WebMember::with('level')
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'total_points', 'lifetime_points', 'lifetime_spending', 'current_level_id', 'status');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($levelId = $request->input('level_id')) {
            $query->where('current_level_id', $levelId);
        }

        $sortBy = $request->input('sort_by', 'lifetime_points');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $members = $query->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $members]);
    }

    public function getMemberDetail(int $memberId): JsonResponse
    {
        $member = WebMember::with('level')->findOrFail($memberId);
        $summary = $this->pointService->getSummary($member);

        return response()->json([
            'success' => true,
            'data' => [
                'member' => [
                    'id' => $member->id,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                ],
                'summary' => $summary,
            ],
        ]);
    }

    public function getMemberTransactions(Request $request, int $memberId): JsonResponse
    {
        $transactions = PointTransaction::with('rule:id,action,name,icon')
            ->where('member_id', $memberId)
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    public function adjustMemberPoints(Request $request, int $memberId): JsonResponse
    {
        $data = $request->validate([
            'points' => 'required|integer|not_in:0',
            'description' => 'required|string|max:255',
        ]);

        $member = WebMember::findOrFail($memberId);

        if ($data['points'] < 0 && abs($data['points']) > $member->total_points) {
            return response()->json([
                'success' => false,
                'message' => 'คะแนนไม่เพียงพอ (มี ' . $member->total_points . ' คะแนน)'
            ], 422);
        }

        $txn = $this->pointService->adjustPoints($member, $data['points'], $data['description']);

        return response()->json([
            'success' => true,
            'data' => $txn,
            'message' => $data['points'] > 0
                ? "เพิ่ม {$data['points']} คะแนนสำเร็จ"
                : "หัก " . abs($data['points']) . " คะแนนสำเร็จ",
        ]);
    }

    // ===== Dashboard Stats =====

    public function stats(): JsonResponse
    {
        $totalMembers = WebMember::where('status', 'active')->count();
        $totalPointsCirculating = WebMember::where('status', 'active')->sum('total_points');
        $pointsEarnedToday = PointTransaction::where('type', 'earn')->whereDate('created_at', today())->sum('points');
        $pointsSpentToday = PointTransaction::where('type', 'spend')->whereDate('created_at', today())->sum('points');
        $redemptionsToday = PointRedemption::whereDate('created_at', today())->count();

        $levelDistribution = MemberLevel::active()->ordered()->get()->map(fn($level) => [
            'name' => $level->name,
            'icon' => $level->icon,
            'color' => $level->color,
            'count' => WebMember::where('current_level_id', $level->id)->where('status', 'active')->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'total_members' => $totalMembers,
                'total_points_circulating' => (int) $totalPointsCirculating,
                'points_earned_today' => (int) $pointsEarnedToday,
                'points_spent_today' => (int) abs($pointsSpentToday),
                'redemptions_today' => $redemptionsToday,
                'level_distribution' => $levelDistribution,
            ],
        ]);
    }
}
