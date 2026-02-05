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
        Schema::create('member_billing_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('web_members')->onDelete('cascade');
            $table->enum('type', ['personal', 'company'])->default('personal');
            $table->boolean('is_default')->default(false);
            
            // Personal fields
            $table->string('name')->nullable();
            
            // Company fields
            $table->string('company_name')->nullable();
            $table->string('tax_id', 13)->nullable();
            $table->string('branch_name')->nullable();
            
            // Common address fields
            $table->text('address');
            $table->string('sub_district', 100);
            $table->string('district', 100);
            $table->string('province', 100);
            $table->string('postal_code', 5);
            $table->string('phone', 20);
            $table->string('email')->nullable();
            
            $table->timestamps();
            
            // Index for faster lookups
            $table->index(['member_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_billing_addresses');
    }
};
