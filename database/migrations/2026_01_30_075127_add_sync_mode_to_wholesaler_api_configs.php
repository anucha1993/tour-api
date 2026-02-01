<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Two-phase sync support:
     * - sync_mode: 'single' = tours+periods together (default), 'two_phase' = separate API calls
     * - periods_endpoint stored in auth_credentials['endpoints']['periods']
     */
    public function up(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->enum('sync_mode', ['single', 'two_phase'])->default('single')->after('sync_method')
                  ->comment('single: tours+periods together, two_phase: separate API calls for periods');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->dropColumn('sync_mode');
        });
    }
};
