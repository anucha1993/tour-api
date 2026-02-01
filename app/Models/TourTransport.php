<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourTransport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tour_id',
        'transport_id',
        'transport_code',
        'transport_name',
        'flight_no',
        'route_from',
        'route_to',
        'depart_time',
        'arrive_time',
        'transport_type',
        'day_no',
        'sort_order',
    ];

    protected $casts = [
        'depart_time' => 'datetime:H:i',
        'arrive_time' => 'datetime:H:i',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function transport(): BelongsTo
    {
        return $this->belongsTo(Transport::class);
    }
}
