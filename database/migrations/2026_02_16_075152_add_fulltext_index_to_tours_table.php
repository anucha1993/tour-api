<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // FULLTEXT index on tours for fast search
        DB::statement('ALTER TABLE tours ADD FULLTEXT INDEX ft_tours_search (title, description, tour_code)');

        // Regular indexes for LIKE queries on names
        Schema::table('countries', function (Blueprint $table) {
            $table->index('name_th', 'idx_countries_name_th');
            $table->index('name_en', 'idx_countries_name_en');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->index('name_th', 'idx_cities_name_th');
            $table->index('name_en', 'idx_cities_name_en');
        });

        // Fix periods where available=0 but capacity>0 and booked=0
        DB::statement('UPDATE periods SET available = capacity - booked WHERE available = 0 AND booked = 0 AND capacity > 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tours DROP INDEX ft_tours_search');

        Schema::table('countries', function (Blueprint $table) {
            $table->dropIndex('idx_countries_name_th');
            $table->dropIndex('idx_countries_name_en');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex('idx_cities_name_th');
            $table->dropIndex('idx_cities_name_en');
        });
    }
};
