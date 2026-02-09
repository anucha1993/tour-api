<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tour_id',
        'session_id',
        'ip_address',
        'user_agent',
        'member_id',
        'country_id',
        'country_name',
        'city_ids',
        'city_names',
        'hashtags',
        'themes',
        'region',
        'sub_region',
        'price',
        'promotion_type',
        'duration_days',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'device_type',
        'viewed_at',
    ];

    protected $casts = [
        'city_ids' => 'array',
        'city_names' => 'array',
        'hashtags' => 'array',
        'themes' => 'array',
        'price' => 'decimal:2',
        'viewed_at' => 'datetime',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Detect device type from user agent
     */
    public static function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) return 'desktop';
        $ua = strtolower($userAgent);
        
        if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobile|android|iphone|ipod|opera mini|iemobile|wpdesktop/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }
}
