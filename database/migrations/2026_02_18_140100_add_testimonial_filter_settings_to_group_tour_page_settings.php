<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_tour_page_settings', function (Blueprint $table) {
            $table->json('testimonial_tour_types')->nullable()->after('testimonial_show_section'); // e.g. ["private","corporate"]
            $table->string('testimonial_sort_by')->default('newest')->after('testimonial_tour_types'); // newest, oldest, rating_high, rating_low, featured
            $table->unsignedInteger('testimonial_min_rating')->default(1)->after('testimonial_sort_by'); // minimum rating to display
        });
    }

    public function down(): void
    {
        Schema::table('group_tour_page_settings', function (Blueprint $table) {
            $table->dropColumn(['testimonial_tour_types', 'testimonial_sort_by', 'testimonial_min_rating']);
        });
    }
};
