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
        // Partner API Keys
        Schema::create('partner_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();
            $table->string('api_key', 64)->unique()->comment('Public key');
            $table->string('api_secret', 128)->comment('Secret สำหรับ signature');
            $table->string('name', 100)->nullable()->comment('Production, Test, etc.');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('wholesaler_id');
            $table->index('is_active');
        });

        // Partner IP Whitelist
        Schema::create('partner_ip_whitelist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();
            $table->string('ip_address', 45)->comment('IPv4 หรือ IPv6');
            $table->string('description', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('wholesaler_id');
            $table->unique(['wholesaler_id', 'ip_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_ip_whitelist');
        Schema::dropIfExists('partner_api_keys');
    }
};
