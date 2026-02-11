<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();
            $table->string('page_slug')->unique(); // 'global', 'home', 'tours', 'promotions', etc.
            $table->string('page_name'); // Display name
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('og_image_cloudflare_id')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->text('structured_data')->nullable(); // JSON-LD schema
            $table->text('custom_head_tags')->nullable(); // Custom tags for <head>
            $table->timestamps();
        });

        // Site-wide contact & social media info
        Schema::create('site_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // phone, hotline, line_id, email, etc.
            $table->string('label'); // Display label
            $table->text('value'); // Actual value
            $table->string('icon')->nullable(); // Icon name
            $table->string('url')->nullable(); // Click URL (tel:, mailto:, https://)
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('group')->default('contact'); // contact, social, business_hours
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_contacts');
        Schema::dropIfExists('seo_settings');
    }
};
