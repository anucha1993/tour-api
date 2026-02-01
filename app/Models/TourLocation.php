<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourLocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tour_id',
        'city_id',
        'name',
        'name_en',
        'sort_order',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
