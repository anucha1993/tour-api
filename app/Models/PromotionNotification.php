<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PromotionNotification extends Model
{
    protected $fillable = [
        'title',
        'description',
        'how_to_use',
        'max_claims',
        'banner_url',
        'cloudflare_id',
        'link_url',
        'type',
        'target_type',
        'target_level_id',
        'is_active',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];

    const TYPE_PROMOTION  = 'promotion';
    const TYPE_FLASH_SALE = 'flash_sale';
    const TYPE_BIRTHDAY   = 'birthday';
    const TYPE_SPECIAL    = 'special';
    const TYPE_CUSTOM     = 'custom';

    const TARGET_ALL   = 'all';
    const TARGET_LEVEL = 'level';

    // ---- Relationships ---------------------------------------------------

    public function reads(): HasMany
    {
        return $this->hasMany(MemberNotificationRead::class, 'notification_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(MemberPromotionClaim::class, 'notification_id');
    }

    public function targetLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'target_level_id');
    }

    // ---- Scopes ----------------------------------------------------------

    /**
     * Active + within date range
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * Notifications visible to a given member (target_type = all OR level matches)
     */
    public function scopeForMember($query, WebMember $member)
    {
        return $query->where(function ($q) use ($member) {
            $q->where('target_type', self::TARGET_ALL)
              ->orWhere(function ($q2) use ($member) {
                  $q2->where('target_type', self::TARGET_LEVEL)
                     ->where('target_level_id', $member->current_level_id);
              });
        });
    }

    // ---- Helpers ---------------------------------------------------------

    public function isReadBy(WebMember $member): bool
    {
        return $this->reads()->where('member_id', $member->id)->exists();
    }
}
