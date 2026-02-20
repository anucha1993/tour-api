<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * List all bookings (admin)
     */
    public function index(Request $request)
    {
        $query = Booking::with([
            'member:id,first_name,last_name,email,phone',
            'tour:id,title,slug,tour_code',
            'period:id,start_date,end_date',
            'flashSaleItem:id,flash_price,discount_percent,flash_sale_id',
        ]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by source
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Search by booking_code, name, phone, email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $bookings = $query->paginate($request->get('per_page', 20));

        return response()->json($bookings);
    }

    /**
     * Get single booking detail
     */
    public function show(int $id)
    {
        $booking = Booking::with([
            'member:id,first_name,last_name,email,phone,avatar',
            'tour:id,title,slug,tour_code,duration_days,duration_nights',
            'period:id,start_date,end_date,capacity,booked',
            'flashSaleItem:id,flash_price,original_price,discount_percent,flash_sale_id',
            'flashSaleItem.flashSale:id,title',
        ])->find($id);

        if (!$booking) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        return response()->json($booking);
    }

    /**
     * Update booking status
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,paid,cancelled,completed',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        $oldStatus = $booking->status;
        $booking->status = $request->status;

        if ($request->filled('admin_note')) {
            $booking->admin_note = $request->admin_note;
        }

        $booking->save();

        // If cancelled and was flash sale → decrement quantity_sold
        if ($request->status === 'cancelled' && $oldStatus !== 'cancelled' && $booking->flash_sale_item_id) {
            $booking->flashSaleItem?->decrement('quantity_sold');
        }

        return response()->json([
            'success' => true,
            'message' => 'อัปเดตสถานะการจองสำเร็จ',
            'booking' => $booking->fresh(['member', 'tour', 'period', 'flashSaleItem']),
        ]);
    }

    /**
     * Get booking statistics
     */
    public function statistics()
    {
        return response()->json([
            'total' => Booking::count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'paid' => Booking::where('status', 'paid')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
            'completed' => Booking::where('status', 'completed')->count(),
            'from_flash_sale' => Booking::where('source', 'flash_sale')->count(),
            'from_website' => Booking::where('source', 'website')->count(),
        ]);
    }
}
