<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * เพิ่ม fields สำหรับ track การ sync จาก Wholesaler API
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // 1. Data Source - แหล่งที่มาของข้อมูล
            if (!Schema::hasColumn('tours', 'data_source')) {
                $table->enum('data_source', ['api', 'manual'])
                    ->default('manual')
                    ->after('wholesaler_id')
                    ->comment('api = มาจาก Wholesaler API, manual = สร้างเอง');
            }

            // 2. Sync Status - สถานะการ sync
            if (!Schema::hasColumn('tours', 'sync_status')) {
                $table->enum('sync_status', ['active', 'paused', 'disconnected'])
                    ->default('active')
                    ->after('data_source')
                    ->comment('active = กำลัง sync, paused = หยุดชั่วคราว, disconnected = ไม่ sync');
            }

            // 3. Sync Lock - ป้องกันการแก้ไข
            if (!Schema::hasColumn('tours', 'sync_locked')) {
                $table->boolean('sync_locked')
                    ->default(false)
                    ->after('sync_status')
                    ->comment('true = ห้ามแก้ไข fields ที่ sync มา');
            }

            // 4. Last Synced At - เวลา sync ล่าสุด
            if (!Schema::hasColumn('tours', 'last_synced_at')) {
                $table->timestamp('last_synced_at')
                    ->nullable()
                    ->after('sync_locked');
            }

            // 5. Sync Hash - hash ของข้อมูลเพื่อเปรียบเทียบว่าเปลี่ยนหรือไม่
            if (!Schema::hasColumn('tours', 'sync_hash')) {
                $table->string('sync_hash', 64)
                    ->nullable()
                    ->after('last_synced_at')
                    ->comment('MD5/SHA256 hash ของข้อมูลจาก API เพื่อตรวจสอบการเปลี่ยนแปลง');
            }

            // 6. External Updated At - เวลา update ล่าสุดจากฝั่ง Wholesaler
            if (!Schema::hasColumn('tours', 'external_updated_at')) {
                $table->timestamp('external_updated_at')
                    ->nullable()
                    ->after('sync_hash')
                    ->comment('updated_at จากฝั่ง Wholesaler API');
            }

            // 7. Manual Override Fields - fields ที่ถูกแก้ไขเอง ไม่ต้อง sync ทับ
            if (!Schema::hasColumn('tours', 'manual_override_fields')) {
                $table->json('manual_override_fields')
                    ->nullable()
                    ->after('external_updated_at')
                    ->comment('["title", "description"] = fields ที่แก้ไขเอง ไม่ให้ sync ทับ');
            }

            // Index for sync queries
            $table->index(['data_source', 'sync_status'], 'idx_tours_sync');
            $table->index(['wholesaler_id', 'external_id'], 'idx_tours_external');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_tours_sync');
            $table->dropIndex('idx_tours_external');

            // Drop columns
            $columns = [
                'data_source',
                'sync_status',
                'sync_locked',
                'last_synced_at',
                'sync_hash',
                'external_updated_at',
                'manual_override_fields',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('tours', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
