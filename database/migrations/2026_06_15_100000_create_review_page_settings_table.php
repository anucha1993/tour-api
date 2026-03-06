<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title')->default('รีวิวจากลูกค้า');
            $table->text('hero_subtitle')->nullable()->default('เสียงจากลูกค้าที่ไว้วางใจเดินทางกับเรา อ่านประสบการณ์จริงจากผู้เดินทาง');
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
        Schema::dropIfExists('review_page_settings');
    }
};
