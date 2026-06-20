<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->boolean('show_sidebar')->default(true)->after('is_active');
            $table->boolean('sidebar_show_blog_posts')->default(true)->after('show_sidebar');
            $table->boolean('sidebar_show_popular_tours')->default(true)->after('sidebar_show_blog_posts');
            $table->boolean('sidebar_show_contact')->default(true)->after('sidebar_show_popular_tours');
            $table->unsignedSmallInteger('sidebar_blog_posts_limit')->default(5)->after('sidebar_show_contact');
            $table->unsignedSmallInteger('sidebar_popular_tours_limit')->default(3)->after('sidebar_blog_posts_limit');
            $table->string('sidebar_blog_posts_title', 100)->default('บทความท่องเที่ยว')->after('sidebar_popular_tours_limit');
            $table->string('sidebar_popular_tours_title', 100)->default('ทัวร์ยอดนิยม')->after('sidebar_blog_posts_title');
            $table->string('sidebar_contact_title', 100)->default('ติดต่อสอบถาม')->after('sidebar_popular_tours_title');
            $table->string('sidebar_contact_phone', 50)->nullable()->after('sidebar_contact_title');
            $table->string('sidebar_contact_line', 100)->nullable()->after('sidebar_contact_phone');
            $table->string('sidebar_contact_text', 255)->nullable()->after('sidebar_contact_line');
        });
    }

    public function down(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->dropColumn([
                'show_sidebar',
                'sidebar_show_blog_posts',
                'sidebar_show_popular_tours',
                'sidebar_show_contact',
                'sidebar_blog_posts_limit',
                'sidebar_popular_tours_limit',
                'sidebar_blog_posts_title',
                'sidebar_popular_tours_title',
                'sidebar_contact_title',
                'sidebar_contact_phone',
                'sidebar_contact_line',
                'sidebar_contact_text',
            ]);
        });
    }
};
