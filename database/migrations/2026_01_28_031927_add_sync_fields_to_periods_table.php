<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * เพิ่ม fields สำหรับ track การ sync รอบเดินทางจาก Wholesaler API
     */
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            // 1. Data Source - แหล่งที่มาของข้อมูล
            if (!Schema::hasColumn('periods', 'data_source')) {
                $table->enum('data_source', ['api', 'manual'])
                    ->default('manual')
                    ->after('tour_id')
                    ->comment('api = มาจาก Wholesaler API, manual = สร้างเอง');
            }

            // 2. Sync Status - สถานะการ sync
            if (!Schema::hasColumn('periods', 'sync_status')) {
                $table->enum('sync_status', ['active', 'paused', 'disconnected'])
                    ->default('active')
                    ->after('data_source')
                    ->comment('active = กำลัง sync, paused = หยุดชั่วคราว, disconnected = ไม่ sync');
            }

            // 3. Last Synced At - เวลา sync ล่าสุด
            if (!Schema::hasColumn('periods', 'last_synced_at')) {
                $table->timestamp('last_synced_at')
                    ->nullable()
                    ->after('sync_status');
            }

            // 4. Sync Hash - hash ของข้อมูลเพื่อเปรียบเทียบว่าเปลี่ยนหรือไม่
            if (!Schema::hasColumn('periods', 'sync_hash')) {
                $table->string('sync_hash', 64)
                    ->nullable()
                    ->after('last_synced_at')
                    ->comment('MD5/SHA256 hash ของข้อมูลจาก API เพื่อตรวจสอบการเปลี่ยนแปลง');
            }

            // 5. External Updated At - เวลา update ล่าสุดจากฝั่ง Wholesaler
            if (!Schema::hasColumn('periods', 'external_updated_at')) {
                $table->timestamp('external_updated_at')
                    ->nullable()
                    ->after('sync_hash')
                    ->comment('updated_at จากฝั่ง Wholesaler API');
            }

            // 6. Guarantee Status - สถานะยืนยันการเดินทาง
            if (!Schema::hasColumn('periods', 'guarantee_status')) {
                $table->enum('guarantee_status', ['pending', 'guaranteed', 'cancelled'])
                    ->default('pending')
                    ->after('status')
                    ->comment('pending = รอยืนยัน, guaranteed = ยืนยันเดินทาง, cancelled = ยกเลิก');
            }

            // Index for sync queries
            $table->index(['data_source', 'sync_status'], 'idx_periods_sync');
            $table->index(['tour_id', 'external_id'], 'idx_periods_external');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            // Drop indexes first
            try {
                $table->dropIndex('idx_periods_sync');
                $table->dropIndex('idx_periods_external');
            } catch (\Exception $e) {
                // Indexes might not exist
            }

            // Drop columns
            $columns = [
                'data_source',
                'sync_status',
                'last_synced_at',
                'sync_hash',
                'external_updated_at',
                'guarantee_status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('periods', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
