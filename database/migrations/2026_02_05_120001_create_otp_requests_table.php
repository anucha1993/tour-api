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
        Schema::create('otp_requests', function (Blueprint $table) {
            $table->id();
            
            // Phone number (MSISDN format: 66xxxxxxxxx)
            $table->string('phone_msisdn', 20);
            
            // ThaiBulkSMS response
            $table->string('message_id')->nullable(); // อ้างอิงจาก API
            
            // OTP settings
            $table->integer('ttl')->default(300); // อายุ OTP (วินาที)
            $table->timestamp('expires_at');
            
            // Purpose
            $table->enum('purpose', ['register', 'login', 'reset_password', 'verify_phone'])->default('register');
            
            // Verification tracking
            $table->integer('attempts')->default(0); // จำนวนครั้งที่ลองกรอก
            $table->integer('max_attempts')->default(5);
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            
            // Rate limiting
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            
            // Reference to member (if exists)
            $table->foreignId('web_member_id')->nullable()->constrained()->onDelete('cascade');
            
            $table->timestamps();
            
            // Indexes for lookups
            $table->index(['phone_msisdn', 'purpose', 'verified']);
            $table->index(['message_id']);
            $table->index(['expires_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_requests');
    }
};
