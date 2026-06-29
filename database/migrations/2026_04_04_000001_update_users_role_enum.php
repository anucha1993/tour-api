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
        // 1. ขยาย enum ให้รองรับทั้งค่าเก่าและใหม่ก่อน เพื่อให้ update ค่าได้
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','staff','sale','it') NOT NULL DEFAULT 'staff'");

        // 2. แปลงข้อมูลเดิม
        DB::table('users')->where('role', 'staff')->update(['role' => 'sale']);
        DB::table('users')->where('role', 'manager')->update(['role' => 'it']);

        // 3. ตัด enum ให้เหลือเฉพาะค่าใหม่
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','sale','it') NOT NULL DEFAULT 'sale'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. ขยาย enum ให้รองรับทั้งสองชุดค่า
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','staff','sale','it') NOT NULL DEFAULT 'staff'");

        // 2. แปลงข้อมูลกลับ
        DB::table('users')->where('role', 'sale')->update(['role' => 'staff']);
        DB::table('users')->where('role', 'it')->update(['role' => 'manager']);

        // 3. เปลี่ยน enum กลับเป็นค่าเดิม
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff'");
    }
};
