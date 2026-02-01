<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * - Rename country_id to primary_country_id
     * - Create tour_countries pivot table for multi-country tours
     */
    public function up(): void
    {
        // Step 1: Create tour_countries pivot table
        Schema::create('tour_countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('days_in_country')->nullable()->comment('จำนวนวันในประเทศนี้');
            $table->timestamp('created_at')->useCurrent();

            // Unique constraint: each tour-country pair is unique
            $table->unique(['tour_id', 'country_id']);
            
            // Index for queries like "find tours that visit France"
            $table->index(['country_id', 'is_primary']);
        });

        // Step 2: Rename country_id to primary_country_id in tours table
        Schema::table('tours', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign(['country_id']);
        });

        Schema::table('tours', function (Blueprint $table) {
            // Rename the column
            $table->renameColumn('country_id', 'primary_country_id');
        });

        Schema::table('tours', function (Blueprint $table) {
            // Re-add the foreign key
            $table->foreign('primary_country_id')->references('id')->on('countries')->nullOnDelete();
        });

        // Step 3: Migrate existing data - copy country_id to tour_countries as primary
        DB::statement('
            INSERT INTO tour_countries (tour_id, country_id, is_primary, sort_order)
            SELECT id, primary_country_id, 1, 0
            FROM tours
            WHERE primary_country_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated data
        DB::table('tour_countries')->truncate();

        // Rename back
        Schema::table('tours', function (Blueprint $table) {
            $table->dropForeign(['primary_country_id']);
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->renameColumn('primary_country_id', 'country_id');
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
        });

        // Drop pivot table
        Schema::dropIfExists('tour_countries');
    }
};
