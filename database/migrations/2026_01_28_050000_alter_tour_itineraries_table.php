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
        Schema::table('tour_itineraries', function (Blueprint $table) {
            // Rename existing columns to match new schema
            $table->renameColumn('day_no', 'day_number');
            $table->renameColumn('hotel_name', 'accommodation');
            $table->renameColumn('meal_breakfast', 'has_breakfast');
            $table->renameColumn('meal_lunch', 'has_lunch');
            $table->renameColumn('meal_dinner', 'has_dinner');
        });

        Schema::table('tour_itineraries', function (Blueprint $table) {
            // Add new columns
            $table->json('places')->nullable()->after('description');
            $table->string('meals_note', 500)->nullable()->after('has_dinner');
            $table->json('images')->nullable()->after('hotel_star');
            $table->smallInteger('sort_order')->unsigned()->nullable()->after('images');
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_itineraries', function (Blueprint $table) {
            $table->dropColumn(['places', 'meals_note', 'images', 'sort_order', 'updated_at']);
        });

        Schema::table('tour_itineraries', function (Blueprint $table) {
            $table->renameColumn('day_number', 'day_no');
            $table->renameColumn('accommodation', 'hotel_name');
            $table->renameColumn('has_breakfast', 'meal_breakfast');
            $table->renameColumn('has_lunch', 'meal_lunch');
            $table->renameColumn('has_dinner', 'meal_dinner');
        });
    }
};
