<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound bookings (admin test + future API-driven flows) may not have a
 * web_member_id at creation time. Relax the NOT NULL constraint.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('web_member_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('web_member_id')->nullable(false)->change();
        });
    }
};
