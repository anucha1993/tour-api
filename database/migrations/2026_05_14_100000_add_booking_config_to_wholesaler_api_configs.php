<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            // Booking provider — short code used to resolve adapter class
            // e.g. 'zego', 'headcode', 'generic_rest', 'manual', or null
            $table->string('booking_provider', 50)->nullable()->after('headcode_file');

            // Enable/disable booking outbound API for this integration
            $table->boolean('booking_enabled')->default(false)->after('booking_provider');

            // Encrypted JSON — provider-specific credentials & settings.
            // Schema is declared by each adapter's getConfigSchema().
            $table->text('booking_config')->nullable()->after('booking_enabled');

            // Pseudo-hold TTL — used by providers that don't reserve seats
            // server-side (e.g. Zego). Acts as UX countdown + cache expiry.
            $table->unsignedInteger('booking_hold_ttl_seconds')->default(900)->after('booking_config');
        });
    }

    public function down(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->dropColumn([
                'booking_provider',
                'booking_enabled',
                'booking_config',
                'booking_hold_ttl_seconds',
            ]);
        });
    }
};
