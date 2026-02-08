<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * เปลี่ยน error_type จาก enum เป็น varchar เพื่อรองรับค่าใหม่ได้ง่ายขึ้น
     */
    public function up(): void
    {
        // MySQL: Change enum to varchar for flexibility
        DB::statement("ALTER TABLE sync_error_logs MODIFY COLUMN error_type VARCHAR(50) DEFAULT 'unknown'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum (optional - may lose data if new values exist)
        DB::statement("ALTER TABLE sync_error_logs MODIFY COLUMN error_type ENUM('mapping', 'validation', 'lookup', 'type_cast', 'api', 'database', 'unknown') DEFAULT 'unknown'");
    }
};
