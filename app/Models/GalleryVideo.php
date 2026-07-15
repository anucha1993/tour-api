<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_url',
        'orientation',
        'thumbnail_cloudflare_id',
        'thumbnail_url',
        'title',
        'description',
        'country_id',
        'city_id',
        'tags',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Auto-detect orientation from YouTube URL when not explicitly set.
     *   /shorts/  → portrait (9:16)
     *   otherwise → landscape (16:9)
     */
    protected static function booted(): void
    {
        static::saving(function (self $video) {
            if (empty($video->orientation) && !empty($video->video_url)) {
                $video->orientation = self::detectOrientationFromUrl($video->video_url);
            }
        });
    }

    public static function detectOrientationFromUrl(?string $url): string
    {
        if (!$url) return 'landscape';
        return str_contains($url, '/shorts/') ? 'portrait' : 'landscape';
    }

    // ─── Relationships ───

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    // ─── Scopes ───

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    public function scopeByCity($query, int $cityId)
    {
        return $query->where('city_id', $cityId);
    }

    public function scopeByTags($query, array $tags)
    {
        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhereRaw("JSON_SEARCH(tags, 'one', ?) IS NOT NULL", [$tag]);
            }
        });
    }
}
