<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename min_points to min_spending in member_levels
        Schema::table('member_levels', function (Blueprint $table) {
            $table->renameColumn('min_points', 'min_spending');
        });

        // Change column type to decimal for money amounts
        Schema::table('member_levels', function (Blueprint $table) {
            $table->decimal('min_spending', 12, 2)->default(0)->change();
        });

        // Update seeded level values to spending thresholds (THB)
        DB::table('member_levels')->where('slug', 'bronze')->update(['min_spending' => 0]);
        DB::table('member_levels')->where('slug', 'silver')->update(['min_spending' => 50001]);
        DB::table('member_levels')->where('slug', 'gold')->update(['min_spending' => 200001]);
        DB::table('member_levels')->where('slug', 'platinum')->update(['min_spending' => 500001]);

        // Add lifetime_spending to web_members
        Schema::table('web_members', function (Blueprint $table) {
            $table->decimal('lifetime_spending', 14, 2)->default(0)->after('lifetime_points');
        });
    }

    public function down(): void
    {
        Schema::table('web_members', function (Blueprint $table) {
            $table->dropColumn('lifetime_spending');
        });

        Schema::table('member_levels', function (Blueprint $table) {
            $table->renameColumn('min_spending', 'min_points');
        });
    }
};
