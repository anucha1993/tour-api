<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use Illuminate\Http\Request;

class WebWishlistController extends Controller
{
    /**
     * Get member's wishlist
     */
    public function index(Request $request)
    {
        $member = $request->user();
        
        $wishlists = $member->wishlists()
            ->with(['country', 'featuredImage'])
            ->orderBy('web_member_wishlists.created_at', 'desc')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $wishlists,
        ]);
    }

    /**
     * Add tour to wishlist
     */
    public function store(Request $request)
    {
        $request->validate([
            'tour_id' => 'required|exists:tours,id',
        ]);

        $member = $request->user();
        
        // Check if already in wishlist
        if ($member->wishlists()->where('tour_id', $request->tour_id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'already_exists',
                'message' => 'ทัวร์นี้อยู่ในรายการโปรดแล้ว',
            ], 400);
        }

        $member->wishlists()->attach($request->tour_id);

        return response()->json([
            'success' => true,
            'message' => 'เพิ่มลงรายการโปรดแล้ว',
        ]);
    }

    /**
     * Remove tour from wishlist
     */
    public function destroy(Request $request, int $tourId)
    {
        $member = $request->user();
        
        $detached = $member->wishlists()->detach($tourId);

        if (!$detached) {
            return response()->json([
                'success' => false,
                'error' => 'not_found',
                'message' => 'ไม่พบทัวร์นี้ในรายการโปรด',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'ลบออกจากรายการโปรดแล้ว',
        ]);
    }

    /**
     * Check if tour is in wishlist
     */
    public function check(Request $request, int $tourId)
    {
        $member = $request->user();
        
        $inWishlist = $member->wishlists()->where('tour_id', $tourId)->exists();

        return response()->json([
            'success' => true,
            'in_wishlist' => $inWishlist,
        ]);
    }

    /**
     * Get wishlist count
     */
    public function count(Request $request)
    {
        $member = $request->user();
        
        return response()->json([
            'success' => true,
            'count' => $member->wishlists()->count(),
        ]);
    }

    /**
     * Toggle wishlist (add/remove)
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'tour_id' => 'required|exists:tours,id',
        ]);

        $member = $request->user();
        $tourId = $request->tour_id;
        
        if ($member->wishlists()->where('tour_id', $tourId)->exists()) {
            $member->wishlists()->detach($tourId);
            return response()->json([
                'success' => true,
                'action' => 'removed',
                'in_wishlist' => false,
                'message' => 'ลบออกจากรายการโปรดแล้ว',
            ]);
        }

        $member->wishlists()->attach($tourId);
        return response()->json([
            'success' => true,
            'action' => 'added',
            'in_wishlist' => true,
            'message' => 'เพิ่มลงรายการโปรดแล้ว',
        ]);
    }
}
