<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_reviews', function (Blueprint $table) {
            $table->string('program_name', 255)->nullable()->after('tour_id');
        });

        // Make tour_id nullable and keep reviews when referenced tour is deleted.
        DB::statement('ALTER TABLE tour_reviews DROP FOREIGN KEY tour_reviews_tour_id_foreign');
        DB::statement('ALTER TABLE tour_reviews MODIFY tour_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE tour_reviews ADD CONSTRAINT tour_reviews_tour_id_foreign FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tour_reviews DROP FOREIGN KEY tour_reviews_tour_id_foreign');

        // Remove orphaned rows before setting NOT NULL again.
        DB::statement('DELETE FROM tour_reviews WHERE tour_id IS NULL');

        DB::statement('ALTER TABLE tour_reviews MODIFY tour_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE tour_reviews ADD CONSTRAINT tour_reviews_tour_id_foreign FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE');

        Schema::table('tour_reviews', function (Blueprint $table) {
            $table->dropColumn('program_name');
        });
    }
};
