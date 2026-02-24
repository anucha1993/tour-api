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
        Schema::create('international_tour_country_covers', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('setting_id')
                ->constrained('international_tour_settings')
                ->onDelete('cascade');
            
            $table->foreignId('country_id')
                ->constrained('countries')
                ->onDelete('cascade');
            
            // Cover image data
            $table->string('image_url')->nullable()->comment('Cover image URL');
            $table->string('cloudflare_id')->nullable()->comment('Cloudflare image ID');
            $table->string('image_position')->default('center')->comment('Image crop position');
            $table->string('alt_text')->nullable()->comment('Alt text for SEO');
            
            // Sorting
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            
            // Unique constraint - one cover per country per setting
            $table->unique(['setting_id', 'country_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('international_tour_country_covers');
    }
};
