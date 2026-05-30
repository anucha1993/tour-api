<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE `otp_requests` MODIFY `purpose` ENUM('register','login','reset_password','verify_phone','booking') NOT NULL DEFAULT 'register'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `otp_requests` MODIFY `purpose` ENUM('register','login','reset_password','verify_phone') NOT NULL DEFAULT 'register'");
    }
};
