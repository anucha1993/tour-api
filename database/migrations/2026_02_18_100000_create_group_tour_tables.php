<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Page settings (single row)
        Schema::create('group_tour_page_settings', function (Blueprint $table) {
            $table->id();
            // Hero
            $table->string('hero_title')->default('รับจัดกรุ๊ปทัวร์ ครบวงจร');
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_image_url')->nullable();
            $table->string('hero_image_cf_id')->nullable();
            $table->string('hero_image_position')->default('center');
            // Stats bar
            $table->json('stats')->nullable(); // [{icon, value, label}]
            // Group types
            $table->json('group_types')->nullable(); // [{icon, title, description}]
            // Advantages
            $table->string('advantages_title')->default('ทำไมต้องเลือกเรา');
            $table->string('advantages_image_url')->nullable();
            $table->string('advantages_image_cf_id')->nullable();
            $table->json('advantages')->nullable(); // [{text}]
            // Process steps
            $table->json('process_steps')->nullable(); // [{step_number, title, description}]
            // FAQ
            $table->json('faqs')->nullable(); // [{question, answer}]
            // CTA / Contact info
            $table->string('cta_title')->default('สนใจจัดกรุ๊ปทัวร์?');
            $table->text('cta_description')->nullable();
            $table->string('cta_phone')->nullable();
            $table->string('cta_email')->nullable();
            $table->string('cta_line_id')->nullable();
            // SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Portfolios (past work gallery)
        Schema::create('group_tour_portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('caption')->nullable();
            $table->string('group_size')->nullable(); // e.g. "50 คน"
            $table->string('destination')->nullable();
            $table->string('image_url')->nullable();
            $table->string('image_cf_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Testimonials
        Schema::create('group_tour_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_position')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('logo_cf_id')->nullable();
            $table->text('content');
            $table->tinyInteger('rating')->default(5);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Inquiries (form submissions)
        Schema::create('group_tour_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('line_id')->nullable();
            $table->string('group_type')->nullable();
            $table->string('group_size')->nullable();
            $table->string('destination')->nullable();
            $table->date('travel_date_start')->nullable();
            $table->date('travel_date_end')->nullable();
            $table->text('details')->nullable();
            $table->enum('status', ['new', 'contacted', 'quoted', 'confirmed', 'cancelled'])->default('new');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_tour_inquiries');
        Schema::dropIfExists('group_tour_testimonials');
        Schema::dropIfExists('group_tour_portfolios');
        Schema::dropIfExists('group_tour_page_settings');
    }
};
