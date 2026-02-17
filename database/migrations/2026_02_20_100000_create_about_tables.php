<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // About Page Settings (singleton - 1 row)
        Schema::create('about_page_settings', function (Blueprint $table) {
            $table->id();

            // Hero section
            $table->string('hero_title')->default('เกี่ยวกับเรา');
            $table->string('hero_subtitle')->nullable();
            $table->string('hero_image_url')->nullable();
            $table->string('hero_image_cf_id')->nullable();
            $table->string('hero_image_position')->default('center');

            // About section
            $table->string('about_title')->default('เกี่ยวกับ เน็กซ์ ทริป ฮอลิเดย์');
            $table->longText('about_content')->nullable();
            $table->json('highlights')->nullable(); // [{label, value, suffix}] e.g. [{label: "ปีประสบการณ์", value: "15", suffix: "+"}]
            $table->json('value_props')->nullable(); // ["มั่นใจในราคาที่ถูก", "ส่วนลดพิเศษ", ...]

            // Company registration info
            $table->string('company_name')->nullable();
            $table->string('registration_no')->nullable(); // ทะเบียนพาณิชย์
            $table->string('capital')->nullable(); // ทุนจดทะเบียน
            $table->string('vat_no')->nullable(); // ภ.พ.20
            $table->string('tat_license')->nullable(); // ใบอนุญาต TAT
            $table->text('company_info_extra')->nullable(); // ข้อมูลเพิ่มเติม

            // License image
            $table->string('license_image_url')->nullable();
            $table->string('license_image_cf_id')->nullable();

            // SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Associations (สมาคม)
        Schema::create('about_associations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('license_no')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('logo_cf_id')->nullable();
            $table->string('website_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Services (บริการหลัก)
        Schema::create('about_services', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // lucide icon name or emoji
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Customer Groups (กลุ่มลูกค้าหลัก)
        Schema::create('about_customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('image_url')->nullable();
            $table->string('image_cf_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Awards (รางวัล)
        Schema::create('about_awards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('year')->nullable();
            $table->string('image_url')->nullable();
            $table->string('image_cf_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_awards');
        Schema::dropIfExists('about_customer_groups');
        Schema::dropIfExists('about_services');
        Schema::dropIfExists('about_associations');
        Schema::dropIfExists('about_page_settings');
    }
};
