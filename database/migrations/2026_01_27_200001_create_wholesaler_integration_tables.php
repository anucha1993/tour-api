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
        // 1. Wholesaler API Configs - การตั้งค่า API ของแต่ละ Wholesaler
        Schema::create('wholesaler_api_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();
            
            // API Connection
            $table->string('api_base_url', 500);
            $table->string('api_version', 20)->default('v1');
            $table->enum('api_format', ['rest', 'soap', 'graphql'])->default('rest');
            
            // Authentication
            $table->enum('auth_type', ['api_key', 'oauth2', 'basic', 'bearer', 'custom'])->default('api_key');
            $table->text('auth_credentials')->nullable()->comment('Encrypted JSON');
            $table->string('auth_header_name', 100)->default('Authorization');
            
            // Rate Limiting
            $table->integer('rate_limit_per_minute')->default(60);
            $table->integer('rate_limit_per_day')->default(10000);
            
            // Timeouts
            $table->integer('connect_timeout_seconds')->default(10);
            $table->integer('request_timeout_seconds')->default(30);
            $table->integer('retry_attempts')->default(3);
            
            // Sync Settings
            $table->boolean('sync_enabled')->default(true);
            $table->enum('sync_method', ['cursor', 'ack_callback', 'last_modified'])->default('cursor');
            $table->string('sync_schedule', 100)->default('0 */2 * * *')->comment('Every 2 hours');
            $table->string('full_sync_schedule', 100)->default('0 3 * * *')->comment('Daily 3 AM');
            
            // Webhook
            $table->boolean('webhook_enabled')->default(false);
            $table->string('webhook_secret', 200)->nullable();
            $table->string('webhook_url', 500)->nullable();
            
            // Features Support
            $table->boolean('supports_availability_check')->default(true);
            $table->boolean('supports_hold_booking')->default(true);
            $table->boolean('supports_modify_booking')->default(false);
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_health_check_at')->nullable();
            $table->boolean('last_health_check_status')->nullable();
            
            $table->timestamps();
            
            $table->unique('wholesaler_id');
        });

        // 2. Section Definitions - กำหนด fields ในแต่ละ section
        Schema::create('section_definitions', function (Blueprint $table) {
            $table->id();
            
            $table->string('section_name', 50)->comment('tour, period, pricing, content, media, seo');
            $table->string('field_name', 100);
            
            // Data Type
            $table->enum('data_type', [
                'TEXT', 'INT', 'DECIMAL', 'DATE', 'DATETIME',
                'BOOLEAN', 'ENUM', 'ARRAY_TEXT', 'ARRAY_INT',
                'ARRAY_DECIMAL', 'JSON'
            ]);
            $table->json('enum_values')->nullable()->comment('For ENUM type: ["join", "incentive"]');
            
            // Validation
            $table->boolean('is_required')->default(false);
            $table->string('default_value', 500)->nullable();
            $table->string('validation_rules', 500)->nullable()->comment('Laravel validation rules');
            
            // Lookup
            $table->string('lookup_table', 100)->nullable()->comment('countries, cities, transports');
            $table->json('lookup_match_fields')->nullable()->comment('["name_en", "name_th", "iso2"]');
            $table->string('lookup_return_field', 100)->default('id');
            $table->boolean('lookup_create_if_not_found')->default(false);
            
            // Meta
            $table->string('description', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false)->comment('System fields cannot be deleted');
            
            $table->timestamps();
            
            $table->unique(['section_name', 'field_name']);
        });

        // 3. Wholesaler Field Mappings - mapping fields ของ wholesaler กับ section fields
        Schema::create('wholesaler_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();
            
            // Section & Field
            $table->string('section_name', 50);
            $table->string('our_field', 100);
            
            // Their Field (flexible)
            $table->string('their_field', 200)->nullable()->comment('Simple field name');
            $table->string('their_field_path', 500)->nullable()->comment('JSON path: data.tour.details.name');
            
            // Transformation
            $table->enum('transform_type', [
                'direct',      // Copy as-is
                'value_map',   // Map values
                'formula',     // Calculate
                'split',       // Split string
                'concat',      // Concatenate
                'lookup',      // Lookup from table
                'custom'       // Custom function
            ])->default('direct');
            $table->json('transform_config')->nullable();
            
            // Override
            $table->string('default_value', 500)->nullable();
            $table->boolean('is_required_override')->nullable()->comment('Override section definition');
            
            // Meta
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            
            $table->unique(['wholesaler_id', 'section_name', 'our_field'], 'unique_mapping');
        });

        // 4. Sync Cursors - เก็บ cursor สำหรับ incremental sync
        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();
            
            $table->enum('sync_type', ['tours', 'periods', 'prices', 'all'])->default('all');
            $table->string('cursor_value', 500)->nullable();
            $table->enum('cursor_type', ['string', 'timestamp', 'integer'])->default('string');
            
            // Last Sync Info
            $table->string('last_sync_id', 100)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            
            // Stats
            $table->integer('total_received')->default(0);
            $table->integer('last_batch_count')->default(0);
            
            $table->timestamps();
            
            $table->unique(['wholesaler_id', 'sync_type']);
        });

        // 5. Sync Logs - บันทึกประวัติ sync
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();
            
            // Sync Info
            $table->enum('sync_type', ['full', 'incremental', 'webhook', 'manual'])->default('incremental');
            $table->string('sync_id', 100)->unique()->nullable();
            
            // Timing
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            
            // Results
            $table->enum('status', ['running', 'completed', 'failed', 'partial'])->default('running');
            
            // Tour Stats
            $table->integer('tours_received')->default(0);
            $table->integer('tours_created')->default(0);
            $table->integer('tours_updated')->default(0);
            $table->integer('tours_skipped')->default(0);
            $table->integer('tours_failed')->default(0);
            
            // Period Stats
            $table->integer('periods_received')->default(0);
            $table->integer('periods_created')->default(0);
            $table->integer('periods_updated')->default(0);
            
            // Errors
            $table->integer('error_count')->default(0);
            $table->json('error_summary')->nullable();
            
            // ACK Status
            $table->boolean('ack_sent')->default(false);
            $table->timestamp('ack_sent_at')->nullable();
            $table->boolean('ack_accepted')->nullable();
            
            $table->timestamps();
            
            $table->index(['wholesaler_id', 'started_at']);
        });

        // 6. Sync Error Logs - บันทึก errors ระหว่าง sync
        Schema::create('sync_error_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->constrained('sync_logs')->cascadeOnDelete();
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();
            
            // Error Context
            $table->string('external_tour_code', 200)->nullable();
            $table->foreignId('tour_id')->nullable()->constrained('tours')->nullOnDelete();
            $table->string('section_name', 50)->nullable();
            $table->string('field_name', 100)->nullable();
            
            // Error Details
            $table->enum('error_type', ['mapping', 'validation', 'lookup', 'type_cast', 'api', 'database', 'unknown'])->default('unknown');
            $table->text('error_message');
            
            // Values
            $table->text('received_value')->nullable();
            $table->string('expected_type', 50)->nullable();
            
            // Debug
            $table->json('raw_data')->nullable();
            $table->text('stack_trace')->nullable();
            
            // Resolution
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['wholesaler_id', 'is_resolved', 'created_at']);
        });

        // 7. Outbound API Logs - บันทึก API calls ที่เราส่งไป wholesaler
        Schema::create('outbound_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();
            
            // Request
            $table->enum('action', [
                'fetch_tours', 'fetch_detail', 'check_availability',
                'hold', 'confirm', 'cancel', 'modify', 'check_status',
                'ack_sync', 'health_check'
            ]);
            $table->string('endpoint', 500);
            $table->enum('method', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            
            // Response
            $table->integer('response_code')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable();
            $table->integer('response_time_ms')->nullable();
            
            // Context
            $table->foreignId('tour_id')->nullable()->constrained('tours')->nullOnDelete();
            $table->foreignId('sync_log_id')->nullable()->constrained('sync_logs')->nullOnDelete();
            
            // Status
            $table->enum('status', ['success', 'failed', 'timeout', 'error']);
            $table->string('error_type', 50)->nullable();
            $table->text('error_message')->nullable();
            
            // Retry
            $table->unsignedBigInteger('retry_of_id')->nullable();
            $table->integer('retry_count')->default(0);
            
            $table->timestamps();
            
            $table->index(['action', 'status', 'created_at']);
            $table->index(['wholesaler_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbound_api_logs');
        Schema::dropIfExists('sync_error_logs');
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('sync_cursors');
        Schema::dropIfExists('wholesaler_field_mappings');
        Schema::dropIfExists('section_definitions');
        Schema::dropIfExists('wholesaler_api_configs');
    }
};
