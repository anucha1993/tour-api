<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * เปลี่ยน role enum: admin,manager,staff → admin,sale,it
     * - manager → it
     * - staff   → sale
     */
    public function up(): void
    {
        // 1. แปลงข้อมูลเดิมก่อน
        DB::table('users')->where('role', 'staff')->update(['role' => 'sale']);
        DB::table('users')->where('role', 'manager')->update(['role' => 'it']);

        // 2. เปลี่ยน enum column
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','sale','it') NOT NULL DEFAULT 'sale'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. แปลงข้อมูลกลับ
        DB::table('users')->where('role', 'sale')->update(['role' => 'staff']);
        DB::table('users')->where('role', 'it')->update(['role' => 'manager']);

        // 2. เปลี่ยน enum กลับ
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff'");
    }
};
