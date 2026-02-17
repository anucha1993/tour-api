<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TourPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'terms',
        'remarks',
        'cancellation_policy',
        'inclusions',
        'exclusions',
        'timeline',
        'image_url',
        'image_cf_id',
        'pdf_url',
        'pdf_path',
        'hashtags',
        'expires_at',
        'is_never_expire',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'inclusions' => 'array',
        'exclusions' => 'array',
        'timeline' => 'array',
        'hashtags' => 'array',
        'expires_at' => 'date',
        'is_never_expire' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::generateSlug($model->name);
            }
        });

        static::updating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::generateSlug($model->name);
            }
        });
    }

    /**
     * Generate a URL-safe slug, supporting Thai characters
     */
    public static function generateSlug(string $name): string
    {
        $slug = Str::slug($name);

        if (empty($slug)) {
            $slug = preg_replace('/[^\p{L}\p{N}\p{M}]+/u', '-', $name);
            $slug = trim($slug, '-');
            $slug = mb_strtolower($slug, 'UTF-8');
        }

        if (empty($slug)) {
            $slug = 'package-' . time();
        }

        // Ensure unique
        $base = $slug;
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Active scope
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Not expired scope — either never expires or expires_at is in the future
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('is_never_expire', true)
              ->orWhere('expires_at', '>=', now()->toDateString());
        });
    }

    /**
     * Countries relationship (many-to-many)
     */
    public function countries()
    {
        return $this->belongsToMany(Country::class, 'tour_package_country');
    }
}
