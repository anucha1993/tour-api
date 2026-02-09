<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ตาราง tour_views — เก็บสถิติการเข้าชมทัวร์แต่ละครั้ง
        Schema::create('tour_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            
            // Session / visitor tracking
            $table->string('session_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            
            // Tour metadata snapshot (เก็บข้อมูลตอนดู เพื่อวิเคราะห์ย้อนหลัง)
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('country_name', 100)->nullable();
            $table->json('city_ids')->nullable();
            $table->json('city_names')->nullable();
            $table->json('hashtags')->nullable();
            $table->json('themes')->nullable();
            $table->string('region', 50)->nullable();
            $table->string('sub_region', 50)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('promotion_type', 20)->nullable();
            $table->unsignedTinyInteger('duration_days')->nullable();
            
            // Referrer / UTM
            $table->string('referrer', 500)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            
            // Device
            $table->enum('device_type', ['desktop', 'mobile', 'tablet'])->nullable();
            
            $table->timestamp('viewed_at')->useCurrent();
            
            // Indexes for analytics queries
            $table->index('tour_id');
            $table->index('country_id');
            $table->index('viewed_at');
            $table->index('session_id');
            $table->index(['tour_id', 'viewed_at']);
            $table->index(['country_id', 'viewed_at']);
            $table->index('region');
        });

        // ตาราง tour_view_daily_stats — สรุปรายวัน (ลด query load)
        Schema::create('tour_view_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            
            $table->unique(['tour_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_view_daily_stats');
        Schema::dropIfExists('tour_views');
    }
};
