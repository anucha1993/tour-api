<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->string('cover_image_url')->nullable()->after('description');
            $table->string('cover_image_cf_id')->nullable()->after('cover_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->dropColumn(['cover_image_url', 'cover_image_cf_id']);
        });
    }
};
