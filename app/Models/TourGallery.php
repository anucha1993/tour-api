<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourGallery extends Model
{
    public $timestamps = false;

    protected $table = 'tour_gallery';

    protected $fillable = [
        'tour_id',
        'url',
        'thumbnail_url',
        'alt',
        'caption',
        'width',
        'height',
        'sort_order',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
