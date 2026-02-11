<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_slug',
        'page_name',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'og_image_cloudflare_id',
        'canonical_url',
        'robots_index',
        'robots_follow',
        'structured_data',
        'custom_head_tags',
    ];

    protected $casts = [
        'robots_index' => 'boolean',
        'robots_follow' => 'boolean',
    ];

    // Predefined pages
    const PAGES = [
        'global' => 'ตั้งค่า SEO ทั้งเว็บ',
        'home' => 'หน้าแรก',
        'tours' => 'หน้ารวมทัวร์',
        'tours-international' => 'ทัวร์ต่างประเทศ',
        'tours-domestic' => 'ทัวร์ในประเทศ',
        'promotions' => 'โปรโมชั่น',
        'blog' => 'บล็อก',
        'about' => 'เกี่ยวกับเรา',
        'contact' => 'ติดต่อเรา',
    ];

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('page_slug', $slug);
    }
}
