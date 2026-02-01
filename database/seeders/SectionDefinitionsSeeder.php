<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectionDefinitionsSeeder extends Seeder
{
    /**
     * Seed section definitions with default fields.
     */
    public function run(): void
    {
        $now = now();
        
        $sections = [
            // ================== SECTION: tour ==================
            ['section_name' => 'tour', 'field_name' => 'title', 'data_type' => 'TEXT', 'is_required' => true, 'is_system' => true, 'sort_order' => 1, 'description' => 'ชื่อทัวร์'],
            ['section_name' => 'tour', 'field_name' => 'code', 'data_type' => 'TEXT', 'is_required' => true, 'is_system' => true, 'sort_order' => 2, 'description' => 'รหัสทัวร์ของ wholesaler'],
            ['section_name' => 'tour', 'field_name' => 'tour_type', 'data_type' => 'ENUM', 'enum_values' => json_encode(['join', 'incentive', 'collective']), 'sort_order' => 3, 'description' => 'ประเภททัวร์'],
            ['section_name' => 'tour', 'field_name' => 'duration_days', 'data_type' => 'INT', 'is_required' => true, 'is_system' => true, 'sort_order' => 4, 'description' => 'จำนวนวัน'],
            ['section_name' => 'tour', 'field_name' => 'duration_nights', 'data_type' => 'INT', 'sort_order' => 5, 'description' => 'จำนวนคืน (default: days-1)'],
            ['section_name' => 'tour', 'field_name' => 'hotel_star', 'data_type' => 'INT', 'sort_order' => 6, 'description' => 'ระดับโรงแรม (3, 4, 5)'],
            ['section_name' => 'tour', 'field_name' => 'countries', 'data_type' => 'ARRAY_TEXT', 'is_required' => true, 'lookup_table' => 'countries', 'lookup_match_fields' => json_encode(['name_en', 'name_th', 'iso2', 'iso3']), 'sort_order' => 7, 'description' => 'ประเทศ → lookup to IDs'],
            ['section_name' => 'tour', 'field_name' => 'cities', 'data_type' => 'ARRAY_TEXT', 'lookup_table' => 'cities', 'lookup_match_fields' => json_encode(['name_en', 'name_th']), 'lookup_create_if_not_found' => true, 'sort_order' => 8, 'description' => 'เมือง → lookup to IDs'],
            ['section_name' => 'tour', 'field_name' => 'transport', 'data_type' => 'TEXT', 'lookup_table' => 'transports', 'lookup_match_fields' => json_encode(['code', 'name']), 'sort_order' => 9, 'description' => 'สายการบิน → lookup to ID'],
            ['section_name' => 'tour', 'field_name' => 'description', 'data_type' => 'TEXT', 'sort_order' => 10, 'description' => 'รายละเอียดทัวร์'],
            
            // ================== SECTION: period ==================
            ['section_name' => 'period', 'field_name' => 'start_date', 'data_type' => 'DATE', 'is_required' => true, 'is_system' => true, 'sort_order' => 1, 'description' => 'วันเริ่มเดินทาง'],
            ['section_name' => 'period', 'field_name' => 'end_date', 'data_type' => 'DATE', 'is_required' => true, 'is_system' => true, 'sort_order' => 2, 'description' => 'วันสิ้นสุด'],
            ['section_name' => 'period', 'field_name' => 'capacity', 'data_type' => 'INT', 'sort_order' => 3, 'description' => 'จำนวนที่นั่ง'],
            ['section_name' => 'period', 'field_name' => 'booked', 'data_type' => 'INT', 'default_value' => '0', 'sort_order' => 4, 'description' => 'จองแล้ว'],
            ['section_name' => 'period', 'field_name' => 'status', 'data_type' => 'ENUM', 'enum_values' => json_encode(['open', 'closed', 'full', 'cancelled']), 'default_value' => 'open', 'sort_order' => 5, 'description' => 'สถานะ'],
            ['section_name' => 'period', 'field_name' => 'is_visible', 'data_type' => 'BOOLEAN', 'default_value' => 'true', 'sort_order' => 6, 'description' => 'แสดงหรือไม่'],
            
            // ================== SECTION: pricing ==================
            ['section_name' => 'pricing', 'field_name' => 'price_adult', 'data_type' => 'DECIMAL', 'is_required' => true, 'is_system' => true, 'sort_order' => 1, 'description' => 'ราคาผู้ใหญ่'],
            ['section_name' => 'pricing', 'field_name' => 'price_child', 'data_type' => 'DECIMAL', 'sort_order' => 2, 'description' => 'ราคาเด็ก'],
            ['section_name' => 'pricing', 'field_name' => 'price_child_nobed', 'data_type' => 'DECIMAL', 'sort_order' => 3, 'description' => 'ราคาเด็กไม่มีเตียง'],
            ['section_name' => 'pricing', 'field_name' => 'price_single', 'data_type' => 'DECIMAL', 'sort_order' => 4, 'description' => 'พักเดี่ยว'],
            ['section_name' => 'pricing', 'field_name' => 'discount_adult', 'data_type' => 'DECIMAL', 'sort_order' => 5, 'description' => 'ส่วนลดผู้ใหญ่'],
            ['section_name' => 'pricing', 'field_name' => 'discount_child', 'data_type' => 'DECIMAL', 'sort_order' => 6, 'description' => 'ส่วนลดเด็ก'],
            ['section_name' => 'pricing', 'field_name' => 'currency', 'data_type' => 'TEXT', 'default_value' => 'THB', 'sort_order' => 7, 'description' => 'สกุลเงิน'],
            
            // ================== SECTION: content ==================
            ['section_name' => 'content', 'field_name' => 'highlights', 'data_type' => 'ARRAY_TEXT', 'sort_order' => 1, 'description' => 'ไฮไลท์การเดินทาง'],
            ['section_name' => 'content', 'field_name' => 'food_highlights', 'data_type' => 'ARRAY_TEXT', 'sort_order' => 2, 'description' => 'ไฮไลท์อาหาร'],
            ['section_name' => 'content', 'field_name' => 'shopping_highlights', 'data_type' => 'ARRAY_TEXT', 'sort_order' => 3, 'description' => 'ไฮไลท์ช้อปปิ้ง'],
            ['section_name' => 'content', 'field_name' => 'inclusions', 'data_type' => 'TEXT', 'sort_order' => 4, 'description' => 'สิ่งที่รวม (HTML ok)'],
            ['section_name' => 'content', 'field_name' => 'exclusions', 'data_type' => 'TEXT', 'sort_order' => 5, 'description' => 'สิ่งที่ไม่รวม'],
            ['section_name' => 'content', 'field_name' => 'conditions', 'data_type' => 'TEXT', 'sort_order' => 6, 'description' => 'เงื่อนไข'],
            ['section_name' => 'content', 'field_name' => 'itinerary', 'data_type' => 'JSON', 'sort_order' => 7, 'description' => 'โปรแกรมการเดินทาง'],
            
            // ================== SECTION: media ==================
            ['section_name' => 'media', 'field_name' => 'cover_image', 'data_type' => 'TEXT', 'sort_order' => 1, 'description' => 'URL รูปปก'],
            ['section_name' => 'media', 'field_name' => 'cover_alt', 'data_type' => 'TEXT', 'sort_order' => 2, 'description' => 'Alt text'],
            ['section_name' => 'media', 'field_name' => 'gallery', 'data_type' => 'ARRAY_TEXT', 'sort_order' => 3, 'description' => 'URLs รูปภาพ'],
            ['section_name' => 'media', 'field_name' => 'pdf_url', 'data_type' => 'TEXT', 'sort_order' => 4, 'description' => 'PDF โปรแกรม'],
            ['section_name' => 'media', 'field_name' => 'video_url', 'data_type' => 'TEXT', 'sort_order' => 5, 'description' => 'Video'],
            
            // ================== SECTION: seo ==================
            ['section_name' => 'seo', 'field_name' => 'slug', 'data_type' => 'TEXT', 'sort_order' => 1, 'description' => 'URL slug'],
            ['section_name' => 'seo', 'field_name' => 'meta_title', 'data_type' => 'TEXT', 'sort_order' => 2, 'description' => 'Meta title'],
            ['section_name' => 'seo', 'field_name' => 'meta_description', 'data_type' => 'TEXT', 'sort_order' => 3, 'description' => 'Meta description'],
            ['section_name' => 'seo', 'field_name' => 'keywords', 'data_type' => 'ARRAY_TEXT', 'sort_order' => 4, 'description' => 'Keywords'],
        ];

        foreach ($sections as $section) {
            $section['created_at'] = $now;
            $section['updated_at'] = $now;
            
            // Set defaults for optional fields
            $section['is_required'] = $section['is_required'] ?? false;
            $section['is_system'] = $section['is_system'] ?? false;
            $section['lookup_create_if_not_found'] = $section['lookup_create_if_not_found'] ?? false;
            
            DB::table('section_definitions')->updateOrInsert(
                ['section_name' => $section['section_name'], 'field_name' => $section['field_name']],
                $section
            );
        }

        $this->command->info('Section definitions seeded successfully!');
    }
}
