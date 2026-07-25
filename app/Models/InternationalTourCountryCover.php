<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternationalTourCountryCover extends Model
{
    protected $fillable = [
        'setting_id',
        'country_id',
        'image_url',
        'cloudflare_id',
        'image_position',
        'alt_text',
        'hero_text',
        'intro_html',
        'faq',
        'pinned_tour_codes',
        'sort_order',
    ];

    protected $casts = [
        'setting_id' => 'integer',
        'country_id' => 'integer',
        'sort_order' => 'integer',
        'faq' => 'array',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(InternationalTourSetting::class, 'setting_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
