<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wholesaler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class WholesalerController extends Controller
{
    /**
     * Display a listing of wholesalers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Wholesaler::withCount('tours');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('company_name_th', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Order
        $query->orderBy('created_at', 'desc');

        // Paginate
        $perPage = $request->input('per_page', 15);
        $wholesalers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $wholesalers->items(),
            'meta' => [
                'current_page' => $wholesalers->currentPage(),
                'last_page' => $wholesalers->lastPage(),
                'per_page' => $wholesalers->perPage(),
                'total' => $wholesalers->total(),
            ],
        ]);
    }

    /**
     * Store a newly created wholesaler.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // ข้อมูลพื้นฐาน
            'code' => ['required', 'string', 'max:50', 'unique:wholesalers,code'],
            'name' => ['required', 'string', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'website' => ['nullable', 'url', 'max:500'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
            
            // ข้อมูลติดต่อ
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            
            // ข้อมูลใบกำกับภาษี
            'tax_id' => ['nullable', 'string', 'max:20'],
            'company_name_th' => ['nullable', 'string', 'max:255'],
            'company_name_en' => ['nullable', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:10'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
        ], [
            'code.required' => 'กรุณาระบุรหัส',
            'code.unique' => 'รหัสนี้ถูกใช้งานแล้ว',
            'name.required' => 'กรุณาระบุชื่อ',
            'contact_email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'logo_url.url' => 'รูปแบบ URL โลโก้ไม่ถูกต้อง',
            'website.url' => 'รูปแบบ URL เว็บไซต์ไม่ถูกต้อง',
        ]);

        $wholesaler = Wholesaler::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'สร้างตัวแทนจำหน่ายสำเร็จ',
            'data' => $wholesaler,
        ], 201);
    }

    /**
     * Display the specified wholesaler.
     */
    public function show(Wholesaler $wholesaler): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $wholesaler,
        ]);
    }

    /**
     * Update the specified wholesaler.
     */
    public function update(Request $request, Wholesaler $wholesaler): JsonResponse
    {
        $validated = $request->validate([
            // ข้อมูลพื้นฐาน
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('wholesalers')->ignore($wholesaler->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'website' => ['nullable', 'url', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            
            // ข้อมูลติดต่อ
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            
            // ข้อมูลใบกำกับภาษี
            'tax_id' => ['nullable', 'string', 'max:20'],
            'company_name_th' => ['nullable', 'string', 'max:255'],
            'company_name_en' => ['nullable', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:10'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
        ], [
            'code.required' => 'กรุณาระบุรหัส',
            'code.unique' => 'รหัสนี้ถูกใช้งานแล้ว',
            'name.required' => 'กรุณาระบุชื่อ',
            'contact_email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'logo_url.url' => 'รูปแบบ URL โลโก้ไม่ถูกต้อง',
            'website.url' => 'รูปแบบ URL เว็บไซต์ไม่ถูกต้อง',
        ]);

        $wholesaler->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'อัปเดตตัวแทนจำหน่ายสำเร็จ',
            'data' => $wholesaler,
        ]);
    }

    /**
     * Remove the specified wholesaler.
     */
    public function destroy(Wholesaler $wholesaler): JsonResponse
    {
        // TODO: Check if has related tours before deleting
        
        $wholesaler->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบตัวแทนจำหน่ายสำเร็จ',
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Wholesaler $wholesaler): JsonResponse
    {
        $wholesaler->is_active = !$wholesaler->is_active;
        $wholesaler->save();

        return response()->json([
            'success' => true,
            'message' => $wholesaler->is_active ? 'เปิดใช้งานสำเร็จ' : 'ปิดใช้งานสำเร็จ',
            'data' => $wholesaler,
        ]);
    }
}
