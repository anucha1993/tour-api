<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_tour_portfolios', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('image_cf_id');
            $table->string('logo_cf_id')->nullable()->after('logo_url');
            $table->string('group_type')->nullable()->after('logo_cf_id'); // private, corporate
        });
    }

    public function down(): void
    {
        Schema::table('group_tour_portfolios', function (Blueprint $table) {
            $table->dropColumn(['logo_url', 'logo_cf_id', 'group_type']);
        });
    }
};
