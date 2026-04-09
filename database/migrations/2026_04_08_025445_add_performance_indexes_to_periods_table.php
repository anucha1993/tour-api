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
        Schema::table('periods', function (Blueprint $table) {
            // Covering index for whereHas: status='open' AND start_date >= ? → tour_id
            $table->index(['status', 'start_date', 'tour_id'], 'idx_periods_status_date_tour');
            // Covering index for eager load: tour_id + is_visible + start_date ORDER
            $table->index(['tour_id', 'is_visible', 'start_date'], 'idx_periods_tour_visible_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->dropIndex('idx_periods_status_date_tour');
            $table->dropIndex('idx_periods_tour_visible_date');
        });
    }
};
