<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPromotionClaim extends Model
{
    protected $fillable = [
        'member_id',
        'notification_id',
        'claim_code',
        'status',
        'claimed_at',
        'used_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(WebMember::class, 'member_id');
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(PromotionNotification::class, 'notification_id');
    }
}
