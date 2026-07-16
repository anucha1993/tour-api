<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX (2026-07-16): Production log for 2026-07-15 showed SQLSTATE[22001]
 * "Data too long for column 'title'" on `tour_itineraries` inserts, e.g.
 * tour_id 56007 with a >255-char Chinese day-title.
 *
 * The active production schema still has title VARCHAR(255) (from
 * 2026_01_26_200001_create_tour_system_tables.php); the newer
 * 2026_01_28_040000_create_tour_itineraries_table.php was skipped because
 * the table already existed. TourItineraryController's validation already
 * allows max:500, so widen the column to 1000 to comfortably accommodate
 * long Thai/Chinese itinerary titles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_itineraries', function (Blueprint $table) {
            $table->string('title', 1000)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tour_itineraries', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->change();
        });
    }
};
