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
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['code', 'image', 'timezone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('code', 10)->unique()->nullable()->after('id');
            $table->string('timezone', 50)->nullable()->after('country_id');
            $table->string('image', 500)->nullable()->after('timezone');
        });
    }
};
