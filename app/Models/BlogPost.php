<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPost extends Model
{
    protected $fillable = [
        'category_id',
        'country_ids',
        'city_ids',
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image_url',
        'cover_image_cf_id',
        'author_name',
        'author_avatar_url',
        'status',
        'is_featured',
        'published_at',
        'view_count',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'tags',
        'reading_time_min',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'reading_time_min' => 'integer',
        'tags' => 'array',
        'country_ids' => 'array',
        'city_ids' => 'array',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
