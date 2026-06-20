<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_page_settings', function (Blueprint $table) {
            $table->boolean('show_sidebar')->default(true)->after('is_active');
            $table->boolean('sidebar_show_author')->default(true)->after('show_sidebar');
            $table->boolean('sidebar_show_related_posts')->default(true)->after('sidebar_show_author');
            $table->boolean('sidebar_show_recent_posts')->default(false)->after('sidebar_show_related_posts');
            $table->boolean('sidebar_show_recommended_tours')->default(true)->after('sidebar_show_recent_posts');
            $table->boolean('sidebar_show_back_button')->default(true)->after('sidebar_show_recommended_tours');
            $table->unsignedSmallInteger('sidebar_related_posts_limit')->default(5)->after('sidebar_show_back_button');
            $table->unsignedSmallInteger('sidebar_recent_posts_limit')->default(5)->after('sidebar_related_posts_limit');
            $table->unsignedSmallInteger('sidebar_recommended_tours_limit')->default(3)->after('sidebar_recent_posts_limit');
            $table->string('sidebar_recommended_tours_title', 100)->default('โปรแกรมทัวร์แนะนำ')->after('sidebar_recommended_tours_limit');
            $table->string('sidebar_related_posts_title', 100)->default('บทความที่เกี่ยวข้อง')->after('sidebar_recommended_tours_title');
            $table->string('sidebar_recent_posts_title', 100)->default('บทความท่องเที่ยว')->after('sidebar_related_posts_title');
        });
    }

    public function down(): void
    {
        Schema::table('blog_page_settings', function (Blueprint $table) {
            $table->dropColumn([
                'show_sidebar',
                'sidebar_show_author',
                'sidebar_show_related_posts',
                'sidebar_show_recent_posts',
                'sidebar_show_recommended_tours',
                'sidebar_show_back_button',
                'sidebar_related_posts_limit',
                'sidebar_recent_posts_limit',
                'sidebar_recommended_tours_limit',
                'sidebar_recommended_tours_title',
                'sidebar_related_posts_title',
                'sidebar_recent_posts_title',
            ]);
        });
    }
};
