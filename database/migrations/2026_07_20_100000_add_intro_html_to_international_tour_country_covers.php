<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('international_tour_country_covers', function (Blueprint $table) {
            // Long, per-country SEO description shown at the bottom of
            // /tours/country/{slug}. Stored as HTML (a few <p> paragraphs).
            $table->text('intro_html')->nullable()->after('hero_text');
        });
    }

    public function down(): void
    {
        Schema::table('international_tour_country_covers', function (Blueprint $table) {
            $table->dropColumn('intro_html');
        });
    }
};
