<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MemberBillingAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebBillingAddressController extends Controller
{
    /**
     * Get all billing addresses for the authenticated member
     */
    public function index()
    {
        $member = Auth::user();
        
        $addresses = MemberBillingAddress::where('member_id', $member->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'addresses' => $addresses,
        ]);
    }

    /**
     * Store a new billing address
     */
    public function store(Request $request)
    {
        $member = Auth::user();

        $validated = $request->validate([
            'type' => 'required|in:personal,company',
            'name' => 'required_if:type,personal|nullable|string|max:255',
            'company_name' => 'required_if:type,company|nullable|string|max:255',
            'tax_id' => 'required_if:type,company|nullable|string|max:13',
            'branch_name' => 'nullable|string|max:255',
            'address' => 'required|string|max:500',
            'sub_district' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:5',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_default' => 'boolean',
        ]);

        // If this is the first address or marked as default, set others to non-default
        if ($request->is_default || MemberBillingAddress::where('member_id', $member->id)->count() === 0) {
            MemberBillingAddress::where('member_id', $member->id)
                ->update(['is_default' => false]);
            $validated['is_default'] = true;
        }

        $validated['member_id'] = $member->id;
        
        $address = MemberBillingAddress::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'เพิ่มที่อยู่เรียบร้อยแล้ว',
            'address' => $address,
        ], 201);
    }

    /**
     * Update a billing address
     */
    public function update(Request $request, $id)
    {
        $member = Auth::user();

        $address = MemberBillingAddress::where('member_id', $member->id)
            ->where('id', $id)
            ->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบที่อยู่ที่ต้องการแก้ไข',
            ], 404);
        }

        $validated = $request->validate([
            'type' => 'required|in:personal,company',
            'name' => 'required_if:type,personal|nullable|string|max:255',
            'company_name' => 'required_if:type,company|nullable|string|max:255',
            'tax_id' => 'required_if:type,company|nullable|string|max:13',
            'branch_name' => 'nullable|string|max:255',
            'address' => 'required|string|max:500',
            'sub_district' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:5',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_default' => 'boolean',
        ]);

        // If marked as default, set others to non-default
        if ($request->is_default && !$address->is_default) {
            MemberBillingAddress::where('member_id', $member->id)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'แก้ไขที่อยู่เรียบร้อยแล้ว',
            'address' => $address->fresh(),
        ]);
    }

    /**
     * Delete a billing address
     */
    public function destroy($id)
    {
        $member = Auth::user();

        $address = MemberBillingAddress::where('member_id', $member->id)
            ->where('id', $id)
            ->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบที่อยู่ที่ต้องการลบ',
            ], 404);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        // If deleted address was default, set the first remaining address as default
        if ($wasDefault) {
            $firstAddress = MemberBillingAddress::where('member_id', $member->id)->first();
            if ($firstAddress) {
                $firstAddress->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'ลบที่อยู่เรียบร้อยแล้ว',
        ]);
    }

    /**
     * Set an address as default
     */
    public function setDefault($id)
    {
        $member = Auth::user();

        $address = MemberBillingAddress::where('member_id', $member->id)
            ->where('id', $id)
            ->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบที่อยู่ที่ต้องการตั้งเป็นหลัก',
            ], 404);
        }

        // Set all other addresses to non-default
        MemberBillingAddress::where('member_id', $member->id)
            ->where('id', '!=', $id)
            ->update(['is_default' => false]);

        // Set this address as default
        $address->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'ตั้งเป็นที่อยู่หลักเรียบร้อยแล้ว',
            'address' => $address->fresh(),
        ]);
    }
}
