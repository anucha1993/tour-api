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
     * Includes reviews from other tours that share matching hashtags/tags
     */
    public function index(Request $request, $tourSlug)
    {
        $tour = Tour::where('slug', $tourSlug)->firstOrFail();

        // Get tour's hashtags for cross-tour matching
        $hashtags = $tour->hashtags;
        if (is_string($hashtags)) $hashtags = json_decode($hashtags, true);
        if (!is_array($hashtags)) $hashtags = [];

        // Also include city names and country name
        $tour->loadMissing(['cities:id,name_th', 'primaryCountry:id,name_th']);
        $cityNames = $tour->cities->pluck('name_th')->filter()->values()->toArray();
        $countryName = $tour->primaryCountry?->name_th;
        $allTags = array_values(array_unique(array_filter(
            array_merge($hashtags, $cityNames, $countryName ? [$countryName] : [])
        )));

        // Query: reviews from this tour OR reviews with matching tags
        $query = TourReview::approved()
            ->with(['user:id,first_name,last_name,avatar', 'images', 'tour:id,title,slug,tour_code'])
            ->where(function ($q) use ($tour, $allTags) {
                // Reviews belonging to this tour
                $q->where('tour_id', $tour->id);

                // OR reviews from other tours that have matching tags
                if (!empty($allTags)) {
                    $q->orWhere(function ($sub) use ($allTags) {
                        foreach ($allTags as $tag) {
                            $sub->orWhereRaw("JSON_SEARCH(tags, 'one', ?) IS NOT NULL", [$tag]);
                        }
                    });
                }
            });

        // Sort - prioritize same-tour reviews first
        $sort = $request->get('sort', 'latest');
        $query->orderByRaw("CASE WHEN tour_id = ? THEN 0 ELSE 1 END", [$tour->id]);
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

        // Get summary stats (combined: this tour + matched reviews)
        $summary = $this->getCombinedSummary($tour->id, $allTags);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'reviews' => $reviews,
            ],
        ]);
    }

    /**
     * Get combined review summary for a tour + hashtag-matched reviews
     */
    private function getCombinedSummary(int $tourId, array $allTags): array
    {
        $query = TourReview::approved()
            ->where(function ($q) use ($tourId, $allTags) {
                $q->where('tour_id', $tourId);
                if (!empty($allTags)) {
                    $q->orWhere(function ($sub) use ($allTags) {
                        foreach ($allTags as $tag) {
                            $sub->orWhereRaw("JSON_SEARCH(tags, 'one', ?) IS NOT NULL", [$tag]);
                        }
                    });
                }
            });

        $reviews = $query->get();

        if ($reviews->isEmpty()) {
            return [
                'average_rating' => 0,
                'total_reviews' => 0,
                'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                'category_averages' => [],
            ];
        }

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($reviews as $r) {
            if (isset($distribution[$r->rating])) {
                $distribution[$r->rating]++;
            }
        }

        $categoryTotals = [];
        $categoryCounts = [];
        foreach ($reviews as $r) {
            if ($r->category_ratings) {
                foreach ($r->category_ratings as $cat => $val) {
                    if (!isset($categoryTotals[$cat])) {
                        $categoryTotals[$cat] = 0;
                        $categoryCounts[$cat] = 0;
                    }
                    $categoryTotals[$cat] += $val;
                    $categoryCounts[$cat]++;
                }
            }
        }
        $categoryAverages = [];
        foreach ($categoryTotals as $cat => $total) {
            $categoryAverages[$cat] = round($total / $categoryCounts[$cat], 1);
        }

        return [
            'average_rating' => round($reviews->avg('rating'), 1),
            'total_reviews' => $reviews->count(),
            'rating_distribution' => $distribution,
            'category_averages' => $categoryAverages,
        ];
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
            'tour_type' => 'nullable|in:individual,private,corporate',
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
            'tour_type' => $request->input('tour_type', 'individual'),
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

        // ให้คะแนนสำหรับการรีวิว
        try {
            app(\App\Services\PointService::class)->earnPoints(
                $member, 'review', 0,
                TourReview::class, $review->id,
                "รีวิวทัวร์: {$tour->title}"
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to earn review points', ['error' => $e->getMessage()]);
        }

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

        // Get combined tags for cross-tour matching
        $hashtags = $tour->hashtags;
        if (is_string($hashtags)) $hashtags = json_decode($hashtags, true);
        if (!is_array($hashtags)) $hashtags = [];

        $tour->loadMissing(['cities:id,name_th', 'primaryCountry:id,name_th']);
        $cityNames = $tour->cities->pluck('name_th')->filter()->values()->toArray();
        $countryName = $tour->primaryCountry?->name_th;
        $allTags = array_values(array_unique(array_filter(
            array_merge($hashtags, $cityNames, $countryName ? [$countryName] : [])
        )));

        $summary = $this->getCombinedSummary($tour->id, $allTags);

        // Featured reviews for Schema.org (same tour + tag-matched)
        $featuredReviews = TourReview::approved()
            ->featured()
            ->where(function ($q) use ($tour, $allTags) {
                $q->where('tour_id', $tour->id);
                if (!empty($allTags)) {
                    $q->orWhere(function ($sub) use ($allTags) {
                        foreach ($allTags as $tag) {
                            $sub->orWhereRaw("JSON_SEARCH(tags, 'one', ?) IS NOT NULL", [$tag]);
                        }
                    });
                }
            })
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
