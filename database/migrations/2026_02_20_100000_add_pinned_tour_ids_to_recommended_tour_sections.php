<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommended_tour_sections', function (Blueprint $table) {
            $table->json('pinned_tour_ids')->nullable()->after('conditions')
                  ->comment('Manually selected tour IDs to always include');
        });
    }

    public function down(): void
    {
        Schema::table('recommended_tour_sections', function (Blueprint $table) {
            $table->dropColumn('pinned_tour_ids');
        });
    }
};
