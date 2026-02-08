<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * เพิ่ม fields สำหรับ:
     * - Heartbeat monitoring (ตรวจจับ stuck syncs)
     * - Progress tracking (แสดง % ความคืบหน้า)
     * - Chunked processing (แบ่ง batch)
     */
    public function up(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            // Heartbeat - ตรวจจับ stuck syncs
            $table->timestamp('last_heartbeat_at')->nullable()->after('completed_at');
            $table->integer('heartbeat_timeout_minutes')->default(30)->after('last_heartbeat_at');
            
            // Progress tracking
            $table->integer('total_items')->default(0)->after('heartbeat_timeout_minutes');
            $table->integer('processed_items')->default(0)->after('total_items');
            $table->integer('progress_percent')->default(0)->after('processed_items');
            $table->string('current_item_code', 100)->nullable()->after('progress_percent');
            
            // Chunked processing
            $table->integer('chunk_size')->default(50)->after('current_item_code');
            $table->integer('current_chunk')->default(0)->after('chunk_size');
            $table->integer('total_chunks')->default(0)->after('current_chunk');
            
            // Rate limiting
            $table->integer('api_calls_count')->default(0)->after('total_chunks');
            $table->timestamp('rate_limit_reset_at')->nullable()->after('api_calls_count');
            
            // Cancellation
            $table->boolean('cancel_requested')->default(false)->after('rate_limit_reset_at');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_requested');
            $table->string('cancel_reason', 255)->nullable()->after('cancelled_at');
            
            // Index for heartbeat monitoring
            $table->index(['status', 'last_heartbeat_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_heartbeat_at']);
            
            $table->dropColumn([
                'last_heartbeat_at',
                'heartbeat_timeout_minutes',
                'total_items',
                'processed_items',
                'progress_percent',
                'current_item_code',
                'chunk_size',
                'current_chunk',
                'total_chunks',
                'api_calls_count',
                'rate_limit_reset_at',
                'cancel_requested',
                'cancelled_at',
                'cancel_reason',
            ]);
        });
    }
};
