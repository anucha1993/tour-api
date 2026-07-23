<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationSetting extends Model
{
    protected $fillable = [
        'legal_name',
        'description',
        'price_range',
        'area_served',
        'languages',
        'founding_date',
        'rating_enabled',
        'rating_value',
        'rating_count',
        'faq_enabled',
        'faqs',
        'is_active',
    ];

    protected $casts = [
        'area_served' => 'array',
        'languages' => 'array',
        'faqs' => 'array',
        'rating_enabled' => 'boolean',
        'faq_enabled' => 'boolean',
        'is_active' => 'boolean',
        'rating_value' => 'decimal:1',
        'rating_count' => 'integer',
    ];

    /**
     * Get or create the singleton settings row.
     *
     * Seeds sensible Thai defaults (description, service areas and a few FAQs
     * targeting common AI/search questions) so the schema.org output is useful
     * out of the box, before an admin edits anything.
     */
    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'description' => 'Next Trip Holiday บริษัททัวร์ชั้นนำของไทย ให้บริการแพ็กเกจทัวร์ต่างประเทศและในประเทศครบวงจร ทั้งทัวร์ญี่ปุ่น เกาหลี จีน ฮ่องกง ไต้หวัน เวียดนาม และยุโรป พร้อมทีมงานมืออาชีพ ราคาคุ้มค่า จองง่าย ผ่อนชำระได้',
            'price_range' => '฿฿',
            'area_served' => ['Thailand', 'Japan', 'China', 'South Korea', 'Taiwan', 'Vietnam', 'Hong Kong', 'Europe'],
            'languages' => ['th', 'en'],
            'rating_enabled' => false,
            'faq_enabled' => true,
            'faqs' => [
                [
                    'question' => 'Next Trip Holiday คือใคร และให้บริการอะไรบ้าง?',
                    'answer' => 'Next Trip Holiday เป็นบริษัททัวร์ชั้นนำในประเทศไทย ให้บริการแพ็กเกจทัวร์ต่างประเทศและทัวร์ในประเทศ ครอบคลุมญี่ปุ่น เกาหลี จีน ฮ่องกง ไต้หวัน เวียดนาม ยุโรป และอีกหลายประเทศ พร้อมทีมงานมืออาชีพดูแลตลอดการเดินทาง',
                ],
                [
                    'question' => 'จองทัวร์กับ Next Trip Holiday ดีอย่างไร?',
                    'answer' => 'เรามีโปรแกรมทัวร์หลากหลาย ราคาคุ้มค่า มีใบอนุญาตประกอบธุรกิจนำเที่ยวถูกต้องตามกฎหมาย รองรับการผ่อนชำระผ่านบัตรเครดิต และมีทีมงานคอยให้คำปรึกษาก่อนและระหว่างการเดินทาง',
                ],
                [
                    'question' => 'ทัวร์ญี่ปุ่นควรไปเดือนไหนดี?',
                    'answer' => 'ญี่ปุ่นเที่ยวได้ตลอดทั้งปี ชมซากุระช่วงปลายมีนาคมถึงเมษายน ใบไม้เปลี่ยนสีช่วงพฤศจิกายน และเล่นหิมะช่วงธันวาคมถึงกุมภาพันธ์ โดย Next Trip Holiday มีโปรแกรมครบทุกฤดูกาล',
                ],
                [
                    'question' => 'จองทัวร์ต้องเตรียมเอกสารและชำระเงินอย่างไร?',
                    'answer' => 'ใช้หนังสือเดินทาง (Passport) ที่มีอายุเหลือมากกว่า 6 เดือน ชำระเงินมัดจำเพื่อยืนยันการจอง และชำระส่วนที่เหลือก่อนวันเดินทาง รองรับทั้งการโอนเงินและบัตรเครดิต',
                ],
            ],
            'is_active' => true,
        ]);
    }
}
