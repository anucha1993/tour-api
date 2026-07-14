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

    // Predefined pages (must sync with DEFAULT_PAGES in
    // tour-backend/src/app/dashboard/website/seo/page.tsx AND with
    // buildMetadata() calls in each tour-web layout.tsx)
    const PAGES = [
        // ─── Global ────────────────────────────────────────
        'global' => 'ตั้งค่า SEO ทั้งเว็บ (Fallback)',

        // ─── Main pages ────────────────────────────────────
        'home' => 'หน้าแรก',
        'about' => 'เกี่ยวกับเรา',
        'contact' => 'ติดต่อเรา',
        'blog' => 'บล็อก',
        'promotions' => 'โปรโมชั่น',
        'reviews' => 'รีวิวจากลูกค้า',

        // ─── Tours ─────────────────────────────────────────
        'tours-international' => 'ทัวร์ต่างประเทศ',
        'tours-domestic' => 'ทัวร์ในประเทศ',
        'tours-festival' => 'ทัวร์เทศกาล',
        'tours-packages' => 'แพ็คเกจทัวร์',
        'tours-group' => 'รับจัดกรุ๊ปทัวร์',

        // ─── Legal / policy ────────────────────────────────
        'terms' => 'เงื่อนไขการให้บริการ',
        'privacy-policy' => 'นโยบายความเป็นส่วนตัว',
        'cookie-policy' => 'นโยบายคุกกี้',
        'data-deletion' => 'ขอลบข้อมูล',
        'payment-channels' => 'ช่องทางการชำระเงิน',
        'payment-terms' => 'เงื่อนไขการชำระเงิน',

        // ─── Utility / auth (usually noindex) ──────────────
        'search' => 'ค้นหา',
        'login' => 'เข้าสู่ระบบ',
        'register' => 'สมัครสมาชิก',
        'forgot-password' => 'ลืมรหัสผ่าน',
    ];

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('page_slug', $slug);
    }
}
