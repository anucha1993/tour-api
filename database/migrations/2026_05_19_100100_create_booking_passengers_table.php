<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

            // Passenger type: adult | child | infant
            $table->string('type', 10);

            // Identity
            $table->string('title', 20)->nullable(); // Mr/Mrs/Ms/...
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('first_name_th', 100)->nullable();
            $table->string('last_name_th', 100)->nullable();

            $table->date('dob')->nullable();
            $table->string('gender', 10)->nullable(); // male/female/other

            // Travel document
            $table->string('passport_no', 50)->nullable();
            $table->string('nationality', 50)->nullable();
            $table->date('passport_expiry')->nullable();
            $table->date('passport_issue_date')->nullable();
            $table->string('passport_issue_country', 50)->nullable();

            // Misc
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('special_request')->nullable();
            $table->boolean('is_lead')->default(false);

            // Room assignment (optional)
            $table->string('room_type', 20)->nullable(); // single/double/twin/triple
            $table->unsignedSmallInteger('room_index')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'type']);
            $table->index('is_lead');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_passengers');
    }
};
