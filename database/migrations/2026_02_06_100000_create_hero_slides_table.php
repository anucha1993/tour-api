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
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('cloudflare_id')->nullable()->comment('Cloudflare Images ID');
            $table->string('url', 500)->comment('URL ของรูปภาพ');
            $table->string('thumbnail_url', 500)->nullable()->comment('URL ของ thumbnail');
            $table->string('filename')->comment('ชื่อไฟล์');
            $table->string('alt', 255)->nullable()->comment('Alt text สำหรับ SEO');
            $table->string('title', 255)->nullable()->comment('Title attribute');
            $table->string('subtitle', 500)->nullable()->comment('Subtitle/คำบรรยาย');
            $table->string('button_text', 100)->nullable()->comment('ข้อความปุ่ม');
            $table->string('button_link', 500)->nullable()->comment('ลิงก์ปุ่ม');
            $table->unsignedInteger('width')->default(0)->comment('ความกว้างรูป');
            $table->unsignedInteger('height')->default(0)->comment('ความสูงรูป');
            $table->unsignedBigInteger('file_size')->default(0)->comment('ขนาดไฟล์ (bytes)');
            $table->boolean('is_active')->default(true)->comment('เปิดใช้งาน');
            $table->unsignedInteger('sort_order')->default(0)->comment('ลำดับการแสดง');
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
