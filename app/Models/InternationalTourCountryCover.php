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
        'sort_order',
    ];

    protected $casts = [
        'setting_id' => 'integer',
        'country_id' => 'integer',
        'sort_order' => 'integer',
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
