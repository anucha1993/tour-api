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
        Schema::create('web_members', function (Blueprint $table) {
            $table->id();
            
            // Basic info
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone', 20)->unique(); // เก็บแบบ 66xxxxxxxxx
            $table->string('password');
            
            // Verification status
            $table->boolean('email_verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('phone_verified')->default(false);
            $table->timestamp('phone_verified_at')->nullable();
            
            // Consent tracking (PDPA)
            $table->boolean('consent_marketing')->default(false); // รับข่าวสาร
            $table->boolean('consent_terms')->default(false); // ยอมรับ Terms
            $table->boolean('consent_privacy')->default(false); // ยอมรับ Privacy Policy
            $table->timestamp('consent_at')->nullable();
            
            // Account status
            $table->enum('status', ['pending', 'active', 'suspended', 'deleted'])->default('pending');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            
            // Profile
            $table->string('avatar')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            
            // Security
            $table->string('remember_token', 100)->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index('phone');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_members');
    }
};
