<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number', 30)->unique();
            $table->unsignedBigInteger('web_member_id');
            $table->unsignedBigInteger('tour_id')->nullable();
            $table->unsignedBigInteger('period_id')->nullable();

            // Customer request
            $table->string('customer_name', 200);
            $table->string('customer_phone', 50);
            $table->string('customer_email', 150)->nullable();
            $table->unsignedSmallInteger('pax_adult')->default(1);
            $table->unsignedSmallInteger('pax_child')->default(0);
            $table->unsignedSmallInteger('pax_infant')->default(0);
            $table->string('travel_date_preference', 200)->nullable();
            $table->text('notes')->nullable();

            // Quotation details (filled by admin)
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->json('items')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->text('admin_notes')->nullable();

            // Workflow
            $table->enum('status', [
                'requested', 'draft', 'sent', 'accepted', 'declined', 'expired', 'cancelled',
            ])->default('requested');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->unsignedBigInteger('converted_booking_id')->nullable();
            $table->unsignedBigInteger('handled_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('web_member_id');
            $table->index('status');
            $table->index('tour_id');
            $table->index('handled_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
