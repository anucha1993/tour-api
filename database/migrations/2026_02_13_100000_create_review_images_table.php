<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_review_id')->constrained('tour_reviews')->cascadeOnDelete();
            $table->string('image_url');
            $table->string('thumbnail_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('tour_review_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_images');
    }
};
