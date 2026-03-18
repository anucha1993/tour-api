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
        DB::statement("ALTER TABLE tours MODIFY COLUMN status ENUM('draft', 'active', 'inactive', 'closed') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tours MODIFY COLUMN status ENUM('draft', 'active', 'inactive') NOT NULL DEFAULT 'draft'");
    }
};
