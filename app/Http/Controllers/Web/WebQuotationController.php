<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WebQuotationController extends Controller
{
    /**
     * List my quotations
     */
    public function index(Request $request)
    {
        $member = $request->user();

        $query = Quotation::with(['tour:id,title,slug,cover_image_url'])
            ->where('web_member_id', $member->id)
            ->orderBy('created_at', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->query('per_page', 15), 50);
        $list = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $list,
        ]);
    }

    /**
     * Show single quotation
     */
    public function show(Request $request, int $id)
    {
        $member = $request->user();

        $quotation = Quotation::with(['tour:id,title,slug,cover_image_url'])
            ->where('web_member_id', $member->id)
            ->find($id);

        if (!$quotation) {
            return response()->json(['success' => false, 'message' => 'ไม่พบใบเสนอราคา'], 404);
        }

        // Auto-mark expired
        if ($quotation->status === 'sent' && $quotation->valid_until && $quotation->valid_until->isPast()) {
            $quotation->status = 'expired';
            $quotation->save();
        }

        return response()->json([
            'success' => true,
            'data' => $quotation,
        ]);
    }

    /**
     * Customer requests a quotation
     */
    public function store(Request $request)
    {
        $member = $request->user();

        $validator = Validator::make($request->all(), [
            'tour_id' => 'nullable|exists:tours,id',
            'period_id' => 'nullable|integer',
            'customer_name' => 'required|string|max:200',
            'customer_phone' => 'required|string|max:50',
            'customer_email' => 'nullable|email|max:150',
            'pax_adult' => 'required|integer|min:1|max:100',
            'pax_child' => 'nullable|integer|min:0|max:100',
            'pax_infant' => 'nullable|integer|min:0|max:100',
            'travel_date_preference' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $title = null;
        if ($request->tour_id) {
            $tour = Tour::find($request->tour_id);
            $title = $tour?->title;
        }

        $quotation = Quotation::create([
            'quotation_number' => Quotation::generateNumber(),
            'web_member_id' => $member->id,
            'tour_id' => $request->tour_id,
            'period_id' => $request->period_id,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'customer_email' => $request->customer_email,
            'pax_adult' => $request->pax_adult,
            'pax_child' => $request->pax_child ?? 0,
            'pax_infant' => $request->pax_infant ?? 0,
            'travel_date_preference' => $request->travel_date_preference,
            'notes' => $request->notes,
            'title' => $title,
            'status' => 'requested',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ส่งคำขอใบเสนอราคาเรียบร้อย เจ้าหน้าที่จะติดต่อกลับภายใน 24 ชั่วโมง',
            'data' => $quotation,
        ], 201);
    }

    /**
     * Customer accepts the quotation
     */
    public function accept(Request $request, int $id)
    {
        $member = $request->user();

        $quotation = Quotation::where('web_member_id', $member->id)->find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => 'ไม่พบใบเสนอราคา'], 404);
        }

        if ($quotation->status !== 'sent') {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถยอมรับใบเสนอราคาในสถานะนี้ได้',
            ], 400);
        }

        if ($quotation->valid_until && $quotation->valid_until->isPast()) {
            $quotation->status = 'expired';
            $quotation->save();
            return response()->json([
                'success' => false,
                'message' => 'ใบเสนอราคาหมดอายุแล้ว',
            ], 400);
        }

        $quotation->status = 'accepted';
        $quotation->accepted_at = now();
        $quotation->save();

        return response()->json([
            'success' => true,
            'message' => 'ยอมรับใบเสนอราคาแล้ว เจ้าหน้าที่จะติดต่อกลับเพื่อดำเนินการจอง',
            'data' => $quotation,
        ]);
    }

    /**
     * Customer declines the quotation
     */
    public function decline(Request $request, int $id)
    {
        $member = $request->user();

        $quotation = Quotation::where('web_member_id', $member->id)->find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => 'ไม่พบใบเสนอราคา'], 404);
        }

        if (!in_array($quotation->status, ['sent', 'requested', 'draft'])) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถปฏิเสธใบเสนอราคาในสถานะนี้ได้',
            ], 400);
        }

        $quotation->status = 'declined';
        $quotation->declined_at = now();
        $quotation->decline_reason = $request->input('reason', '');
        $quotation->save();

        return response()->json([
            'success' => true,
            'message' => 'ปฏิเสธใบเสนอราคาแล้ว',
            'data' => $quotation,
        ]);
    }
}
