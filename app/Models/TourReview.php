<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'user_id',
        'order_id',
        'reviewer_name',
        'reviewer_avatar_url',
        'rating',
        'category_ratings',
        'tags',
        'comment',
        'review_source',
        'approved_by_customer',
        'approval_screenshot_url',
        'assisted_by_admin_id',
        'status',
        'moderated_by',
        'moderated_at',
        'rejection_reason',
        'admin_reply',
        'replied_by',
        'replied_at',
        'incentive_type',
        'incentive_value',
        'incentive_claimed',
        'is_featured',
        'helpful_count',
        'sort_order',
    ];

    protected $casts = [
        'category_ratings' => 'array',
        'tags' => 'array',
        'approved_by_customer' => 'boolean',
        'incentive_claimed' => 'boolean',
        'is_featured' => 'boolean',
        'moderated_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    // ==================== Relationships ====================

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(WebMember::class, 'user_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function assistedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assisted_by_admin_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class, 'tour_review_id')->orderBy('sort_order');
    }

    // ==================== Scopes ====================

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('review_source', $source);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ==================== Category Labels ====================

    public const CATEGORY_LABELS = [
        'guide' => 'ไกด์',
        'food' => 'อาหาร',
        'hotel' => 'ที่พัก',
        'value' => 'ความคุ้มค่า',
        'program_accuracy' => 'โปรแกรมตรงปก',
        'would_return' => 'อยากกลับไปอีก',
    ];

    // ==================== Helpers ====================

    public function getCategoryAverage(): ?float
    {
        if (!$this->category_ratings) return null;
        $ratings = array_values($this->category_ratings);
        return count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : null;
    }

    /**
     * Get review summary stats for a tour
     */
    public static function getTourSummary(int $tourId): array
    {
        $reviews = self::where('tour_id', $tourId)->approved()->get();

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

        // Category averages
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
}
