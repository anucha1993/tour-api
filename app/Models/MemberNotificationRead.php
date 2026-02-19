<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberNotificationRead extends Model
{
    protected $fillable = [
        'member_id',
        'notification_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
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
