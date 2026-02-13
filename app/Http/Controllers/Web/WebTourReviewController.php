<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TourReview;
use App\Models\ReviewTag;
use App\Models\ReviewImage;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebTourReviewController extends Controller
{
    /**
     * Get approved reviews for a tour (public)
     */
    public function index(Request $request, $tourSlug)
    {
        $tour = Tour::where('slug', $tourSlug)->firstOrFail();

        $query = TourReview::where('tour_id', $tour->id)
            ->approved()
            ->with(['user:id,first_name,last_name,avatar', 'images']);

        // Sort
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'highest':
                $query->orderByDesc('rating');
                break;
            case 'lowest':
                $query->orderBy('rating');
                break;
            case 'helpful':
                $query->orderByDesc('helpful_count');
                break;
            case 'featured':
                $query->orderByDesc('is_featured')->orderByDesc('created_at');
                break;
            default: // latest
                $query->orderByDesc('created_at');
        }

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->paginate($request->get('per_page', 10));

        // Get summary stats
        $summary = TourReview::getTourSummary($tour->id);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'reviews' => $reviews,
            ],
        ]);
    }

    /**
     * Get available review tags
     */
    public function tags()
    {
        $tags = ReviewTag::active()->ordered()->get(['id', 'name', 'slug', 'icon']);

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    /**
     * Get featured reviews for homepage (public)
     */
    public function featured(Request $request)
    {
        $limit = min((int) $request->get('limit', 10), 20);

        $reviews = TourReview::approved()
            ->where('is_featured', true)
            ->with(['tour:id,title,slug,tour_code,cover_image_url', 'images'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        // If not enough featured, fill with latest approved high-rating reviews
        if ($reviews->count() < $limit) {
            $remaining = $limit - $reviews->count();
            $excludeIds = $reviews->pluck('id')->toArray();

            $extraReviews = TourReview::approved()
                ->whereNotIn('id', $excludeIds)
                ->where('rating', '>=', 4)
                ->with(['tour:id,title,slug,tour_code,cover_image_url', 'images'])
                ->orderByDesc('rating')
                ->orderByDesc('created_at')
                ->limit($remaining)
                ->get();

            $reviews = $reviews->concat($extraReviews);
        }

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }

    /**
     * Submit a review (authenticated member)
     */
    public function store(Request $request, $tourSlug)
    {
        $tour = Tour::where('slug', $tourSlug)->firstOrFail();
        $member = $request->user();

        // Check if member already reviewed this tour
        $existingReview = TourReview::where('tour_id', $tour->id)
            ->where('user_id', $member->id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'คุณได้รีวิวทัวร์นี้ไปแล้ว',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
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
            'reviewer_avatar' => 'nullable|image|max:2048',
            'images' => 'required|array|min:1|max:6',
            'images.*' => 'image|max:5120',
        ], [
            'rating.required' => 'กรุณาให้คะแนนรีวิว',
            'images.required' => 'กรุณาอัปโหลดรูปภาพอย่างน้อย 1 รูป',
            'images.min' => 'กรุณาอัปโหลดรูปภาพอย่างน้อย 1 รูป',
            'images.max' => 'อัปโหลดภาพได้สูงสุด 6 ภาพ',
            'images.*.image' => 'ไฟล์ต้องเป็นรูปภาพเท่านั้น',
            'images.*.max' => 'ขนาดรูปภาพต้องไม่เกิน 5MB',
            'rating.min' => 'คะแนนต้องอย่างน้อย 1 ดาว',
            'rating.max' => 'คะแนนสูงสุด 5 ดาว',
            'comment.required' => 'กรุณาเขียนความคิดเห็น',
            'comment.max' => 'ความคิดเห็นไม่เกิน 200 ตัวอักษร',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Upload reviewer avatar to R2
        $avatarUrl = $member->avatar;
        if ($request->hasFile('reviewer_avatar')) {
            $disk = Storage::disk('r2');
            $file = $request->file('reviewer_avatar');
            $path = 'review-avatars/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $disk->put($path, file_get_contents($file->getRealPath()), 'public');
            $avatarUrl = rtrim(env('R2_URL'), '/') . '/' . $path;
        }

        $review = TourReview::create([
            'tour_id' => $tour->id,
            'user_id' => $member->id,
            'reviewer_name' => $member->first_name . ' ' . $member->last_name,
            'reviewer_avatar_url' => $avatarUrl,
            'rating' => $request->rating,
            'category_ratings' => $request->category_ratings,
            'tags' => $request->tags,
            'comment' => $request->comment,
            'review_source' => 'self',
            'status' => 'pending', // Needs approval
        ]);

        // Upload review images to R2
        if ($request->hasFile('images')) {
            $disk = Storage::disk('r2');
            $r2Url = rtrim(env('R2_URL'), '/');
            foreach ($request->file('images') as $index => $imageFile) {
                $path = 'review-images/' . Str::uuid() . '.' . $imageFile->getClientOriginalExtension();
                $disk->put($path, file_get_contents($imageFile->getRealPath()), 'public');
                ReviewImage::create([
                    'tour_review_id' => $review->id,
                    'image_url' => $r2Url . '/' . $path,
                    'sort_order' => $index,
                ]);
            }
        }

        $review->load('images');

        // Increment tag usage counts
        if ($request->tags && is_array($request->tags)) {
            foreach ($request->tags as $tagSlug) {
                ReviewTag::where('slug', $tagSlug)->increment('usage_count');
            }
        }

        Log::info('Tour review submitted', [
            'review_id' => $review->id,
            'tour_id' => $tour->id,
            'member_id' => $member->id,
            'rating' => $request->rating,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ส่งรีวิวสำเร็จ จะแสดงผลหลังจากตรวจสอบ',
            'data' => $review,
        ], 201);
    }

    /**
     * Get member's own reviews
     */
    public function myReviews(Request $request)
    {
        $member = $request->user();

        $reviews = TourReview::where('user_id', $member->id)
            ->with(['tour:id,title,slug,cover_image_url', 'images'])
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }

    /**
     * Check if member can review a tour
     */
    public function canReview(Request $request, $tourSlug)
    {
        $tour = Tour::where('slug', $tourSlug)->firstOrFail();
        $member = $request->user();

        $existingReview = TourReview::where('tour_id', $tour->id)
            ->where('user_id', $member->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'can_review' => !$existingReview,
                'existing_review' => $existingReview,
            ],
        ]);
    }

    /**
     * Mark a review as helpful
     */
    public function markHelpful($reviewId)
    {
        $review = TourReview::where('id', $reviewId)->approved()->firstOrFail();
        $review->increment('helpful_count');

        return response()->json([
            'success' => true,
            'data' => ['helpful_count' => $review->fresh()->helpful_count],
        ]);
    }

    /**
     * Get review summary for tour detail page (public, for SEO Schema)
     */
    public function summary($tourSlug)
    {
        $tour = Tour::where('slug', $tourSlug)->firstOrFail();
        $summary = TourReview::getTourSummary($tour->id);

        // Featured reviews for Schema.org
        $featuredReviews = TourReview::where('tour_id', $tour->id)
            ->approved()
            ->featured()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'reviewer_name', 'rating', 'comment', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'featured_reviews' => $featuredReviews,
                'schema' => $this->buildSchemaOrg($tour, $summary, $featuredReviews),
            ],
        ]);
    }

    /**
     * Build Schema.org Review JSON-LD
     */
    private function buildSchemaOrg($tour, $summary, $featuredReviews): ?array
    {
        if ($summary['total_reviews'] === 0) return null;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $tour->title,
            'description' => $tour->description ?? $tour->title,
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => $summary['average_rating'],
                'reviewCount' => $summary['total_reviews'],
                'bestRating' => 5,
                'worstRating' => 1,
            ],
        ];

        if ($featuredReviews->isNotEmpty()) {
            $schema['review'] = $featuredReviews->map(function ($r) {
                return [
                    '@type' => 'Review',
                    'author' => [
                        '@type' => 'Person',
                        'name' => $r->reviewer_name,
                    ],
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => $r->rating,
                        'bestRating' => 5,
                        'worstRating' => 1,
                    ],
                    'reviewBody' => $r->comment,
                    'datePublished' => $r->created_at->toIso8601String(),
                ];
            })->toArray();
        }

        return $schema;
    }
}
