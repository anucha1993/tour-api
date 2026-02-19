<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('link_url')->nullable();
            // type: promotion, flash_sale, birthday, special, custom
            $table->string('type')->default('promotion');
            // target_type: all = ส่งทุกคน, level = เฉพาะระดับสมาชิก
            $table->string('target_type')->default('all');
            $table->unsignedBigInteger('target_level_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('target_level_id')->references('id')->on('member_levels')->nullOnDelete();
        });

        Schema::create('member_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('notification_id');
            $table->timestamp('read_at')->useCurrent();
            $table->timestamps();

            $table->unique(['member_id', 'notification_id']);
            $table->foreign('member_id')->references('id')->on('web_members')->cascadeOnDelete();
            $table->foreign('notification_id')->references('id')->on('promotion_notifications')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_notification_reads');
        Schema::dropIfExists('promotion_notifications');
    }
};
