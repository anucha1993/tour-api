<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewPageSetting extends Model
{
    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'hero_image_url',
        'hero_image_cf_id',
        'hero_image_position',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'is_active',
        'homepage_enabled',
        'homepage_title',
        'homepage_subtitle',
        'homepage_mode',
        'homepage_limit',
        'homepage_review_ids',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'homepage_enabled' => 'boolean',
        'homepage_limit' => 'integer',
        'homepage_review_ids' => 'array',
    ];

    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'hero_title' => 'รีวิวจากลูกค้า',
            'hero_subtitle' => 'เสียงจากลูกค้าที่ไว้วางใจเดินทางกับเรา อ่านประสบการณ์จริงจากผู้เดินทาง',
            'hero_image_position' => 'center',
            'is_active' => true,
            'homepage_enabled' => true,
            'homepage_title' => 'รีวิวจากลูกค้า',
            'homepage_subtitle' => 'เสียงจากลูกค้าที่ไว้วางใจเดินทางกับเรา',
            'homepage_mode' => 'latest',
            'homepage_limit' => 10,
        ]);
    }
}
