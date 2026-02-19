<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add how_to_use to promotion_notifications
        Schema::table('promotion_notifications', function (Blueprint $table) {
            $table->text('how_to_use')->nullable()->after('description');
        });

        // Add claim_code to member_promotion_claims
        Schema::table('member_promotion_claims', function (Blueprint $table) {
            $table->string('claim_code', 12)->nullable()->unique()->after('notification_id');
        });
    }

    public function down(): void
    {
        Schema::table('member_promotion_claims', function (Blueprint $table) {
            $table->dropColumn('claim_code');
        });

        Schema::table('promotion_notifications', function (Blueprint $table) {
            $table->dropColumn('how_to_use');
        });
    }
};
