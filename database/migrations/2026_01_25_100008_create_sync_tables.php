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
        // Sync Batches - ประวัติ batch sync
        Schema::create('sync_batches', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 100)->unique()->comment('Idempotency key');
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();
            $table->enum('mode', ['delta', 'full'])->default('delta');
            $table->enum('status', ['pending', 'processing', 'completed', 'partial', 'failed'])->default('pending');
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable()->comment('เวลาที่ partner ส่ง');
            $table->timestamp('processed_at')->nullable()->comment('เวลาที่ประมวลผลเสร็จ');
            $table->timestamps();

            $table->index('wholesaler_id');
            $table->index('status');
            $table->index('created_at');
        });

        // Sync Batch Items - รายละเอียดแต่ละ item
        Schema::create('sync_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_batch_id')->constrained('sync_batches')->cascadeOnDelete();
            $table->enum('entity_type', ['tour', 'departure']);
            $table->string('external_id', 50);
            $table->enum('result', ['created', 'updated', 'skipped', 'error']);
            $table->string('error_code', 10)->nullable()->comment('E001, E002, ...');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('sync_batch_id');
            $table->index(['entity_type', 'external_id']);
        });

        // Price History - ประวัติราคา (Audit)
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->decimal('price_adult_old', 10, 2)->nullable();
            $table->decimal('price_adult_new', 10, 2)->nullable();
            $table->string('changed_by', 50)->nullable()->comment('sync / admin / api');
            $table->timestamp('changed_at')->useCurrent();

            $table->index('offer_id');
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('sync_batch_items');
        Schema::dropIfExists('sync_batches');
    }
};
