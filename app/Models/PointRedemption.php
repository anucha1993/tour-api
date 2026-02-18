<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointRedemption extends Model
{
    protected $fillable = [
        'member_id',
        'transaction_id',
        'points_used',
        'discount_amount',
        'redemption_rate',
        'booking_code',
        'status',
        'note',
    ];

    protected $casts = [
        'points_used' => 'integer',
        'discount_amount' => 'decimal:2',
        'redemption_rate' => 'decimal:2',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(WebMember::class, 'member_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class, 'transaction_id');
    }
}
