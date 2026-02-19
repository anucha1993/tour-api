<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_notifications', function (Blueprint $table) {
            $table->unsignedInteger('max_claims')->nullable()->after('how_to_use')
                ->comment('จำนวนสิทธิ์สูงสุด null = ไม่จำกัด');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_notifications', function (Blueprint $table) {
            $table->dropColumn('max_claims');
        });
    }
};
