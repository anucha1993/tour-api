<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Popup extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'cloudflare_id',
        'image_url',
        'thumbnail_url',
        'alt_text',
        'button_text',
        'button_link',
        'button_color',
        'popup_type',
        'display_frequency',
        'delay_seconds',
        'start_date',
        'end_date',
        'is_active',
        'show_close_button',
        'close_on_overlay',
        'sort_order',
        'width',
        'height',
        'file_size',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_close_button' => 'boolean',
        'close_on_overlay' => 'boolean',
        'delay_seconds' => 'integer',
        'sort_order' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'file_size' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Type constants
    const TYPE_IMAGE = 'image';
    const TYPE_CONTENT = 'content';
    const TYPE_PROMO = 'promo';
    const TYPE_NEWSLETTER = 'newsletter';
    const TYPE_ANNOUNCEMENT = 'announcement';

    const TYPES = [
        self::TYPE_IMAGE => 'รูปภาพ',
        self::TYPE_CONTENT => 'เนื้อหา',
        self::TYPE_PROMO => 'โปรโมชั่น',
        self::TYPE_NEWSLETTER => 'สมัครรับข่าวสาร',
        self::TYPE_ANNOUNCEMENT => 'ประกาศ',
    ];

    const FREQUENCIES = [
        'always' => 'แสดงทุกครั้ง',
        'once_per_session' => 'แสดงครั้งเดียว/เซสชัน',
        'once_per_day' => 'แสดงครั้งเดียว/วัน',
        'once_per_week' => 'แสดงครั้งเดียว/สัปดาห์',
        'once' => 'แสดงครั้งเดียวเท่านั้น',
    ];

    /**
     * Scope: Active popups only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Scope: Currently valid date range
     */
    public function scopeCurrentlyValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('start_date')
              ->orWhere('start_date', '<=', now());
        })->where(function ($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now());
        });
    }
}
