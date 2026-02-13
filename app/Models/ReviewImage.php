<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_review_id',
        'image_url',
        'thumbnail_url',
        'sort_order',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(TourReview::class, 'tour_review_id');
    }
}
