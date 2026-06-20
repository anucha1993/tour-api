<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPageSetting extends Model
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
        'show_sidebar',
        'sidebar_show_author',
        'sidebar_show_related_posts',
        'sidebar_show_recent_posts',
        'sidebar_show_recommended_tours',
        'sidebar_show_back_button',
        'sidebar_related_posts_limit',
        'sidebar_recent_posts_limit',
        'sidebar_recommended_tours_limit',
        'sidebar_recommended_tours_title',
        'sidebar_related_posts_title',
        'sidebar_recent_posts_title',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_sidebar' => 'boolean',
        'sidebar_show_author' => 'boolean',
        'sidebar_show_related_posts' => 'boolean',
        'sidebar_show_recent_posts' => 'boolean',
        'sidebar_show_recommended_tours' => 'boolean',
        'sidebar_show_back_button' => 'boolean',
        'sidebar_related_posts_limit' => 'integer',
        'sidebar_recent_posts_limit' => 'integer',
        'sidebar_recommended_tours_limit' => 'integer',
    ];

    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'hero_title' => 'รอบรู้เรื่องเที่ยว',
            'hero_subtitle' => 'บทความท่องเที่ยว เคล็ดลับ และแรงบันดาลใจสำหรับการเดินทาง',
            'hero_image_position' => 'center',
            'is_active' => true,
        ]);
    }
}
