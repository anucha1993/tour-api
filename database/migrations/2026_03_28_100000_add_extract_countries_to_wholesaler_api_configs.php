<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->boolean('extract_countries_from_name')->default(false)->after('extract_cities_from_name')
                ->comment('Extract country names from tour name when API does not provide countries');
        });
    }

    public function down(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->dropColumn('extract_countries_from_name');
        });
    }
};
