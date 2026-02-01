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
        // Modify ENUM to add 'fetch_periods', 'fetch_itineraries', and 'oauth_token'
        DB::statement("ALTER TABLE outbound_api_logs MODIFY COLUMN action ENUM(
            'fetch_tours', 'fetch_detail', 'fetch_periods', 'fetch_itineraries',
            'check_availability', 'hold', 'confirm', 'cancel', 'modify',
            'check_status', 'ack_sync', 'health_check', 'oauth_token'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original ENUM values
        DB::statement("ALTER TABLE outbound_api_logs MODIFY COLUMN action ENUM(
            'fetch_tours', 'fetch_detail', 'check_availability',
            'hold', 'confirm', 'cancel', 'modify', 'check_status',
            'ack_sync', 'health_check'
        )");
    }
};
