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
        // Main settings table
        Schema::create('popular_country_settings', function (Blueprint $table) {
            $table->id();
            
            // Display info
            $table->string('name')->comment('Display name for this setting');
            $table->string('slug')->unique()->comment('Unique identifier');
            $table->text('description')->nullable();
            
            // Country selection mode
            $table->enum('selection_mode', ['auto', 'manual'])->default('auto')
                ->comment('auto: Based on tour count, manual: Specific countries');
            
            // Filter conditions (stored as JSON)
            $table->json('filters')->nullable()->comment('Filter conditions: wholesaler, price_range, themes, etc.');
            
            // Tour conditions (base filters for tours)
            $table->json('tour_conditions')->nullable()->comment('Base tour conditions to count');
            
            // Display settings
            $table->integer('display_count')->default(6)->comment('Number of countries to display');
            $table->integer('min_tour_count')->default(1)->comment('Minimum tours required to show country');
            
            // Sorting
            $table->enum('sort_by', ['tour_count', 'name', 'manual'])->default('tour_count');
            $table->enum('sort_direction', ['asc', 'desc'])->default('desc');
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            
            // Cache settings
            $table->integer('cache_minutes')->default(60)->comment('Cache duration in minutes');
            $table->timestamp('last_cached_at')->nullable();
            
            $table->timestamps();
        });

        // Pivot table for manual country selection with custom display data
        Schema::create('popular_country_items', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('setting_id')
                ->constrained('popular_country_settings')
                ->onDelete('cascade');
            
            $table->foreignId('country_id')
                ->constrained('countries')
                ->onDelete('cascade');
            
            // Custom display data per country
            $table->string('image_url')->nullable()->comment('Custom image URL for this country');
            $table->string('cloudflare_id')->nullable()->comment('Cloudflare image ID');
            $table->string('alt_text')->nullable()->comment('Alt text for SEO');
            $table->string('title')->nullable()->comment('Title attribute');
            $table->string('subtitle')->nullable()->comment('Subtitle / description');
            $table->string('link_url')->nullable()->comment('Custom link URL');
            
            // Override display name
            $table->string('display_name')->nullable()->comment('Override country name');
            
            // Sorting
            $table->integer('sort_order')->default(0);
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['setting_id', 'country_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('popular_country_items');
        Schema::dropIfExists('popular_country_settings');
    }
};
