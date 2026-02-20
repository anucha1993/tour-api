<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 20)->unique();

            // References
            $table->foreignId('web_member_id')->constrained('web_members')->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->foreignId('flash_sale_item_id')->nullable()->constrained('flash_sale_items')->nullOnDelete();

            // Quantities
            $table->unsignedSmallInteger('qty_adult')->default(1);
            $table->unsignedSmallInteger('qty_adult_single')->default(0);
            $table->unsignedSmallInteger('qty_child_bed')->default(0);
            $table->unsignedSmallInteger('qty_child_nobed')->default(0);

            // Prices (snapshot at time of booking)
            $table->decimal('price_adult', 12, 2)->default(0);
            $table->decimal('price_single', 12, 2)->default(0);
            $table->decimal('price_child_bed', 12, 2)->default(0);
            $table->decimal('price_child_nobed', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            // Customer info (snapshot)
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255);
            $table->string('phone', 20);

            // Extras
            $table->string('sale_code', 50)->nullable();
            $table->text('special_request')->nullable();

            // Status & Source
            $table->enum('status', ['pending', 'confirmed', 'paid', 'cancelled', 'completed'])->default('pending');
            $table->enum('source', ['website', 'flash_sale'])->default('website');

            // Admin notes
            $table->text('admin_note')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('source');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
