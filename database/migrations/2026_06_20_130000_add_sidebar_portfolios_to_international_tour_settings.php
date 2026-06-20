<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->boolean('sidebar_show_portfolios')->default(false)->after('sidebar_show_contact');
            $table->unsignedSmallInteger('sidebar_portfolios_limit')->default(3)->after('sidebar_show_portfolios');
            $table->string('sidebar_portfolios_title', 100)->default('ผลงานที่ผ่านมา')->after('sidebar_portfolios_limit');
        });
    }

    public function down(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->dropColumn([
                'sidebar_show_portfolios',
                'sidebar_portfolios_limit',
                'sidebar_portfolios_title',
            ]);
        });
    }
};
