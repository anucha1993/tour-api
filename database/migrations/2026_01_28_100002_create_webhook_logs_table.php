<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * สร้างตาราง webhook_logs สำหรับรับ Callback จาก Wholesaler
     */
    public function up(): void
    {
        // Webhook Logs - รับ callback จาก Wholesaler
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();

            // Request Info
            $table->string('webhook_id', 100)->unique()->nullable()->comment('ID จาก Wholesaler');
            $table->string('event_type', 100)->comment('tour.created, tour.updated, booking.confirmed, etc.');
            $table->string('source_ip', 45)->nullable();

            // Payload
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->string('signature', 200)->nullable()->comment('Signature สำหรับ verify');
            $table->boolean('signature_valid')->nullable();

            // Processing
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'ignored'])->default('pending');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->integer('processing_time_ms')->nullable();

            // Result
            $table->integer('tours_affected')->default(0);
            $table->integer('periods_affected')->default(0);
            $table->text('result_message')->nullable();

            // Error
            $table->text('error_message')->nullable();
            $table->text('error_trace')->nullable();

            // Retry
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();

            $table->timestamps();

            $table->index(['wholesaler_id', 'status', 'received_at']);
            $table->index(['event_type', 'status']);
        });

        // เพิ่ม webhook event types ที่ support ใน wholesaler_api_configs
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('wholesaler_api_configs', 'webhook_events')) {
                $table->json('webhook_events')
                    ->nullable()
                    ->after('webhook_url')
                    ->comment('["tour.created", "tour.updated", "period.updated", "availability.changed"]');
            }

            if (!Schema::hasColumn('wholesaler_api_configs', 'callback_url')) {
                $table->string('callback_url', 500)
                    ->nullable()
                    ->after('webhook_events')
                    ->comment('URL ที่ Wholesaler ส่ง callback มา (ถ้าต่างจาก webhook_url)');
            }

            if (!Schema::hasColumn('wholesaler_api_configs', 'callback_auth_type')) {
                $table->enum('callback_auth_type', ['none', 'signature', 'token', 'basic'])
                    ->default('signature')
                    ->after('callback_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');

        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            if (Schema::hasColumn('wholesaler_api_configs', 'webhook_events')) {
                $table->dropColumn('webhook_events');
            }
            if (Schema::hasColumn('wholesaler_api_configs', 'callback_url')) {
                $table->dropColumn('callback_url');
            }
            if (Schema::hasColumn('wholesaler_api_configs', 'callback_auth_type')) {
                $table->dropColumn('callback_auth_type');
            }
        });
    }
};
