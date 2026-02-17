<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupTourPageSetting extends Model
{
    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'hero_image_url',
        'hero_image_cf_id',
        'hero_image_position',
        'content',
        'stats',
        'group_types',
        'advantages_title',
        'advantages_image_url',
        'advantages_image_cf_id',
        'advantages',
        'process_steps',
        'faqs',
        'cta_title',
        'cta_description',
        'cta_phone',
        'cta_email',
        'cta_line_id',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'is_active',
    ];

    protected $casts = [
        'stats' => 'array',
        'group_types' => 'array',
        'advantages' => 'array',
        'process_steps' => 'array',
        'faqs' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get or create the singleton settings row
     */
    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'hero_title' => 'รับจัดกรุ๊ปทัวร์ ครบวงจร',
            'hero_subtitle' => 'ไม่ว่าจะทริปบริษัท สัมมนา ทัศนศึกษา หรือครอบครัวใหญ่ เราดูแลทุกรายละเอียดให้คุณ',
            'stats' => [
                ['icon' => 'Calendar', 'value' => '10+', 'label' => 'ปีประสบการณ์'],
                ['icon' => 'Users', 'value' => '500+', 'label' => 'กรุ๊ปที่เราดูแล'],
                ['icon' => 'Globe', 'value' => '50+', 'label' => 'ประเทศปลายทาง'],
                ['icon' => 'Star', 'value' => '98%', 'label' => 'ลูกค้าพึงพอใจ'],
            ],
            'group_types' => [
                ['icon' => 'Building2', 'title' => 'ทริปบริษัท / Team Building', 'description' => 'สร้างทีมเวิร์ค กระชับความสัมพันธ์ในองค์กร'],
                ['icon' => 'GraduationCap', 'title' => 'ทัศนศึกษา / สถาบันการศึกษา', 'description' => 'เปิดประสบการณ์ใหม่ให้นักเรียนนักศึกษา'],
                ['icon' => 'Landmark', 'title' => 'สัมมนา / ประชุม (MICE)', 'description' => 'จัดงานระดับมืออาชีพ ครบทุกความต้องการ'],
                ['icon' => 'Heart', 'title' => 'ครอบครัวใหญ่ / งานรวมญาติ', 'description' => 'ทริปครอบครัวสุดพิเศษ ดูแลทุกวัย'],
                ['icon' => 'Church', 'title' => 'กลุ่มศาสนา / จาริกแสวงบุญ', 'description' => 'ดูแลทุกรายละเอียดด้วยใจ'],
                ['icon' => 'Trophy', 'title' => 'กลุ่มรางวัล Incentive', 'description' => 'ตอบแทนพนักงานด้วยทริปพิเศษ'],
            ],
            'advantages_title' => 'ทำไมต้องเลือกเรา',
            'advantages' => [
                ['text' => 'ออกแบบโปรแกรมเฉพาะกลุ่ม ไม่ใช่ทัวร์สำเร็จรูป'],
                ['text' => 'ทีมงานมืออาชีพ ดูแลตลอดทริป'],
                ['text' => 'ราคาพิเศษสำหรับกรุ๊ป'],
                ['text' => 'รองรับกรุ๊ปทุกขนาด ตั้งแต่ 10-500 คน'],
                ['text' => 'ดูแลเรื่องวีซ่า ตั๋วเครื่องบิน โรงแรม ครบวงจร'],
                ['text' => 'มีใบอนุญาตนำเที่ยวถูกต้องตามกฎหมาย'],
            ],
            'process_steps' => [
                ['step_number' => 1, 'title' => 'ติดต่อเรา', 'description' => 'แจ้งความต้องการเบื้องต้น จำนวนคน และปลายทางที่สนใจ'],
                ['step_number' => 2, 'title' => 'วางแผนทริป', 'description' => 'ทีมงานออกแบบโปรแกรมพร้อมใบเสนอราคา'],
                ['step_number' => 3, 'title' => 'ยืนยันและชำระเงิน', 'description' => 'ตกลงรายละเอียดและมัดจำเพื่อจองที่พักและตั๋ว'],
                ['step_number' => 4, 'title' => 'เตรียมตัวเดินทาง', 'description' => 'รับเอกสารการเดินทาง ข้อมูลเตรียมตัวก่อนเดินทาง'],
                ['step_number' => 5, 'title' => 'ออกเดินทาง!', 'description' => 'พร้อมทีมงานดูแลตลอดทริป มั่นใจได้ทุกขั้นตอน'],
            ],
            'faqs' => [
                ['question' => 'จำนวนคนขั้นต่ำในการจัดกรุ๊ปเท่าไหร่?', 'answer' => 'รับจัดกรุ๊ปตั้งแต่ 10 คนขึ้นไป สามารถปรับโปรแกรมได้ตามความต้องการ'],
                ['question' => 'ต้องจองล่วงหน้ากี่วัน?', 'answer' => 'แนะนำให้จองล่วงหน้าอย่างน้อย 30-45 วัน เพื่อเตรียมการเรื่องตั๋วเครื่องบินและโรงแรม'],
                ['question' => 'สามารถปรับเปลี่ยนโปรแกรมได้ไหม?', 'answer' => 'ได้เลยครับ เราออกแบบโปรแกรมตามความต้องการของกลุ่มโดยเฉพาะ'],
                ['question' => 'ราคาประมาณเท่าไหร่?', 'answer' => 'ขึ้นอยู่กับปลายทาง จำนวนวัน และระดับโรงแรม สามารถแจ้งงบประมาณให้เราออกแบบโปรแกรมได้'],
            ],
            'cta_title' => 'สนใจจัดกรุ๊ปทัวร์?',
            'cta_description' => 'ติดต่อเราได้เลย ทีมงานพร้อมให้คำปรึกษาและออกแบบทริปที่ใช่สำหรับคุณ',
        ]);
    }
}
