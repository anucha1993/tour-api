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
            // Store enabled field keys as JSON array
            // Example: ["tour.title", "tour.duration_days", "pricing.price_adult"]
            $table->json('enabled_fields')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->dropColumn('enabled_fields');
        });
    }
};
