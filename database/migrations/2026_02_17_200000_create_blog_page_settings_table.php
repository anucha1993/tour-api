<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title')->default('รอบรู้เรื่องเที่ยว');
            $table->text('hero_subtitle')->nullable()->default('บทความท่องเที่ยว เคล็ดลับ และแรงบันดาลใจสำหรับการเดินทาง');
            $table->string('hero_image_url')->nullable();
            $table->string('hero_image_cf_id')->nullable();
            $table->string('hero_image_position')->default('center');
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_page_settings');
    }
};
