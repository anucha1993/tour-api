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
        Schema::table('tour_itineraries', function (Blueprint $table) {
            // External API sync fields
            $table->string('external_id', 100)->nullable()->after('id')->comment('ID from external wholesaler API');
            $table->string('data_source', 50)->nullable()->after('external_id')->comment('Source: manual, tourkrub, itsawongsaeng, etc.');
            
            // Index for faster lookup during sync
            $table->index(['external_id', 'data_source'], 'idx_itinerary_external_sync');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_itineraries', function (Blueprint $table) {
            $table->dropIndex('idx_itinerary_external_sync');
            $table->dropColumn(['external_id', 'data_source']);
        });
    }
};
