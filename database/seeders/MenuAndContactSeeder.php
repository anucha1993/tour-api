<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\SiteContact;
use App\Models\SeoSetting;

class MenuAndContactSeeder extends Seeder
{
    public function run(): void
    {
        // ============================
        // HEADER MENUS
        // ============================
        $headerMenus = [
            [
                'title' => 'หน้าหลัก',
                'url' => '/',
                'children' => [],
            ],
            [
                'title' => 'ทัวร์ต่างประเทศ',
                'url' => '/tours/international',
                'children' => [
                    ['title' => 'ทัวร์ยุโรป', 'url' => '/tours/europe'],
                    ['title' => 'ทัวร์ญี่ปุ่น', 'url' => '/tours/japan'],
                    ['title' => 'ทัวร์เกาหลี', 'url' => '/tours/korea'],
                    ['title' => 'ทัวร์จีน', 'url' => '/tours/china'],
                    ['title' => 'ทัวร์ไต้หวัน', 'url' => '/tours/taiwan'],
                    ['title' => 'ทัวร์เวียดนาม', 'url' => '/tours/vietnam'],
                    ['title' => 'ทัวร์สิงคโปร์', 'url' => '/tours/singapore'],
                    ['title' => 'ทัวร์ฮ่องกง', 'url' => '/tours/hongkong'],
                    ['title' => 'ดูทั้งหมด', 'url' => '/tours/international'],
                ],
            ],
            [
                'title' => 'ทัวร์ในประเทศ',
                'url' => '/tours/domestic',
                'children' => [
                    ['title' => 'ทัวร์ภาคเหนือ', 'url' => '/tours/domestic/north'],
                    ['title' => 'ทัวร์ภาคใต้', 'url' => '/tours/domestic/south'],
                    ['title' => 'ทัวร์ภาคอีสาน', 'url' => '/tours/domestic/northeast'],
                    ['title' => 'ทัวร์ภาคกลาง', 'url' => '/tours/domestic/central'],
                    ['title' => 'ดูทั้งหมด', 'url' => '/tours/domestic'],
                ],
            ],
            [
                'title' => 'ทัวร์โปรโมชั่น',
                'url' => '/promotions',
                'children' => [],
            ],
            [
                'title' => 'ทัวร์ตามเทศกาล',
                'url' => '/tours/festival',
                'children' => [
                    ['title' => 'ทัวร์ปีใหม่', 'url' => '/tours/festival/new-year'],
                    ['title' => 'ทัวร์สงกรานต์', 'url' => '/tours/festival/songkran'],
                    ['title' => 'ทัวร์วันหยุดยาว', 'url' => '/tours/festival/long-weekend'],
                    ['title' => 'ดูทั้งหมด', 'url' => '/tours/festival'],
                ],
            ],
            [
                'title' => 'แพ็คเกจทัวร์',
                'url' => '/packages',
                'children' => [],
            ],
            [
                'title' => 'รับจัดกรุ๊ปทัวร์',
                'url' => '/group-tours',
                'children' => [],
            ],
            [
                'title' => 'รอบรู้เรื่องเที่ยว',
                'url' => '/blog',
                'children' => [],
            ],
        ];

        foreach ($headerMenus as $i => $menu) {
            $parent = Menu::create([
                'location' => Menu::LOCATION_HEADER,
                'title' => $menu['title'],
                'url' => $menu['url'],
                'target' => '_self',
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);

            foreach ($menu['children'] as $j => $child) {
                Menu::create([
                    'location' => Menu::LOCATION_HEADER,
                    'title' => $child['title'],
                    'url' => $child['url'],
                    'target' => '_self',
                    'parent_id' => $parent->id,
                    'sort_order' => $j + 1,
                    'is_active' => true,
                ]);
            }
        }

        // ============================
        // FOOTER COL 1 - ทัวร์ยอดนิยม
        // ============================
        $footerCol1 = [
            ['title' => 'ทัวร์ต่างประเทศ', 'url' => '/tours/international'],
            ['title' => 'ทัวร์ในประเทศ', 'url' => '/tours/domestic'],
            ['title' => 'ทัวร์โปรโมชั่น', 'url' => '/promotions'],
            ['title' => 'ทัวร์ตามเทศกาล', 'url' => '/tours/festival'],
            ['title' => 'แพ็คเกจทัวร์', 'url' => '/packages'],
        ];

        foreach ($footerCol1 as $i => $item) {
            Menu::create([
                'location' => Menu::LOCATION_FOOTER_COL1,
                'title' => $item['title'],
                'url' => $item['url'],
                'target' => '_self',
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }

        // ============================
        // FOOTER COL 2 - บริษัท
        // ============================
        $footerCol2 = [
            ['title' => 'เกี่ยวกับเรา', 'url' => '/about'],
            ['title' => 'ติดต่อเรา', 'url' => '/contact'],
            ['title' => 'รับจัดกรุ๊ปทัวร์', 'url' => '/group-tours'],
            ['title' => 'ร่วมงานกับเรา', 'url' => '/careers'],
            ['title' => 'พันธมิตรธุรกิจ', 'url' => '/partners'],
            ['title' => 'รอบรู้เรื่องเที่ยว', 'url' => '/blog'],
        ];

        foreach ($footerCol2 as $i => $item) {
            Menu::create([
                'location' => Menu::LOCATION_FOOTER_COL2,
                'title' => $item['title'],
                'url' => $item['url'],
                'target' => '_self',
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }

        // ============================
        // FOOTER COL 3 - ช่วยเหลือ
        // ============================
        $footerCol3 = [
            ['title' => 'วิธีการจอง', 'url' => '/how-to-book'],
            ['title' => 'การชำระเงิน', 'url' => '/payment'],
            ['title' => 'คำถามที่พบบ่อย', 'url' => '/faq'],
            ['title' => 'เงื่อนไขการให้บริการ', 'url' => '/terms'],
            ['title' => 'เงื่อนไขชำระเงิน', 'url' => '/payment-terms'],
            ['title' => 'ช่องทางการชำระเงิน', 'url' => '/payment-channels'],
            ['title' => 'นโยบายคุกกี้', 'url' => '/cookie-policy'],
            ['title' => 'นโยบายความเป็นส่วนตัว', 'url' => '/privacy-policy'],
        ];

        foreach ($footerCol3 as $i => $item) {
            Menu::create([
                'location' => Menu::LOCATION_FOOTER_COL3,
                'title' => $item['title'],
                'url' => $item['url'],
                'target' => '_self',
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }

        // ============================
        // SITE CONTACTS
        // ============================

        // -- Contact group --
        $contacts = [
            ['key' => 'phone', 'label' => 'สอบถามทัวร์', 'value' => '02-136-9144', 'icon' => 'phone', 'url' => 'tel:021369144'],
            ['key' => 'hotline', 'label' => 'Hotline (ตลอดเวลา)', 'value' => '091-091-6364', 'icon' => 'headphones', 'url' => 'tel:0910916364'],
            ['key' => 'line_id', 'label' => 'LINE', 'value' => '@nexttripholiday', 'icon' => 'message-circle', 'url' => 'https://line.me/R/ti/p/@nexttripholiday'],
            ['key' => 'email', 'label' => 'อีเมล', 'value' => 'info@nexttripholiday.com', 'icon' => 'mail', 'url' => 'mailto:info@nexttripholiday.com'],
        ];

        foreach ($contacts as $i => $c) {
            SiteContact::create([
                'key' => $c['key'],
                'label' => $c['label'],
                'value' => $c['value'],
                'icon' => $c['icon'],
                'url' => $c['url'],
                'sort_order' => $i + 1,
                'is_active' => true,
                'group' => SiteContact::GROUP_CONTACT,
            ]);
        }

        // -- Social group --
        $socials = [
            ['key' => 'facebook', 'label' => 'Facebook', 'value' => 'nexttripholiday', 'icon' => 'facebook', 'url' => 'https://facebook.com/nexttripholiday'],
            ['key' => 'instagram', 'label' => 'Instagram', 'value' => 'nexttripholiday', 'icon' => 'instagram', 'url' => 'https://instagram.com/nexttripholiday'],
            ['key' => 'youtube', 'label' => 'YouTube', 'value' => '@nexttripholiday', 'icon' => 'youtube', 'url' => 'https://youtube.com/@nexttripholiday'],
            ['key' => 'tiktok', 'label' => 'TikTok', 'value' => '@nexttripholiday', 'icon' => 'tiktok', 'url' => 'https://tiktok.com/@nexttripholiday'],
        ];

        foreach ($socials as $i => $s) {
            SiteContact::create([
                'key' => $s['key'],
                'label' => $s['label'],
                'value' => $s['value'],
                'icon' => $s['icon'],
                'url' => $s['url'],
                'sort_order' => $i + 1,
                'is_active' => true,
                'group' => SiteContact::GROUP_SOCIAL,
            ]);
        }

        // -- Business hours group --
        $hours = [
            ['key' => 'everyday', 'label' => 'เปิดทุกวัน', 'value' => 'เปิดทุกวัน 08.00-23.00 น.', 'icon' => 'clock', 'url' => null],
        ];

        foreach ($hours as $i => $h) {
            SiteContact::create([
                'key' => $h['key'],
                'label' => $h['label'],
                'value' => $h['value'],
                'icon' => $h['icon'],
                'url' => $h['url'],
                'sort_order' => $i + 1,
                'is_active' => true,
                'group' => SiteContact::GROUP_BUSINESS_HOURS,
            ]);
        }

        // ============================
        // SEO SETTINGS
        // ============================
        $seoData = [
            [
                'page_slug' => 'global',
                'page_name' => 'ตั้งค่า SEO ทั้งเว็บ',
                'meta_title' => 'NextTrip - ทัวร์ท่องเที่ยวทั่วโลก',
                'meta_description' => 'บริษัททัวร์ชั้นนำ ให้บริการจัดทัวร์ท่องเที่ยวทั้งในและต่างประเทศ ทัวร์ยุโรป ทัวร์ญี่ปุ่น ทัวร์เกาหลี พร้อมทีมงานมืออาชีพดูแลตลอดการเดินทาง',
                'meta_keywords' => 'ทัวร์, ท่องเที่ยว, ทัวร์ต่างประเทศ, ทัวร์ยุโรป, ทัวร์ญี่ปุ่น, ทัวร์เกาหลี, บริษัททัวร์',
                'og_title' => 'NextTrip - ทัวร์ท่องเที่ยวทั่วโลก',
                'og_description' => 'บริษัททัวร์ชั้นนำ ให้บริการจัดทัวร์ท่องเที่ยวทั้งในและต่างประเทศ',
                'canonical_url' => 'https://nexttrip.co.th',
                'robots_index' => true,
                'robots_follow' => true,
            ],
            [
                'page_slug' => 'home',
                'page_name' => 'หน้าแรก',
                'meta_title' => 'NextTrip - ทัวร์ท่องเที่ยวทั่วโลก | จองทัวร์ราคาดี',
                'meta_description' => 'จองทัวร์ท่องเที่ยวทั่วโลกกับ NextTrip บริษัททัวร์ชั้นนำ ทัวร์ยุโรป ทัวร์ญี่ปุ่น ทัวร์เกาหลี ทัวร์จีน ราคาพิเศษ พร้อมทีมงานดูแลตลอดทริป',
                'meta_keywords' => 'จองทัวร์, ทัวร์ราคาถูก, ทัวร์ท่องเที่ยว, NextTrip',
                'canonical_url' => 'https://nexttrip.co.th',
                'robots_index' => true,
                'robots_follow' => true,
            ],
            [
                'page_slug' => 'tours',
                'page_name' => 'หน้ารวมทัวร์',
                'meta_title' => 'ทัวร์ทั้งหมด - NextTrip',
                'meta_description' => 'รวมทัวร์ท่องเที่ยวทั้งในและต่างประเทศ หลากหลายเส้นทาง ราคาพิเศษ จอง NextTrip วันนี้',
                'meta_keywords' => 'ทัวร์ทั้งหมด, รวมทัวร์, ทัวร์ไทย, ทัวร์ต่างประเทศ',
                'robots_index' => true,
                'robots_follow' => true,
            ],
            [
                'page_slug' => 'tours-international',
                'page_name' => 'ทัวร์ต่างประเทศ',
                'meta_title' => 'ทัวร์ต่างประเทศ - NextTrip | ทัวร์ยุโรป ญี่ปุ่น เกาหลี จีน',
                'meta_description' => 'ทัวร์ต่างประเทศราคาพิเศษ ทัวร์ยุโรป ทัวร์ญี่ปุ่น ทัวร์เกาหลี ทัวร์จีน ทัวร์ไต้หวัน รวมทุกเส้นทางยอดนิยม',
                'meta_keywords' => 'ทัวร์ต่างประเทศ, ทัวร์ยุโรป, ทัวร์ญี่ปุ่น, ทัวร์เกาหลี, ทัวร์จีน',
                'robots_index' => true,
                'robots_follow' => true,
            ],
            [
                'page_slug' => 'tours-domestic',
                'page_name' => 'ทัวร์ในประเทศ',
                'meta_title' => 'ทัวร์ในประเทศ - NextTrip | เที่ยวไทยทั่วประเทศ',
                'meta_description' => 'ทัวร์ในประเทศ เที่ยวไทยทั่วประเทศ ภาคเหนือ ภาคใต้ ภาคอีสาน ภาคกลาง ราคาดี จัดกรุ๊ปส่วนตัว',
                'meta_keywords' => 'ทัวร์ในประเทศ, เที่ยวไทย, ทัวร์เชียงใหม่, ทัวร์ภูเก็ต',
                'robots_index' => true,
                'robots_follow' => true,
            ],
            [
                'page_slug' => 'promotions',
                'page_name' => 'โปรโมชั่น',
                'meta_title' => 'โปรโมชั่นทัวร์ - NextTrip | ลดราคาพิเศษ',
                'meta_description' => 'โปรโมชั่นทัวร์ลดราคาพิเศษ จาก NextTrip จองวันนี้รับส่วนลดเพิ่ม ทัวร์ราคาถูกที่สุด',
                'meta_keywords' => 'โปรโมชั่นทัวร์, ทัวร์ลดราคา, ทัวร์ราคาถูก, โปรทัวร์',
                'robots_index' => true,
                'robots_follow' => true,
            ],
            [
                'page_slug' => 'blog',
                'page_name' => 'บล็อก',
                'meta_title' => 'รอบรู้เรื่องเที่ยว - NextTrip Blog',
                'meta_description' => 'บทความท่องเที่ยว เคล็ดลับการเดินทาง รีวิวทัวร์ แนะนำที่เที่ยว อัปเดตข่าวท่องเที่ยวล่าสุด',
                'meta_keywords' => 'บล็อกท่องเที่ยว, รีวิวทัวร์, เคล็ดลับเที่ยว, แนะนำที่เที่ยว',
                'robots_index' => true,
                'robots_follow' => true,
            ],
            [
                'page_slug' => 'about',
                'page_name' => 'เกี่ยวกับเรา',
                'meta_title' => 'เกี่ยวกับ NextTrip - บริษัททัวร์ชั้นนำ',
                'meta_description' => 'NextTrip บริษัททัวร์ชั้นนำ ใบอนุญาตถูกต้อง TAT: 11/07440 ประสบการณ์กว่า 10 ปี พร้อมทีมงานมืออาชีพ',
                'meta_keywords' => 'เกี่ยวกับ NextTrip, บริษัททัวร์, ใบอนุญาตนำเที่ยว',
                'robots_index' => true,
                'robots_follow' => true,
            ],
            [
                'page_slug' => 'contact',
                'page_name' => 'ติดต่อเรา',
                'meta_title' => 'ติดต่อเรา - NextTrip',
                'meta_description' => 'ติดต่อ NextTrip สอบถามทัวร์ โทร 02-136-9144 Hotline 091-091-6364 LINE: @nexttripholiday เปิดทุกวัน 08.00-23.00 น.',
                'meta_keywords' => 'ติดต่อ NextTrip, เบอร์โทรบริษัททัวร์, สอบถามทัวร์',
                'robots_index' => true,
                'robots_follow' => true,
            ],
        ];

        foreach ($seoData as $seo) {
            SeoSetting::updateOrCreate(
                ['page_slug' => $seo['page_slug']],
                $seo
            );
        }

        $this->command->info('✅ Seeded: ' . Menu::count() . ' menus, ' . SiteContact::count() . ' contacts, ' . SeoSetting::count() . ' SEO settings');
    }
}
