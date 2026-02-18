<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointTransaction extends Model
{
    protected $fillable = [
        'member_id',
        'rule_id',
        'type',
        'points',
        'balance_after',
        'source_type',
        'source_id',
        'description',
        'expires_at',
        'is_expired',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'expires_at' => 'datetime',
        'is_expired' => 'boolean',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(WebMember::class, 'member_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PointRule::class, 'rule_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeEarned($query)
    {
        return $query->where('type', 'earn');
    }

    public function scopeSpent($query)
    {
        return $query->where('type', 'spend');
    }

    public function scopeExpired($query)
    {
        return $query->where('type', 'expire');
    }

    public function scopeNotExpired($query)
    {
        return $query->where('is_expired', false);
    }

    public function scopeExpiring($query)
    {
        return $query->where('type', 'earn')
            ->where('is_expired', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30));
    }
}
