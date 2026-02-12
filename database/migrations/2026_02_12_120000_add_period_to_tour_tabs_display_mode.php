<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * เพิ่ม 'period' ใน enum display_mode ของ tour_tabs
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tour_tabs MODIFY COLUMN display_mode ENUM('tab', 'badge', 'both', 'period') NOT NULL DEFAULT 'tab'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tour_tabs MODIFY COLUMN display_mode ENUM('tab', 'badge', 'both') NOT NULL DEFAULT 'tab'");
    }
};
