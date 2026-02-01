<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wholesalers', function (Blueprint $table) {
            $table->id();
            
            // ข้อมูลพื้นฐาน
            $table->string('code', 50)->unique()->comment('รหัส Wholesale เช่น ZEGO, TOURKRUB');
            $table->string('name', 255)->comment('ชื่อบริษัท');
            $table->string('logo_url', 500)->nullable()->comment('URL โลโก้');
            $table->string('website', 255)->nullable()->comment('เว็บไซต์');
            $table->boolean('is_active')->default(true)->comment('สถานะเปิด/ปิดใช้งาน');
            $table->text('notes')->nullable()->comment('หมายเหตุภายใน');
            
            // ข้อมูลติดต่อ
            $table->string('contact_name', 255)->nullable()->comment('ชื่อผู้ติดต่อ');
            $table->string('contact_email', 255)->nullable()->comment('Email ติดต่อ');
            $table->string('contact_phone', 50)->nullable()->comment('เบอร์โทรติดต่อ');
            
            // ข้อมูลใบกำกับภาษี
            $table->string('tax_id', 20)->nullable()->index()->comment('เลขประจำตัวผู้เสียภาษี 13 หลัก');
            $table->string('company_name_th', 255)->nullable()->comment('ชื่อบริษัท ภาษาไทย');
            $table->string('company_name_en', 255)->nullable()->comment('ชื่อบริษัท English');
            $table->string('branch_code', 10)->default('00000')->comment('รหัสสาขา (00000 = สำนักงานใหญ่)');
            $table->string('branch_name', 100)->nullable()->comment('ชื่อสาขา');
            $table->text('address')->nullable()->comment('ที่อยู่เต็ม');
            $table->string('phone', 50)->nullable()->comment('เบอร์โทรบริษัท');
            $table->string('fax', 50)->nullable()->comment('เบอร์แฟกซ์');
            
            $table->timestamps();
            
            // Indexes
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesalers');
    }
};
