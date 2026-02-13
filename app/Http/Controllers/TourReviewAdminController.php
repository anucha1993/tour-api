<?php

namespace App\Http\Controllers;

use App\Models\TourReview;
use App\Models\ReviewTag;
use App\Models\ReviewImage;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'tour:id,title,slug,tour_code',
            'user:id,first_name,last_name,avatar',
            'moderator:id,name',
            'images',
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
                      $tq->where('title', 'like', "%{$search}%");
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
            'tour:id,title,slug,cover_image_url',
            'user:id,first_name,last_name,email,phone,avatar',
            'moderator:id,name',
            'replier:id,name',
            'assistedByAdmin:id,name',
            'images',
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
            'reviewer_avatar' => 'nullable|image|max:2048',
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
            'review_source' => 'nullable|in:self,assisted,internal',
            'status' => 'nullable|in:pending,approved,rejected',
            'images' => 'nullable|array|max:6',
            'images.*' => 'image|max:5120',
        ], [
            'tour_id.required' => 'กรุณาเลือกทัวร์',
            'images.max' => 'อัปโหลดภาพได้สูงสุด 6 ภาพ',
            'images.*.image' => 'ไฟล์ต้องเป็นรูปภาพเท่านั้น',
            'images.*.max' => 'ขนาดรูปภาพต้องไม่เกิน 5MB',
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

        // Upload reviewer avatar to R2
        $avatarUrl = null;
        if ($request->hasFile('reviewer_avatar')) {
            $disk = Storage::disk('r2');
            $file = $request->file('reviewer_avatar');
            $path = 'review-avatars/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $disk->put($path, file_get_contents($file->getRealPath()), 'public');
            $avatarUrl = rtrim(env('R2_URL'), '/') . '/' . $path;
        }

        // Upload approval screenshot to R2
        $screenshotUrl = null;
        if ($request->hasFile('approval_screenshot')) {
            $disk = Storage::disk('r2');
            $file = $request->file('approval_screenshot');
            $path = 'review-screenshots/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $disk->put($path, file_get_contents($file->getRealPath()), 'public');
            $screenshotUrl = rtrim(env('R2_URL'), '/') . '/' . $path;
        }

        $review = TourReview::create([
            'tour_id' => $request->tour_id,
            'reviewer_name' => $request->reviewer_name,
            'reviewer_avatar_url' => $avatarUrl,
            'rating' => $request->rating,
            'category_ratings' => $request->category_ratings,
            'tags' => $request->tags,
            'comment' => $request->comment,
            'review_source' => $request->input('review_source', 'assisted'),
            'approved_by_customer' => $request->approved_by_customer,
            'approval_screenshot_url' => $screenshotUrl,
            'assisted_by_admin_id' => $admin->id,
            'status' => $request->input('status', 'approved'),
        ]);

        // Upload review images to R2
        if ($request->hasFile('images')) {
            $disk = Storage::disk('r2');
            foreach ($request->file('images') as $index => $imageFile) {
                $path = 'review-images/' . Str::uuid() . '.' . $imageFile->getClientOriginalExtension();
                $disk->put($path, file_get_contents($imageFile->getRealPath()), 'public');
                $imageUrl = rtrim(env('R2_URL'), '/') . '/' . $path;
                ReviewImage::create([
                    'tour_review_id' => $review->id,
                    'image_url' => $imageUrl,
                    'sort_order' => $index,
                ]);
            }
        }

        $review->load('images');

        // Increment tag usage
        if ($request->tags && is_array($request->tags)) {
            foreach ($request->tags as $tagName) {
                ReviewTag::where('name', $tagName)
                    ->orWhere('slug', $tagName)
                    ->increment('usage_count');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'สร้างรีวิว Assisted สำเร็จ',
            'data' => $review,
        ], 201);
    }

    /**
     * Delete a review and its R2 files
     */
    public function destroy($id)
    {
        $review = TourReview::with('images')->findOrFail($id);
        $disk = Storage::disk('r2');
        $r2Url = rtrim(env('R2_URL'), '/');

        // Delete review images from R2
        foreach ($review->images as $image) {
            if ($image->image_url && str_starts_with($image->image_url, $r2Url)) {
                $r2Path = str_replace($r2Url . '/', '', $image->image_url);
                $disk->delete($r2Path);
            }
            $image->delete();
        }

        // Delete reviewer avatar from R2
        if ($review->reviewer_avatar_url && str_starts_with($review->reviewer_avatar_url, $r2Url)) {
            $r2Path = str_replace($r2Url . '/', '', $review->reviewer_avatar_url);
            $disk->delete($r2Path);
        }

        // Delete approval screenshot from R2
        if ($review->approval_screenshot_url && str_starts_with($review->approval_screenshot_url, $r2Url)) {
            $r2Path = str_replace($r2Url . '/', '', $review->approval_screenshot_url);
            $disk->delete($r2Path);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบรีวิวสำเร็จ',
        ]);
    }

    /**
     * Update a review (admin edit)
     */
    public function update(Request $request, $id)
    {
        $review = TourReview::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'reviewer_name' => 'required|string|max:100',
            'reviewer_avatar' => 'nullable|image|max:2048',
            'rating' => 'required|integer|min:1|max:5',
            'category_ratings' => 'nullable|array',
            'category_ratings.guide' => 'nullable|integer|min:1|max:5',
            'category_ratings.food' => 'nullable|integer|min:1|max:5',
            'category_ratings.hotel' => 'nullable|integer|min:1|max:5',
            'category_ratings.value' => 'nullable|integer|min:1|max:5',
            'category_ratings.program_accuracy' => 'nullable|integer|min:1|max:5',
            'category_ratings.would_return' => 'nullable|integer|min:1|max:5',
            'comment' => 'required|string|max:200',
            'review_source' => 'nullable|in:self,assisted,internal',
            'status' => 'nullable|in:pending,approved,rejected',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'images' => 'nullable|array|max:6',
            'images.*' => 'image|max:5120',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer',
            'remove_avatar' => 'nullable|boolean',
        ], [
            'reviewer_name.required' => 'กรุณาระบุชื่อผู้รีวิว',
            'rating.required' => 'กรุณาให้คะแนน',
            'comment.required' => 'กรุณาเขียนความคิดเห็น',
            'images.max' => 'อัปโหลดภาพได้สูงสุด 6 ภาพ',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $disk = Storage::disk('r2');
        $r2Url = rtrim(env('R2_URL'), '/');

        // Upload reviewer avatar to R2
        if ($request->hasFile('reviewer_avatar')) {
            // Delete old avatar from R2
            if ($review->reviewer_avatar_url && str_starts_with($review->reviewer_avatar_url, $r2Url)) {
                $oldPath = str_replace($r2Url . '/', '', $review->reviewer_avatar_url);
                $disk->delete($oldPath);
            }
            $file = $request->file('reviewer_avatar');
            $path = 'review-avatars/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $disk->put($path, file_get_contents($file->getRealPath()), 'public');
            $review->reviewer_avatar_url = $r2Url . '/' . $path;
        } elseif ($request->boolean('remove_avatar')) {
            // Delete old avatar from R2
            if ($review->reviewer_avatar_url && str_starts_with($review->reviewer_avatar_url, $r2Url)) {
                $oldPath = str_replace($r2Url . '/', '', $review->reviewer_avatar_url);
                $disk->delete($oldPath);
            }
            $review->reviewer_avatar_url = null;
        }

        $review->reviewer_name = $request->reviewer_name;
        $review->rating = $request->rating;
        $review->category_ratings = $request->category_ratings;
        $review->tags = $request->tags;
        $review->comment = $request->comment;
        if ($request->filled('review_source')) {
            $review->review_source = $request->review_source;
        }
        if ($request->filled('status')) {
            $review->status = $request->status;
        }
        $review->save();

        // Remove specified images (delete from R2)
        if ($request->filled('remove_image_ids')) {
            $imagesToRemove = ReviewImage::where('tour_review_id', $review->id)
                ->whereIn('id', $request->remove_image_ids)
                ->get();
            foreach ($imagesToRemove as $img) {
                if ($img->image_url && str_starts_with($img->image_url, $r2Url)) {
                    $r2Path = str_replace($r2Url . '/', '', $img->image_url);
                    $disk->delete($r2Path);
                }
                $img->delete();
            }
        }

        // Upload new images to R2
        if ($request->hasFile('images')) {
            $currentCount = $review->images()->count();
            foreach ($request->file('images') as $index => $imageFile) {
                $path = 'review-images/' . Str::uuid() . '.' . $imageFile->getClientOriginalExtension();
                $disk->put($path, file_get_contents($imageFile->getRealPath()), 'public');
                $imageUrl = $r2Url . '/' . $path;
                ReviewImage::create([
                    'tour_review_id' => $review->id,
                    'image_url' => $imageUrl,
                    'sort_order' => $currentCount + $index,
                ]);
            }
        }

        $review->load('images');

        return response()->json([
            'success' => true,
            'message' => 'แก้ไขรีวิวสำเร็จ',
            'data' => $review,
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
