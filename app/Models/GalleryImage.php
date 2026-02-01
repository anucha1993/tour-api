<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'cloudflare_id',
        'url',
        'thumbnail_url',
        'filename',
        'alt',
        'caption',
        'country_id',
        'city_id',
        'tags',
        'width',
        'height',
        'file_size',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_active' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
        'file_size' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Relationship: Country
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Relationship: City
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Scope: Active images only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By country
     */
    public function scopeByCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Scope: By city
     */
    public function scopeByCity($query, int $cityId)
    {
        return $query->where('city_id', $cityId);
    }

    /**
     * Scope: By tags (match any)
     */
    public function scopeByTags($query, array $tags)
    {
        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhereJsonContains('tags', $tag);
            }
        });
    }

    /**
     * Get matching images for a tour
     * Priority: Tags/Hashtags > City > Country
     */
    public static function getForTour(array $cityIds, array $countryIds, array $hashtags, int $limit = 6): \Illuminate\Support\Collection
    {
        $images = collect();

        // 1. Match by tags/hashtags (highest priority)
        if (!empty($hashtags)) {
            $tagImages = self::active()
                ->byTags($hashtags)
                ->inRandomOrder()
                ->limit($limit)
                ->get();
            $images = $images->merge($tagImages);
        }

        // 2. Match by city if need more
        if ($images->count() < $limit && !empty($cityIds)) {
            $remaining = $limit - $images->count();
            $cityImages = self::active()
                ->whereIn('city_id', $cityIds)
                ->whereNotIn('id', $images->pluck('id'))
                ->inRandomOrder()
                ->limit($remaining)
                ->get();
            $images = $images->merge($cityImages);
        }

        // 3. Match by country if need more
        if ($images->count() < $limit && !empty($countryIds)) {
            $remaining = $limit - $images->count();
            $countryImages = self::active()
                ->whereIn('country_id', $countryIds)
                ->whereNotIn('id', $images->pluck('id'))
                ->inRandomOrder()
                ->limit($remaining)
                ->get();
            $images = $images->merge($countryImages);
        }

        return $images->take($limit);
    }

    /**
     * File size validation: max 150KB
     */
    public static function getMaxFileSize(): int
    {
        return 150 * 1024; // 150 KB
    }

    /**
     * Allowed dimensions
     */
    public static function getAllowedDimensions(): array
    {
        return [
            'width' => 1200,
            'height' => 800,
        ];
    }
}
