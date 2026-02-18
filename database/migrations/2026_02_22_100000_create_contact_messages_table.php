<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->enum('status', ['new', 'read', 'replied', 'archived'])->default('new');
            $table->text('admin_notes')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('contact_page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title')->default('ติดต่อเรา');
            $table->string('hero_subtitle')->nullable();
            $table->string('hero_image_url')->nullable();
            $table->text('intro_text')->nullable();
            $table->string('map_embed_url')->nullable();
            $table->string('office_name')->nullable();
            $table->text('office_address')->nullable();
            $table->string('office_lat')->nullable();
            $table->string('office_lng')->nullable();
            $table->boolean('show_map')->default(true);
            $table->boolean('show_form')->default(true);
            $table->boolean('is_active')->default(true);
            // SEO
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_page_settings');
        Schema::dropIfExists('contact_messages');
    }
};
