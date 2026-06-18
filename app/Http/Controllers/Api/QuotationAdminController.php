<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuotationAdminController extends Controller
{
    /**
     * Admin list with filters
     */
    public function index(Request $request)
    {
        $query = Quotation::with([
            'member:id,first_name,last_name,phone,email',
            'tour:id,title,slug',
            'handler:id,name',
        ])->orderBy('created_at', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }
        if ($request->boolean('mine')) {
            $query->where('handled_by_user_id', $request->user()->id);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    public function show(int $id)
    {
        $quotation = Quotation::with([
            'member:id,first_name,last_name,phone,email,line_id',
            'tour:id,title,slug,cover_image_url',
            'handler:id,name',
        ])->find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => 'ไม่พบใบเสนอราคา'], 404);
        }
        return response()->json(['success' => true, 'data' => $quotation]);
    }

    /**
     * Statistics for dashboard
     */
    public function statistics()
    {
        $stats = Quotation::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'success' => true,
            'data' => [
                'requested' => (int) ($stats['requested'] ?? 0),
                'draft' => (int) ($stats['draft'] ?? 0),
                'sent' => (int) ($stats['sent'] ?? 0),
                'accepted' => (int) ($stats['accepted'] ?? 0),
                'declined' => (int) ($stats['declined'] ?? 0),
                'expired' => (int) ($stats['expired'] ?? 0),
                'cancelled' => (int) ($stats['cancelled'] ?? 0),
                'total' => (int) array_sum($stats->all()),
            ],
        ]);
    }

    /**
     * Update quotation details (admin fills pricing)
     */
    public function update(Request $request, int $id)
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => 'ไม่พบใบเสนอราคา'], 404);
        }

        if (in_array($quotation->status, ['accepted', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถแก้ไขใบเสนอราคาในสถานะนี้ได้',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.description' => 'required_with:items|string|max:500',
            'items.*.qty' => 'required_with:items|numeric|min:0',
            'items.*.unit_price' => 'required_with:items|numeric',
            'items.*.amount' => 'nullable|numeric',
            'discount' => 'nullable|numeric|min:0',
            'valid_until' => 'nullable|date',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $items = $request->input('items', []);
        $subtotal = 0;
        foreach ($items as &$item) {
            $amount = isset($item['amount'])
                ? (float) $item['amount']
                : (float) $item['qty'] * (float) $item['unit_price'];
            $item['amount'] = round($amount, 2);
            $subtotal += $amount;
        }
        unset($item);

        $discount = (float) $request->input('discount', 0);
        $totalAmount = max(0, $subtotal - $discount);

        $quotation->fill([
            'title' => $request->input('title', $quotation->title),
            'description' => $request->input('description'),
            'items' => $items ?: null,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total_amount' => $totalAmount,
            'valid_until' => $request->input('valid_until'),
            'admin_notes' => $request->input('admin_notes'),
            'handled_by_user_id' => $request->user()->id,
        ]);

        if ($quotation->status === 'requested') {
            $quotation->status = 'draft';
        }

        $quotation->save();

        return response()->json([
            'success' => true,
            'message' => 'บันทึกเรียบร้อย',
            'data' => $quotation,
        ]);
    }

    /**
     * Mark as sent (lock and visible to customer)
     */
    public function send(Request $request, int $id)
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => 'ไม่พบใบเสนอราคา'], 404);
        }

        if (!in_array($quotation->status, ['requested', 'draft'])) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถส่งใบเสนอราคาในสถานะนี้ได้',
            ], 400);
        }

        if ((float) $quotation->total_amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'กรุณากรอกรายการและราคาก่อนส่ง',
            ], 400);
        }

        $quotation->status = 'sent';
        $quotation->sent_at = now();
        $quotation->handled_by_user_id = $request->user()->id;
        $quotation->save();

        return response()->json([
            'success' => true,
            'message' => 'ส่งใบเสนอราคาให้ลูกค้าเรียบร้อย',
            'data' => $quotation,
        ]);
    }

    /**
     * Cancel quotation
     */
    public function cancel(Request $request, int $id)
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => 'ไม่พบใบเสนอราคา'], 404);
        }

        if (in_array($quotation->status, ['accepted', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถยกเลิกในสถานะนี้',
            ], 400);
        }

        $quotation->status = 'cancelled';
        $quotation->save();

        return response()->json([
            'success' => true,
            'message' => 'ยกเลิกใบเสนอราคาเรียบร้อย',
            'data' => $quotation,
        ]);
    }
}
