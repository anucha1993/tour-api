<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Order
        $query->orderBy('created_at', 'desc');

        // Paginate
        $perPage = $request->input('per_page', 15);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ],
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in(['admin', 'manager', 'staff'])],
            'is_active' => ['boolean'],
        ], [
            'name.required' => 'กรุณาระบุชื่อ',
            'email.required' => 'กรุณาระบุอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique' => 'อีเมลนี้ถูกใช้งานแล้ว',
            'password.required' => 'กรุณาระบุรหัสผ่าน',
            'password.confirmed' => 'รหัสผ่านไม่ตรงกัน',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร',
            'role.required' => 'กรุณาระบุบทบาท',
            'role.in' => 'บทบาทไม่ถูกต้อง',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'สร้างผู้ใช้งานสำเร็จ',
            'data' => $user,
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::min(8)],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'manager', 'staff'])],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'กรุณาระบุชื่อ',
            'email.required' => 'กรุณาระบุอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique' => 'อีเมลนี้ถูกใช้งานแล้ว',
            'password.confirmed' => 'รหัสผ่านไม่ตรงกัน',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร',
            'role.required' => 'กรุณาระบุบทบาท',
            'role.in' => 'บทบาทไม่ถูกต้อง',
        ]);

        // Update fields
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        if (isset($validated['role'])) {
            $user->role = $validated['role'];
        }
        if (isset($validated['is_active'])) {
            $user->is_active = $validated['is_active'];
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'อัปเดตผู้ใช้งานสำเร็จ',
            'data' => $user,
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Prevent deleting self
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถลบบัญชีของตัวเองได้',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบผู้ใช้งานสำเร็จ',
        ]);
    }
}
