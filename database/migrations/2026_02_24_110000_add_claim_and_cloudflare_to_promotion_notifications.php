<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add cloudflare_id to promotion_notifications
        Schema::table('promotion_notifications', function (Blueprint $table) {
            $table->string('cloudflare_id')->nullable()->after('banner_url');
        });

        // Member claim records
        Schema::create('member_promotion_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('notification_id');
            $table->enum('status', ['claimed', 'used', 'expired'])->default('claimed');
            $table->timestamp('claimed_at')->useCurrent();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'notification_id']);
            $table->foreign('member_id')->references('id')->on('web_members')->cascadeOnDelete();
            $table->foreign('notification_id')->references('id')->on('promotion_notifications')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_promotion_claims');
        Schema::table('promotion_notifications', function (Blueprint $table) {
            $table->dropColumn('cloudflare_id');
        });
    }
};
