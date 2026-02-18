<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactPageSetting extends Model
{
    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'hero_image_url',
        'hero_image_cf_id',
        'intro_text',
        'map_embed_url',
        'office_name',
        'office_address',
        'office_lat',
        'office_lng',
        'show_map',
        'show_form',
        'is_active',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    protected $casts = [
        'show_map' => 'boolean',
        'show_form' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get or create singleton settings
     */
    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'hero_title' => 'ติดต่อเรา',
            'hero_subtitle' => 'มีคำถาม? ติดต่อเราได้ทุกช่องทาง',
            'office_name' => 'Next Trip Holiday',
            'show_map' => true,
            'show_form' => true,
            'is_active' => true,
        ]);
    }
}
