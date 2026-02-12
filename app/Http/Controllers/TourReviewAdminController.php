<?php

namespace App\Http\Controllers;

use App\Models\TourReview;
use App\Models\ReviewTag;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TourReviewAdminController extends Controller
{
    /**
     * List all reviews (admin)
     */
    public function index(Request $request)
    {
        $query = TourReview::with([
            'tour:id,tour_name,slug',
            'user:id,first_name,last_name,avatar',
            'moderator:id,name',
        ]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by source
        if ($request->filled('source')) {
            $query->where('review_source', $request->source);
        }

        // Filter by tour
        if ($request->filled('tour_id')) {
            $query->where('tour_id', $request->tour_id);
        }

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter by featured
        if ($request->filled('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reviewer_name', 'like', "%{$search}%")
                  ->orWhere('comment', 'like', "%{$search}%")
                  ->orWhereHas('tour', function ($tq) use ($search) {
                      $tq->where('tour_name', 'like', "%{$search}%");
                  });
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $reviews = $query->paginate($request->get('per_page', 20));

        // Stats
        $stats = [
            'total' => TourReview::count(),
            'pending' => TourReview::pending()->count(),
            'approved' => TourReview::approved()->count(),
            'rejected' => TourReview::where('status', 'rejected')->count(),
            'featured' => TourReview::where('is_featured', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $reviews,
            'stats' => $stats,
        ]);
    }

    /**
     * Show a single review
     */
    public function show($id)
    {
        $review = TourReview::with([
            'tour:id,tour_name,slug,cover_image',
            'user:id,first_name,last_name,email,phone,avatar',
            'moderator:id,name',
            'replier:id,name',
            'assistedByAdmin:id,name',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $review,
        ]);
    }

    /**
     * Approve a review
     */
    public function approve(Request $request, $id)
    {
        $review = TourReview::findOrFail($id);
        $admin = $request->user();

        $review->update([
            'status' => 'approved',
            'moderated_by' => $admin->id,
            'moderated_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'อนุมัติรีวิวสำเร็จ',
            'data' => $review->fresh(),
        ]);
    }

    /**
     * Reject a review
     */
    public function reject(Request $request, $id)
    {
        $review = TourReview::findOrFail($id);
        $admin = $request->user();

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $review->update([
            'status' => 'rejected',
            'moderated_by' => $admin->id,
            'moderated_at' => now(),
            'rejection_reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ปฏิเสธรีวิวสำเร็จ',
            'data' => $review->fresh(),
        ]);
    }

    /**
     * Admin reply to a review
     */
    public function reply(Request $request, $id)
    {
        $review = TourReview::findOrFail($id);
        $admin = $request->user();

        $validator = Validator::make($request->all(), [
            'admin_reply' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $review->update([
            'admin_reply' => $request->admin_reply,
            'replied_by' => $admin->id,
            'replied_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ตอบกลับรีวิวสำเร็จ',
            'data' => $review->fresh(),
        ]);
    }

    /**
     * Toggle featured
     */
    public function toggleFeatured($id)
    {
        $review = TourReview::findOrFail($id);
        $review->update(['is_featured' => !$review->is_featured]);

        return response()->json([
            'success' => true,
            'message' => $review->is_featured ? 'ตั้งเป็นรีวิวแนะนำ' : 'ยกเลิกรีวิวแนะนำ',
            'data' => $review,
        ]);
    }

    /**
     * Create assisted review (admin creates on behalf of customer)
     */
    public function createAssisted(Request $request)
    {
        $admin = $request->user();

        $validator = Validator::make($request->all(), [
            'tour_id' => 'required|exists:tours,id',
            'reviewer_name' => 'required|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'category_ratings' => 'nullable|array',
            'category_ratings.guide' => 'nullable|integer|min:1|max:5',
            'category_ratings.food' => 'nullable|integer|min:1|max:5',
            'category_ratings.hotel' => 'nullable|integer|min:1|max:5',
            'category_ratings.value' => 'nullable|integer|min:1|max:5',
            'category_ratings.program_accuracy' => 'nullable|integer|min:1|max:5',
            'category_ratings.would_return' => 'nullable|integer|min:1|max:5',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'comment' => 'required|string|max:200',
            'approved_by_customer' => 'required|boolean',
            'approval_screenshot' => 'nullable|image|max:5120',
        ], [
            'tour_id.required' => 'กรุณาเลือกทัวร์',
            'reviewer_name.required' => 'กรุณาระบุชื่อผู้รีวิว',
            'rating.required' => 'กรุณาให้คะแนน',
            'comment.required' => 'กรุณาเขียนความคิดเห็น',
            'approved_by_customer.required' => 'กรุณาระบุว่าลูกค้ายินยอมหรือไม่',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Upload approval screenshot if provided
        $screenshotUrl = null;
        if ($request->hasFile('approval_screenshot')) {
            $screenshotUrl = $request->file('approval_screenshot')
                ->store('review-screenshots', 'public');
            $screenshotUrl = '/storage/' . $screenshotUrl;
        }

        $review = TourReview::create([
            'tour_id' => $request->tour_id,
            'reviewer_name' => $request->reviewer_name,
            'rating' => $request->rating,
            'category_ratings' => $request->category_ratings,
            'tags' => $request->tags,
            'comment' => $request->comment,
            'review_source' => 'assisted',
            'approved_by_customer' => $request->approved_by_customer,
            'approval_screenshot_url' => $screenshotUrl,
            'assisted_by_admin_id' => $admin->id,
            'status' => 'approved', // Assisted reviews auto-approved
        ]);

        // Increment tag usage
        if ($request->tags && is_array($request->tags)) {
            foreach ($request->tags as $tagSlug) {
                ReviewTag::where('slug', $tagSlug)->increment('usage_count');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'สร้างรีวิว Assisted สำเร็จ',
            'data' => $review,
        ], 201);
    }

    /**
     * Delete a review
     */
    public function destroy($id)
    {
        $review = TourReview::findOrFail($id);
        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบรีวิวสำเร็จ',
        ]);
    }

    /**
     * Bulk approve reviews
     */
    public function bulkApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:tour_reviews,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $admin = $request->user();

        TourReview::whereIn('id', $request->ids)->update([
            'status' => 'approved',
            'moderated_by' => $admin->id,
            'moderated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'อนุมัติรีวิว ' . count($request->ids) . ' รายการสำเร็จ',
        ]);
    }

    // ==================== Review Tags Management ====================

    /**
     * List all tags (admin)
     */
    public function tagIndex()
    {
        $tags = ReviewTag::ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    /**
     * Create tag
     */
    public function tagStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:review_tags,name',
            'icon' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tag = ReviewTag::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'icon' => $request->icon,
            'sort_order' => ReviewTag::max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'data' => $tag,
        ], 201);
    }

    /**
     * Update tag
     */
    public function tagUpdate(Request $request, $id)
    {
        $tag = ReviewTag::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:review_tags,name,' . $id,
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tag->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'icon' => $request->icon ?? $tag->icon,
            'is_active' => $request->is_active ?? $tag->is_active,
            'sort_order' => $request->sort_order ?? $tag->sort_order,
        ]);

        return response()->json([
            'success' => true,
            'data' => $tag,
        ]);
    }

    /**
     * Delete tag
     */
    public function tagDestroy($id)
    {
        $tag = ReviewTag::findOrFail($id);
        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบแท็กสำเร็จ',
        ]);
    }

    /**
     * Toggle tag active
     */
    public function tagToggle($id)
    {
        $tag = ReviewTag::findOrFail($id);
        $tag->update(['is_active' => !$tag->is_active]);

        return response()->json([
            'success' => true,
            'data' => $tag,
        ]);
    }

    /**
     * Reorder tags
     */
    public function tagReorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:review_tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->ids as $index => $id) {
            ReviewTag::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json([
            'success' => true,
            'message' => 'จัดเรียงแท็กสำเร็จ',
        ]);
    }
}
