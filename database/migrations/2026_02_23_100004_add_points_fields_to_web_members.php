<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_members', function (Blueprint $table) {
            $table->unsignedInteger('total_points')->default(0)->after('gender');
            $table->unsignedInteger('lifetime_points')->default(0)->after('total_points');
            $table->foreignId('current_level_id')->nullable()->after('lifetime_points')
                  ->constrained('member_levels')->nullOnDelete();
            $table->timestamp('level_upgraded_at')->nullable()->after('current_level_id');
            $table->string('referral_code', 20)->nullable()->unique()->after('level_upgraded_at');
            $table->foreignId('referred_by')->nullable()->after('referral_code')
                  ->constrained('web_members')->nullOnDelete();
        });

        // Set default level for existing members
        $defaultLevel = DB::table('member_levels')->where('is_default', true)->first();
        if ($defaultLevel) {
            DB::table('web_members')->whereNull('current_level_id')->update([
                'current_level_id' => $defaultLevel->id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('web_members', function (Blueprint $table) {
            $table->dropForeign(['current_level_id']);
            $table->dropForeign(['referred_by']);
            $table->dropColumn([
                'total_points',
                'lifetime_points',
                'current_level_id',
                'level_upgraded_at',
                'referral_code',
                'referred_by',
            ]);
        });
    }
};
