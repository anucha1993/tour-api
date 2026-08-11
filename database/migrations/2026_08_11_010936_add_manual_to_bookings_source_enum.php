<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Extend the `source` enum to allow 'manual' (admin-created bookings).
        // The BookingController::store() sets source='manual' for admin-side
        // creations, which previously triggered SQL "Data truncated for column
        // 'source'" because the enum was defined as ('website', 'flash_sale').
        DB::statement("ALTER TABLE `bookings` MODIFY COLUMN `source` ENUM('website', 'flash_sale', 'manual') NOT NULL DEFAULT 'website'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Any existing 'manual' rows would break the reverse migration — move them
        // to 'website' first so ALTER can succeed.
        DB::statement("UPDATE `bookings` SET `source` = 'website' WHERE `source` = 'manual'");
        DB::statement("ALTER TABLE `bookings` MODIFY COLUMN `source` ENUM('website', 'flash_sale') NOT NULL DEFAULT 'website'");
    }
};

