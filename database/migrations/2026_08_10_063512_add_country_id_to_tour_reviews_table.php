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
        Schema::table('tour_reviews', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->after('tour_id')
                ->constrained('countries')
                ->nullOnDelete();
            $table->index('country_id', 'tour_reviews_country_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_reviews', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropIndex('tour_reviews_country_id_index');
            $table->dropColumn('country_id');
        });
    }
};
