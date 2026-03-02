<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_reviews', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('helpful_count');
        });
    }

    public function down(): void
    {
        Schema::table('tour_reviews', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });
    }
};
