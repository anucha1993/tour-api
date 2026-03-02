<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingEmailService;
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

        // Track who cancelled
        if ($request->status === 'cancelled' && $oldStatus !== 'cancelled') {
            $booking->cancelled_by = 'admin';
        } elseif ($request->status !== 'cancelled') {
            $booking->cancelled_by = null;
        }

        if ($request->filled('admin_note')) {
            $booking->admin_note = $request->admin_note;
        }

        $booking->save();

        // Send status update email to customer
        if ($oldStatus !== $request->status) {
            try {
                BookingEmailService::sendStatusUpdate($booking);
            } catch (\Exception $e) {
                // Don't fail the status update if email fails
            }
        }

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

    /**
     * Create new booking (manual - admin)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tour_id' => 'required|exists:tours,id',
            'period_id' => 'required|exists:tour_periods,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'required|string|max:20',
            'qty_adult' => 'required|integer|min:1',
            'qty_adult_single' => 'integer|min:0',
            'qty_child_bed' => 'integer|min:0',
            'qty_child_nobed' => 'integer|min:0',
            'qty_infant' => 'integer|min:0',
            'qty_triple' => 'integer|min:0',
            'qty_twin' => 'integer|min:0',
            'qty_double' => 'integer|min:0',
            'price_adult' => 'required|numeric|min:0',
            'price_single' => 'numeric|min:0',
            'price_child_bed' => 'numeric|min:0',
            'price_child_nobed' => 'numeric|min:0',
            'price_infant' => 'numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'sale_code' => 'nullable|string|max:50',
            'special_request' => 'nullable|string|max:1000',
            'admin_note' => 'nullable|string|max:1000',
            'status' => 'in:pending,confirmed,paid,cancelled,completed',
        ]);

        $booking = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'tour_id' => $validated['tour_id'],
            'period_id' => $validated['period_id'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'qty_adult' => $validated['qty_adult'],
            'qty_adult_single' => $validated['qty_adult_single'] ?? 0,
            'qty_child_bed' => $validated['qty_child_bed'] ?? 0,
            'qty_child_nobed' => $validated['qty_child_nobed'] ?? 0,
            'qty_infant' => $validated['qty_infant'] ?? 0,
            'qty_triple' => $validated['qty_triple'] ?? 0,
            'qty_twin' => $validated['qty_twin'] ?? 0,
            'qty_double' => $validated['qty_double'] ?? 0,
            'price_adult' => $validated['price_adult'],
            'price_single' => $validated['price_single'] ?? 0,
            'price_child_bed' => $validated['price_child_bed'] ?? 0,
            'price_child_nobed' => $validated['price_child_nobed'] ?? 0,
            'price_infant' => $validated['price_infant'] ?? 0,
            'total_amount' => $validated['total_amount'],
            'sale_code' => $validated['sale_code'] ?? null,
            'special_request' => $validated['special_request'] ?? null,
            'admin_note' => $validated['admin_note'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'source' => 'manual', // Admin manual creation
        ]);

        return response()->json([
            'success' => true,
            'message' => 'สร้างใบจองสำเร็จ',
            'booking' => $booking->fresh(['tour', 'period']),
        ], 201);
    }

    /**
     * Update booking (admin)
     */
    public function update(Request $request, int $id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['message' => 'ไม่พบข้อมูลการจอง'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
            'email' => 'email|max:100',
            'phone' => 'string|max:20',
            'qty_adult' => 'integer|min:1',
            'qty_adult_single' => 'integer|min:0',
            'qty_child_bed' => 'integer|min:0',
            'qty_child_nobed' => 'integer|min:0',
            'qty_infant' => 'integer|min:0',
            'qty_triple' => 'integer|min:0',
            'qty_twin' => 'integer|min:0',
            'qty_double' => 'integer|min:0',
            'price_adult' => 'numeric|min:0',
            'price_single' => 'numeric|min:0',
            'price_child_bed' => 'numeric|min:0',
            'price_child_nobed' => 'numeric|min:0',
            'price_infant' => 'numeric|min:0',
            'total_amount' => 'numeric|min:0',
            'sale_code' => 'nullable|string|max:50',
            'special_request' => 'nullable|string|max:1000',
            'admin_note' => 'nullable|string|max:1000',
            'status' => 'in:pending,confirmed,paid,cancelled,completed',
            'period_id' => 'exists:tour_periods,id',
        ]);

        // Handle flash sale cancellation
        $oldStatus = $booking->status;
        
        // Track who cancelled
        if (isset($validated['status'])) {
            if ($validated['status'] === 'cancelled' && $oldStatus !== 'cancelled') {
                $validated['cancelled_by'] = 'admin';
            } elseif ($validated['status'] !== 'cancelled') {
                $validated['cancelled_by'] = null;
            }
        }

        $booking->fill($validated);
        $booking->save();

        // Send status update email if status changed
        if (isset($validated['status']) && $oldStatus !== $validated['status']) {
            try {
                BookingEmailService::sendStatusUpdate($booking);
            } catch (\Exception $e) {
                // Don't fail the update if email fails
            }
        }

        // If cancelled and was flash sale → decrement quantity_sold
        if (isset($validated['status']) && $validated['status'] === 'cancelled' && $oldStatus !== 'cancelled' && $booking->flash_sale_item_id) {
            $booking->flashSaleItem?->decrement('quantity_sold');
        }

        return response()->json([
            'success' => true,
            'message' => 'แก้ไขใบจองสำเร็จ',
            'booking' => $booking->fresh(['member', 'tour', 'period', 'flashSaleItem']),
        ]);
    }
}
