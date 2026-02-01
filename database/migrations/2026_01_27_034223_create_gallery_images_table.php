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
        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            
            // Cloudflare Images
            $table->string('cloudflare_id')->nullable()->comment('Cloudflare Image ID');
            $table->string('url', 500)->comment('Full image URL');
            $table->string('thumbnail_url', 500)->nullable()->comment('Thumbnail URL (400x267)');
            
            // Image metadata
            $table->string('filename')->comment('Original filename');
            $table->string('alt', 255)->nullable()->comment('Alt text for SEO');
            $table->string('caption', 500)->nullable()->comment('Image caption');
            
            // Categorization
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->json('tags')->nullable()->comment('Tags for matching: ["ซากุระ", "ฟูจิ", "วัด"]');
            
            // Image specs
            $table->unsignedInteger('width')->default(1200)->comment('Image width in px');
            $table->unsignedInteger('height')->default(800)->comment('Image height in px');
            $table->unsignedInteger('file_size')->nullable()->comment('File size in bytes');
            
            // Status & sorting
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['country_id', 'is_active']);
            $table->index(['city_id', 'is_active']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_images');
    }
};
