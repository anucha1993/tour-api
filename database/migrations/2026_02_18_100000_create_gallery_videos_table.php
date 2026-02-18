<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_videos', function (Blueprint $table) {
            $table->id();
            $table->string('video_url', 500);                          // YouTube / Vimeo / external URL
            $table->string('thumbnail_cloudflare_id', 255)->nullable(); // Cloudflare image ID for thumbnail
            $table->string('thumbnail_url', 500)->nullable();           // Thumbnail URL (uploaded to Cloudflare)
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['country_id', 'is_active']);
            $table->index(['city_id', 'is_active']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_videos');
    }
};
