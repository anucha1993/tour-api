<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContactPageSetting;

class ContactPageSettingSeeder extends Seeder
{
    public function run(): void
    {
        ContactPageSetting::firstOrCreate([], [
            'hero_title'     => 'ติดต่อเรา',
            'hero_subtitle'  => 'มีคำถามหรือต้องการข้อมูลเพิ่มเติม? ทีมงานของเราพร้อมให้บริการคุณ',
            'intro_text'     => 'เรายินดีรับฟังทุกคำถามและข้อเสนอแนะจากคุณ ไม่ว่าจะเป็นเรื่องการจองทัวร์ สอบถามรายละเอียดโปรแกรม หรือต้องการคำแนะนำในการเดินทาง สามารถติดต่อเราได้ตามช่องทางด้านล่าง',
            'map_embed_url'  => null,
            'office_name'    => 'บริษัท เน็กซ์ ทริป ฮอลิเดย์ จำกัด',
            'office_address' => null,
            'show_map'       => true,
            'show_form'      => true,
            'is_active'      => true,
            'seo_title'      => 'ติดต่อเรา | Next Trip Holiday',
            'seo_description' => 'ติดต่อสอบถามข้อมูลทัวร์ จองทัวร์ หรือขอคำแนะนำการเดินทาง ทีมงาน Next Trip Holiday พร้อมให้บริการคุณ',
        ]);
    }
}
