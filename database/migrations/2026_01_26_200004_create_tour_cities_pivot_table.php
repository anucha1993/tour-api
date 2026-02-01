<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Create tour_cities pivot table for multi-city tours
     */
    public function up(): void
    {
        Schema::create('tour_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('days_in_city')->nullable()->comment('จำนวนวันในเมืองนี้');
            $table->timestamp('created_at')->useCurrent();

            // Unique constraint: each tour-city pair is unique
            $table->unique(['tour_id', 'city_id']);
            
            // Index for queries
            $table->index(['city_id']);
            $table->index(['country_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_cities');
    }
};
