<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AboutPageSetting;
use App\Models\AboutAssociation;
use App\Models\AboutService;
use App\Models\AboutCustomerGroup;
use App\Models\AboutAward;

class AboutSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // PAGE SETTINGS
        // ============================================================
        $settings = AboutPageSetting::firstOrCreate([], []);
        $settings->update([
            'hero_title' => 'เกี่ยวกับเรา เน็กซ์ ทริป ฮอลิเดย์',
            'hero_subtitle' => '"ถูกชัวร์ ทัวร์ทั่วโลก" ประสบการณ์ทำทัวร์มากกว่า 15 ปี',
            'hero_image_position' => 'center',
            'about_title' => 'เกี่ยวกับ เน็กซ์ ทริป ฮอลิเดย์',
            'about_content' => '<p>ด้วยความรักในการท่องเที่ยวและประสบการณ์ยาวนานตั้งแต่ปี 2550 เราจึงก่อตั้ง <strong>เน็กซ์ ทริป ฮอลิเดย์ จำกัด</strong> ขึ้นด้วยความเชื่อมั่นว่า "ทริปถัดไปในการท่องเที่ยว จะต้องดี มีมาตรฐานเสมอ"</p>
<p>บริษัทเป็นผู้นำด้านการให้บริการทัวร์ในประเทศไทย ครอบคลุมกว่า <strong>50 ประเทศ</strong>ทั่วเอเชีย ยุโรป และอเมริกา มีโปรแกรมทัวร์ให้เลือกมากกว่า <strong>1,000 รายการ</strong> และทีมงานผู้เชี่ยวชาญพร้อมจัดทริปตามความต้องการของคุณ</p>',
            'highlights' => [
                ['value' => '15', 'suffix' => '+', 'label' => 'ปีประสบการณ์'],
                ['value' => '50', 'suffix' => '+', 'label' => 'ประเทศทั่วโลก'],
                ['value' => '1,000', 'suffix' => '+', 'label' => 'โปรแกรมทัวร์'],
                ['value' => '10,000', 'suffix' => '+', 'label' => 'ลูกค้าที่ไว้วางใจ'],
            ],
            'value_props' => [
                'มั่นใจในราคาที่ถูกและคุ้มค่าที่สุด',
                'ส่วนลดพิเศษสำหรับลูกค้าเก่าและลูกค้าประจำ',
                'มีโปรโมชั่นและสิทธิประโยชน์จากบัตรเครดิตที่หลากหลาย',
                'ความปลอดภัยและไว้ใจได้ตลอดการเดินทาง',
                'ทีมงานมีประสบการณ์และพร้อมให้บริการตลอด 24 ชั่วโมง',
                'โปรแกรมทัวร์คุณภาพ จัดเต็มทุกไฮไลท์',
            ],
            'company_name' => 'บริษัท เน็กซ์ ทริป ฮอลิเดย์ จำกัด',
            'registration_no' => '0115556013658',
            'capital' => '5,000,000 บาท (ห้าล้านบาทถ้วน)',
            'vat_no' => '0115556013658',
            'tat_license' => '11/07440',
            'company_info_extra' => 'จากสำนักทะเบียนธุรกิจนำเที่ยวและมัคคุเทศก์',
            'seo_title' => 'เกี่ยวกับเรา - เน็กซ์ ทริป ฮอลิเดย์ | Next Trip Holiday',
            'seo_description' => 'เน็กซ์ ทริป ฮอลิเดย์ บริษัททัวร์ต่างประเทศ ทัวร์เอเชีย ทัวร์ยุโรป ประสบการณ์กว่า 15 ปี TAT License 11/07440',
        ]);

        // ============================================================
        // ASSOCIATIONS
        // ============================================================
        $associations = [
            [
                'name' => 'สมาคมไทยธุรกิจการท่องเที่ยว (ATTA)',
                'license_no' => 'No.04896',
                'sort_order' => 1,
            ],
            [
                'name' => 'สมาคมธุรกิจท่องเที่ยวภายในประเทศ (สทน.)',
                'license_no' => 'No.1063',
                'sort_order' => 2,
            ],
            [
                'name' => 'สมาคมไทยบริการท่องเที่ยว (TTAA)',
                'license_no' => 'No.1469',
                'sort_order' => 3,
            ],
            [
                'name' => 'การท่องเที่ยวแห่งประเทศไทย (ททท.)',
                'license_no' => 'TAT License: 11/07440',
                'sort_order' => 4,
            ],
        ];

        foreach ($associations as $assoc) {
            AboutAssociation::updateOrCreate(
                ['name' => $assoc['name']],
                $assoc
            );
        }

        // ============================================================
        // SERVICES
        // ============================================================
        $services = [
            [
                'title' => 'ทัวร์ต่างประเทศ',
                'description' => 'บริการจัดทัวร์ต่างประเทศทั่วเอเชีย ยุโรป อเมริกา ตะวันออกกลาง และอื่นๆ กว่า 50 ประเทศทั่วโลก',
                'icon' => 'Plane',
                'sort_order' => 1,
            ],
            [
                'title' => 'ทัวร์ในประเทศ',
                'description' => 'ทัวร์ในประเทศไทย ครอบคลุมทุกภูมิภาค เหนือ ใต้ ออก ตก ทุกไฮไลท์ที่ห้ามพลาด',
                'icon' => 'Palmtree',
                'sort_order' => 2,
            ],
            [
                'title' => 'กรุ๊ปเหมาส่วนตัว VIP',
                'description' => 'จัดกรุ๊ปทัวร์ส่วนตัว VIP ตามความต้องการ กำหนดวัน สถานที่ กิจกรรมได้ตามใจ',
                'icon' => 'Crown',
                'sort_order' => 3,
            ],
            [
                'title' => 'ทัวร์ศึกษาดูงาน',
                'description' => 'จัดทัวร์ศึกษาดูงานสำหรับหน่วยงานราชการ รัฐวิสาหกิจ และองค์กรเอกชน ทั้งในและต่างประเทศ',
                'icon' => 'BookOpen',
                'sort_order' => 4,
            ],
            [
                'title' => 'ทัวร์จอยกรุ๊ป',
                'description' => 'ทัวร์จอยกรุ๊ป ราคาประหยัด สำหรับนักเดินทางที่ต้องการร่วมกรุ๊ปกับคนอื่น',
                'icon' => 'Users',
                'sort_order' => 5,
            ],
            [
                'title' => 'บริการจองตั๋วเครื่องบิน',
                'description' => 'บริการจองตั๋วเครื่องบินทุกสายการบิน ทั้งในประเทศและต่างประเทศ ราคาพิเศษ',
                'icon' => 'Ticket',
                'sort_order' => 6,
            ],
        ];

        foreach ($services as $service) {
            AboutService::updateOrCreate(
                ['title' => $service['title']],
                $service
            );
        }

        // ============================================================
        // CUSTOMER GROUPS
        // ============================================================
        $customerGroups = [
            [
                'title' => 'บริษัทเอกชน',
                'description' => 'จัดทริปท่องเที่ยวประจำปี งานสัมมนา ดูงาน และกิจกรรม Team Building',
                'icon' => 'Building2',
                'sort_order' => 1,
            ],
            [
                'title' => 'หน่วยงานราชการ',
                'description' => 'จัดทัวร์ศึกษาดูงาน งบประมาณราชการ พร้อมเอกสารครบถ้วน',
                'icon' => 'Landmark',
                'sort_order' => 2,
            ],
            [
                'title' => 'รัฐวิสาหกิจ',
                'description' => 'บริการจัดทัวร์สำหรับหน่วยงานรัฐวิสาหกิจทุกประเภท',
                'icon' => 'HardHat',
                'sort_order' => 3,
            ],
            [
                'title' => 'สถาบันการศึกษา',
                'description' => 'ทริปทัศนศึกษาสำหรับนักเรียน นักศึกษา และคณาจารย์',
                'icon' => 'GraduationCap',
                'sort_order' => 4,
            ],
            [
                'title' => 'ครอบครัวและกลุ่มเพื่อน',
                'description' => 'ทริปส่วนตัวสำหรับครอบครัว กลุ่มเพื่อน ปรับแต่งได้ตามใจชอบ',
                'icon' => 'Heart',
                'sort_order' => 5,
            ],
            [
                'title' => 'องค์กร / สมาคม',
                'description' => 'จัดทัวร์สำหรับสมาคมและชมรมต่างๆ กลุ่มใหญ่ ราคาพิเศษ',
                'icon' => 'Handshake',
                'sort_order' => 6,
            ],
        ];

        foreach ($customerGroups as $group) {
            AboutCustomerGroup::updateOrCreate(
                ['title' => $group['title']],
                $group
            );
        }

        // ============================================================
        // AWARDS (placeholder — images to be uploaded via admin)
        // ============================================================
        $awards = [
            [
                'title' => 'รางวัลบริษัททัวร์ดีเด่น',
                'description' => 'รางวัลบริษัททัวร์ที่มีมาตรฐานและคุณภาพในการให้บริการ',
                'year' => '2023',
                'sort_order' => 1,
            ],
            [
                'title' => 'Thailand Tourism Awards',
                'description' => 'รางวัลอุตสาหกรรมท่องเที่ยวไทย จากการท่องเที่ยวแห่งประเทศไทย',
                'year' => '2024',
                'sort_order' => 2,
            ],
            [
                'title' => 'TTAA Best Agent Award',
                'description' => 'รางวัลตัวแทนท่องเที่ยวดีเด่น จากสมาคมไทยบริการท่องเที่ยว',
                'year' => '2024',
                'sort_order' => 3,
            ],
        ];

        foreach ($awards as $award) {
            AboutAward::updateOrCreate(
                ['title' => $award['title']],
                $award
            );
        }

        echo "About page seeded: " . AboutPageSetting::count() . " settings, "
            . AboutAssociation::count() . " associations, "
            . AboutService::count() . " services, "
            . AboutCustomerGroup::count() . " customer groups, "
            . AboutAward::count() . " awards\n";
    }
}
