<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('banner_url')->nullable()->after('description');
            $table->string('cloudflare_id')->nullable()->after('banner_url');
            $table->string('link_url')->nullable()->after('cloudflare_id');
            $table->date('start_date')->nullable()->after('link_url');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('badge_text')->nullable()->after('end_date');
            $table->string('badge_color')->nullable()->after('badge_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn([
                'banner_url',
                'cloudflare_id',
                'link_url',
                'start_date',
                'end_date',
                'badge_text',
                'badge_color',
            ]);
        });
    }
};
