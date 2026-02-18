<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('web_members')->onDelete('cascade');
            $table->foreignId('rule_id')->nullable()->constrained('point_rules')->nullOnDelete();

            $table->enum('type', ['earn', 'spend', 'expire', 'adjust'])->default('earn');
            $table->integer('points'); // positive for earn, negative for spend/expire
            $table->unsignedInteger('balance_after')->default(0);

            // Polymorphic source (tour_views, tour_reviews, bookings, etc.)
            $table->string('source_type')->nullable(); // e.g. App\Models\TourView
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);

            $table->timestamps();

            // Indexes
            $table->index('member_id');
            $table->index('type');
            $table->index('expires_at');
            $table->index('is_expired');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
