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
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->boolean('notifications_enabled')->default(true)->after('aggregation_config');
            $table->json('notification_emails')->nullable()->after('notifications_enabled');
            $table->json('notification_types')->nullable()->after('notification_emails');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->dropColumn(['notifications_enabled', 'notification_emails', 'notification_types']);
        });
    }
};
