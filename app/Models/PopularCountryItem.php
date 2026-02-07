<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopularCountryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'setting_id',
        'country_id',
        'image_url',
        'cloudflare_id',
        'alt_text',
        'title',
        'subtitle',
        'link_url',
        'display_name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relationship: Setting
     */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(PopularCountrySetting::class, 'setting_id');
    }

    /**
     * Relationship: Country
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Scope: Active items only
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
     * Get display name (custom or country name)
     */
    public function getDisplayNameAttribute($value): string
    {
        if ($value) {
            return $value;
        }
        
        return $this->country?->name_th ?? $this->country?->name_en ?? '';
    }

    /**
     * Get image URL with Cloudflare fallback and cache busting
     */
    public function getImageAttribute(): ?string
    {
        $url = null;
        
        if ($this->image_url) {
            $url = $this->image_url;
        } elseif ($this->cloudflare_id) {
            $url = "https://imagedelivery.net/" . config('services.cloudflare.account_hash', 'yixdo-GXTcyjkoSkBzfBcA') . "/{$this->cloudflare_id}/public";
        }
        
        // Add cache busting based on updated_at timestamp
        if ($url && $this->updated_at) {
            $timestamp = $this->updated_at->timestamp;
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= "{$separator}v={$timestamp}";
        }
        
        return $url;
    }
}
