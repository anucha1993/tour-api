<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    use HasFactory;

    protected $fillable = [
        'cloudflare_id',
        'url',
        'thumbnail_url',
        'filename',
        'alt',
        'title',
        'subtitle',
        'button_text',
        'button_link',
        'width',
        'height',
        'file_size',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
        'file_size' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: Active slides only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Get active slides for homepage
     */
    public static function getActiveSlides(): \Illuminate\Support\Collection
    {
        return static::active()
            ->ordered()
            ->get();
    }

    /**
     * Bust the public list cache whenever a slide is created/updated/deleted
     * so the homepage reflects admin changes immediately.
     */
    protected static function booted(): void
    {
        $flush = fn () => \Illuminate\Support\Facades\Cache::forget('hero_slides_public_v1');
        static::saved($flush);
        static::deleted($flush);
    }
}
