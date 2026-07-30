<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the nexttrip-invoice side of a booking's lifecycle (its "invoice
 * quotation"), reported back via the invoice-status callback. Named with an
 * `invoice_` prefix to avoid any confusion with tour-api's own, unrelated
 * pre-sales `quotations` table (customer inquiry -> quotation -> booking).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_quotation_id')->nullable()->after('provider_quote_ref');
            $table->string('invoice_quotation_number', 50)->nullable()->after('invoice_quotation_id');
            $table->string('invoice_status', 30)->nullable()->after('invoice_quotation_number');
            $table->timestamp('invoice_status_updated_at')->nullable()->after('invoice_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_quotation_id',
                'invoice_quotation_number',
                'invoice_status',
                'invoice_status_updated_at',
            ]);
        });
    }
};
