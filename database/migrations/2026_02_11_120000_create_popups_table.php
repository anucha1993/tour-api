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
        Schema::create('popups', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('cloudflare_id')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('alt_text', 255)->nullable()->comment('Alt Text สำหรับ SEO');
            $table->string('button_text', 100)->nullable()->comment('ข้อความปุ่ม เช่น ดูเพิ่มเติม');
            $table->string('button_link', 500)->nullable()->comment('ลิงก์ปุ่ม');
            $table->string('button_color', 20)->default('primary')->comment('สีปุ่ม');
            $table->enum('popup_type', ['image', 'content', 'promo', 'newsletter', 'announcement'])->default('image');
            $table->enum('display_frequency', ['always', 'once_per_session', 'once_per_day', 'once_per_week', 'once'])->default('once_per_session');
            $table->unsignedInteger('delay_seconds')->default(2)->comment('แสดงหลังเปิดหน้าเว็บกี่วินาที');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_close_button')->default(true);
            $table->boolean('close_on_overlay')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('popups');
    }
};
