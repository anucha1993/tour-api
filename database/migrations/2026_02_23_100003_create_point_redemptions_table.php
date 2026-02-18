<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('web_members')->onDelete('cascade');
            $table->foreignId('transaction_id')->nullable()->constrained('point_transactions')->nullOnDelete();

            $table->unsignedInteger('points_used');
            $table->decimal('discount_amount', 10, 2); // ส่วนลดที่ได้ (บาท)
            $table->decimal('redemption_rate', 8, 2)->default(1.00); // rate ที่ใช้ตอนแลก

            $table->string('booking_code')->nullable(); // reference ถ้ามี
            $table->enum('status', ['pending', 'applied', 'cancelled'])->default('pending');
            $table->string('note')->nullable();

            $table->timestamps();

            $table->index('member_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_redemptions');
    }
};
