<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festival_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Holiday date range — used to match tour periods
            $table->date('start_date');
            $table->date('end_date');

            // Holiday card image (shown on festival listing)
            $table->text('image_url')->nullable();
            $table->string('image_cf_id')->nullable();

            // Cover image for the festival detail page hero
            $table->text('cover_image_url')->nullable();
            $table->string('cover_image_cf_id')->nullable();
            $table->string('cover_image_position', 50)->default('center');

            // Badge settings
            $table->string('badge_text', 100)->nullable();
            $table->string('badge_color', 50)->default('red');
            $table->string('badge_icon', 50)->nullable();
            $table->json('display_modes')->nullable(); // ['card','period']

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_holidays');
    }
};
