<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomesticTourCityCover extends Model
{
    protected $fillable = [
        'setting_id',
        'city_id',
        'image_url',
        'cloudflare_id',
        'image_position',
        'alt_text',
        'sort_order',
    ];

    protected $casts = [
        'setting_id' => 'integer',
        'city_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(DomesticTourSetting::class, 'setting_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
