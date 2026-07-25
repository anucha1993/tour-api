<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('international_tour_country_covers', function (Blueprint $table) {
            // FAQ (array of {q,a}) for per-country AEO/GEO — rendered as FAQPage JSON-LD
            $table->json('faq')->nullable()->after('intro_html');
        });
    }

    public function down(): void
    {
        Schema::table('international_tour_country_covers', function (Blueprint $table) {
            $table->dropColumn('faq');
        });
    }
};
