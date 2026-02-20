<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tours MODIFY COLUMN pdf_source ENUM('api', 'custom', 'generate') NOT NULL DEFAULT 'api'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tours MODIFY COLUMN pdf_source ENUM('api', 'custom') NOT NULL DEFAULT 'api'");
    }
};
