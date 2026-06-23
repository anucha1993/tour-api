<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_requests', function (Blueprint $table) {
            $table->string('email', 191)->nullable()->after('phone_msisdn');
            $table->string('channel', 10)->default('phone')->after('email');
        });

        DB::statement("ALTER TABLE `otp_requests` MODIFY `phone_msisdn` VARCHAR(20) NULL");

        Schema::table('otp_requests', function (Blueprint $table) {
            $table->index(['email', 'purpose', 'verified'], 'otp_email_purpose_verified_idx');
        });
    }

    public function down(): void
    {
        Schema::table('otp_requests', function (Blueprint $table) {
            $table->dropIndex('otp_email_purpose_verified_idx');
            $table->dropColumn(['email', 'channel']);
        });

        DB::statement("ALTER TABLE `otp_requests` MODIFY `phone_msisdn` VARCHAR(20) NOT NULL");
    }
};
