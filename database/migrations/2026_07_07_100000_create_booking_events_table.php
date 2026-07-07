<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

            // Where in the lifecycle this happened (quote, hold, confirm, cancel, outbound, note, other)
            $table->string('event_type', 32);

            // ok | failed | info | warning
            $table->string('status', 16)->default('info');

            // Optional short label like 'Zego', 'admin', 'system'
            $table->string('source', 32)->nullable();

            // Human-readable message shown in admin UI
            $table->string('message', 1000)->nullable();

            // Free-form structured details (request/response snippets, error codes, etc.)
            $table->json('payload')->nullable();

            // Who triggered it (admin User.id if applicable)
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['booking_id', 'created_at']);
            $table->index(['event_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
    }
};
