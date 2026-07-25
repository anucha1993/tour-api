<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domestic_tour_city_covers', function (Blueprint $table) {
            // Per-city hero text (overrides setting-level hero_text when city is selected)
            $table->string('hero_text', 255)->nullable()->after('alt_text');
            // Long-form intro HTML shown in the "เกี่ยวกับทัวร์<city>" section (thin-content / SEO fix)
            $table->text('intro_html')->nullable()->after('hero_text');
            // FAQ (array of {q,a}) for per-city AEO/GEO — rendered as FAQPage JSON-LD
            $table->json('faq')->nullable()->after('intro_html');
        });
    }

    public function down(): void
    {
        Schema::table('domestic_tour_city_covers', function (Blueprint $table) {
            $table->dropColumn(['hero_text', 'intro_html', 'faq']);
        });
    }
};
