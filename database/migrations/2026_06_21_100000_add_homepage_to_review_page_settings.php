<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_page_settings', function (Blueprint $table) {
            $table->boolean('homepage_enabled')->default(true)->after('is_active');
            $table->string('homepage_title')->default('รีวิวจากลูกค้า')->after('homepage_enabled');
            $table->string('homepage_subtitle')->nullable()->default('เสียงจากลูกค้าที่ไว้วางใจเดินทางกับเรา')->after('homepage_title');
            // 'latest' = แสดงรีวิวล่าสุด, 'manual' = เลือกรีวิวเฉพาะ
            $table->string('homepage_mode')->default('latest')->after('homepage_subtitle');
            $table->unsignedSmallInteger('homepage_limit')->default(10)->after('homepage_mode');
            $table->json('homepage_review_ids')->nullable()->after('homepage_limit');
        });
    }

    public function down(): void
    {
        Schema::table('review_page_settings', function (Blueprint $table) {
            $table->dropColumn([
                'homepage_enabled',
                'homepage_title',
                'homepage_subtitle',
                'homepage_mode',
                'homepage_limit',
                'homepage_review_ids',
            ]);
        });
    }
};
