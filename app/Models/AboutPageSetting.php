<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutPageSetting extends Model
{
    protected $table = 'about_page_settings';

    protected $fillable = [
        'hero_title', 'hero_subtitle', 'hero_image_url', 'hero_image_cf_id', 'hero_image_position',
        'about_title', 'about_content', 'highlights', 'value_props',
        'company_name', 'registration_no', 'capital', 'vat_no', 'tat_license', 'company_info_extra',
        'license_image_url', 'license_image_cf_id',
        'seo_title', 'seo_description', 'seo_keywords',
        'is_active',
    ];

    protected $casts = [
        'highlights' => 'array',
        'value_props' => 'array',
        'is_active' => 'boolean',
    ];

    public static function getSettings(): self
    {
        return static::firstOrCreate([], [
            'hero_title' => 'เกี่ยวกับเรา',
            'hero_subtitle' => 'Next Trip Holiday',
            'about_title' => 'เกี่ยวกับ เน็กซ์ ทริป ฮอลิเดย์',
            'about_content' => '',
            'highlights' => [],
            'value_props' => [],
        ]);
    }
}
