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
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            // Smart Sync Settings - check manual_override_fields before update
            $table->boolean('respect_manual_overrides')->default(true)->after('sync_limit')
                ->comment('Check manual_override_fields before updating. If a field has been manually edited, skip overwriting it.');
            
            // Fields that should ALWAYS be synced regardless of manual overrides
            $table->json('always_sync_fields')->nullable()->after('respect_manual_overrides')
                ->comment('Fields to always sync: periods, images, pdf, etc.');
            
            // Fields that should NEVER be updated by sync
            $table->json('never_sync_fields')->nullable()->after('always_sync_fields')
                ->comment('Fields to never sync: status, etc.');
            
            // Auto-close expired periods
            $table->boolean('auto_close_expired_periods')->default(false)->after('past_period_threshold_days')
                ->comment('Automatically close periods that have departed');
            
            // Auto-close tours when all periods are closed/expired  
            $table->boolean('auto_close_expired_tours')->default(false)->after('auto_close_expired_periods')
                ->comment('Automatically set tour status to closed when all periods have departed');
            
            // Skip syncing past periods (don't create/update if departure date passed)
            $table->boolean('skip_past_periods_on_sync')->default(true)->after('auto_close_expired_tours')
                ->comment('Skip creating/updating periods that have already departed');
            
            // Skip syncing disabled tours
            $table->boolean('skip_disabled_tours_on_sync')->default(true)->after('skip_past_periods_on_sync')
                ->comment('Skip syncing tours with status = disabled or closed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->dropColumn([
                'respect_manual_overrides',
                'always_sync_fields',
                'never_sync_fields',
                'auto_close_expired_periods',
                'auto_close_expired_tours',
                'skip_past_periods_on_sync',
                'skip_disabled_tours_on_sync',
            ]);
        });
    }
};
