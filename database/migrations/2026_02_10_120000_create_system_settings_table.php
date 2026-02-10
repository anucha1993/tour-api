<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json, array
            $table->string('group')->default('general'); // general, sync, auto_close, etc.
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        $defaultSettings = [
            // Smart Sync Settings
            [
                'key' => 'sync.respect_manual_overrides',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'sync',
                'description' => 'เคารพการแก้ไขด้วยมือ - ไม่เขียนทับฟิลด์ที่ Admin แก้ไขแล้ว',
            ],
            [
                'key' => 'sync.always_sync_fields',
                'value' => json_encode(['cover_image_url', 'pdf_url', 'og_image_url', 'docx_url']),
                'type' => 'json',
                'group' => 'sync',
                'description' => 'ฟิลด์ที่ Sync ทุกครั้ง ไม่สนใจ manual override',
            ],
            [
                'key' => 'sync.never_sync_fields',
                'value' => json_encode(['status']),
                'type' => 'json',
                'group' => 'sync',
                'description' => 'ฟิลด์ที่ไม่ Sync เลย',
            ],
            [
                'key' => 'sync.skip_past_periods',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'sync',
                'description' => 'ข้ามรอบเดินทางที่ผ่านไปแล้วตอน Sync',
            ],
            [
                'key' => 'sync.skip_disabled_tours',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'sync',
                'description' => 'ข้ามทัวร์ที่ปิดใช้งาน (disabled/closed)',
            ],
            
            // Auto-Close Settings
            [
                'key' => 'auto_close.enabled',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'auto_close',
                'description' => 'เปิดใช้งานระบบปิดอัตโนมัติ',
            ],
            [
                'key' => 'auto_close.periods',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'auto_close',
                'description' => 'ปิดรอบเดินทางที่หมดอายุอัตโนมัติ',
            ],
            [
                'key' => 'auto_close.tours',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'auto_close',
                'description' => 'ปิดทัวร์เมื่อไม่มีรอบเดินทางที่เปิดอยู่',
            ],
            [
                'key' => 'auto_close.threshold_days',
                'value' => '0',
                'type' => 'integer',
                'group' => 'auto_close',
                'description' => 'จำนวนวันก่อนวัน start_date ที่ถือว่าหมดอายุ (0 = วันนี้)',
            ],
            [
                'key' => 'auto_close.run_time',
                'value' => '01:00',
                'type' => 'string',
                'group' => 'auto_close',
                'description' => 'เวลาที่รัน Auto-Close (HH:MM)',
            ],
        ];

        foreach ($defaultSettings as $setting) {
            DB::table('system_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
