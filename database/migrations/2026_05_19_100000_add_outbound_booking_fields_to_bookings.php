<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Outbound provider linkage
            $table->foreignId('integration_id')->nullable()->after('flash_sale_item_id')
                ->constrained('wholesaler_api_configs')->nullOnDelete();
            $table->string('provider', 50)->nullable()->after('integration_id');
            $table->string('provider_booking_ref', 100)->nullable()->after('provider');
            $table->string('provider_quote_ref', 100)->nullable()->after('provider_booking_ref');

            // Provider-side lifecycle (independent from customer-visible status)
            // values: quoted | held | confirmed | cancelled | failed
            $table->string('provider_status', 20)->nullable()->after('provider_quote_ref');
            $table->timestamp('hold_expires_at')->nullable()->after('provider_status');

            // Currency for multi-currency support
            $table->string('currency', 3)->default('THB')->after('total_amount');

            // Raw payload snapshot for debugging
            $table->json('provider_payload')->nullable()->after('admin_note');

            // Indexes
            $table->index('provider_status');
            $table->index('hold_expires_at');
            $table->index(['integration_id', 'provider_status']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['integration_id']);
            $table->dropIndex(['provider_status']);
            $table->dropIndex(['hold_expires_at']);
            $table->dropIndex(['integration_id', 'provider_status']);
            $table->dropColumn([
                'integration_id',
                'provider',
                'provider_booking_ref',
                'provider_quote_ref',
                'provider_status',
                'hold_expires_at',
                'currency',
                'provider_payload',
            ]);
        });
    }
};
