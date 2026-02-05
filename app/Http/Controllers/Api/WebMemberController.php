<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * WebMemberController - สำหรับ Backend Admin จัดการสมาชิกหน้าเว็บ
 * แยกจากระบบ User ของ Backend
 */
class WebMemberController extends Controller
{
    /**
     * Get all web members with pagination
     */
    public function index(Request $request)
    {
        $query = WebMember::query();

        // Search by name, email, phone
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by phone verified
        if ($request->has('phone_verified')) {
            $query->where('phone_verified', $request->boolean('phone_verified'));
        }

        // Filter by consent marketing
        if ($request->has('consent_marketing')) {
            $query->where('consent_marketing', $request->boolean('consent_marketing'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->input('per_page', 15);
        $members = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * Get single web member
     */
    public function show(int $id)
    {
        $member = WebMember::withCount('wishlists')->find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลสมาชิก',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $member,
        ]);
    }

    /**
     * Update web member status
     */
    public function updateStatus(Request $request, int $id)
    {
        $member = WebMember::find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลสมาชิก',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,active,suspended,deleted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $member->status = $request->status;
        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'อัปเดตสถานะสำเร็จ',
            'data' => $member,
        ]);
    }

    /**
     * Reset member password (by admin)
     */
    public function resetPassword(Request $request, int $id)
    {
        $member = WebMember::find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลสมาชิก',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $member->password = Hash::make($request->password);
        $member->save();

        // Revoke all tokens
        $member->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'รีเซ็ตรหัสผ่านสำเร็จ',
        ]);
    }

    /**
     * Unlock member account
     */
    public function unlock(int $id)
    {
        $member = WebMember::find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลสมาชิก',
            ], 404);
        }

        $member->failed_login_attempts = 0;
        $member->locked_until = null;
        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'ปลดล็อกบัญชีสำเร็จ',
        ]);
    }

    /**
     * Get member statistics
     */
    public function statistics()
    {
        $total = WebMember::count();
        $active = WebMember::where('status', 'active')->count();
        $inactive = WebMember::where('status', 'inactive')->count();
        $suspended = WebMember::where('status', 'suspended')->count();
        $verified = WebMember::where('phone_verified', true)->count();
        $unverified = WebMember::where('phone_verified', false)->count();
        
        $newToday = WebMember::whereDate('created_at', today())->count();
        $newThisMonth = WebMember::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'suspended' => $suspended,
                'verified' => $verified,
                'unverified' => $unverified,
                'new_today' => $newToday,
                'new_this_month' => $newThisMonth,
            ],
        ]);
    }

    /**
     * Export members to CSV
     */
    public function export(Request $request)
    {
        $query = WebMember::query();

        // Apply same filters as index
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $members = $query->orderBy('created_at', 'desc')->get();

        $csv = "ID,ชื่อ,นามสกุล,อีเมล,โทรศัพท์,สถานะ,ยืนยันโทรศัพท์,รับข่าวสาร,วันที่สมัคร\n";
        
        foreach ($members as $member) {
            $csv .= implode(',', [
                $member->id,
                $member->first_name,
                $member->last_name,
                $member->email,
                $member->phone,
                $member->status,
                $member->phone_verified ? 'ใช่' : 'ไม่',
                $member->consent_marketing ? 'ใช่' : 'ไม่',
                $member->created_at->format('Y-m-d H:i:s'),
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="web_members_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Delete member (soft delete)
     */
    public function destroy(int $id)
    {
        $member = WebMember::find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลสมาชิก',
            ], 404);
        }

        // Revoke all tokens
        $member->tokens()->delete();
        
        // Soft delete
        $member->status = 'deleted';
        $member->save();
        $member->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบสมาชิกสำเร็จ',
        ]);
    }
}
