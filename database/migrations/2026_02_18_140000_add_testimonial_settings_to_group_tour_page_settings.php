<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_tour_page_settings', function (Blueprint $table) {
            $table->string('testimonial_title')->default('เสียงจากลูกค้า')->after('seo_keywords');
            $table->string('testimonial_subtitle')->nullable()->after('testimonial_title');
            $table->unsignedInteger('testimonial_limit')->default(6)->after('testimonial_subtitle');
            $table->json('testimonial_pinned_ids')->nullable()->after('testimonial_limit'); // pinned review IDs in display order
            $table->boolean('testimonial_show_section')->default(true)->after('testimonial_pinned_ids');
        });
    }

    public function down(): void
    {
        Schema::table('group_tour_page_settings', function (Blueprint $table) {
            $table->dropColumn([
                'testimonial_title',
                'testimonial_subtitle',
                'testimonial_limit',
                'testimonial_pinned_ids',
                'testimonial_show_section',
            ]);
        });
    }
};
