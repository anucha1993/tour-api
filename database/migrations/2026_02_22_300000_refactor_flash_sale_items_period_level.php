<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if period_id already exists (partial migration recovery)
        $hasPeriodId = Schema::hasColumn('flash_sale_items', 'period_id');

        if (!$hasPeriodId) {
            Schema::table('flash_sale_items', function (Blueprint $table) {
                $table->foreignId('period_id')->nullable()->after('tour_id')
                      ->constrained('periods')->cascadeOnDelete();
                $table->datetime('flash_end_date')->nullable()->after('is_active')
                      ->comment('วันหมดอายุ flash sale ของรายการนี้');
            });
        }

        // Use raw SQL to drop unique index (bypasses FK check issue)
        // First add standalone index on flash_sale_id so the FK can release the composite unique
        $existingIndexes = collect(DB::select("SHOW INDEX FROM flash_sale_items"))
            ->pluck('Key_name')->unique()->toArray();

        if (!in_array('flash_sale_items_flash_sale_id_index', $existingIndexes)) {
            DB::statement('ALTER TABLE flash_sale_items ADD INDEX flash_sale_items_flash_sale_id_index (flash_sale_id)');
        }

        if (in_array('flash_sale_items_flash_sale_id_tour_id_unique', $existingIndexes)) {
            DB::statement('ALTER TABLE flash_sale_items DROP INDEX flash_sale_items_flash_sale_id_tour_id_unique');
        }

        // Re-add tour_id FK if it was lost
        $fks = collect(DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME='flash_sale_items' AND CONSTRAINT_TYPE='FOREIGN KEY' AND TABLE_SCHEMA=DATABASE()"))
            ->pluck('CONSTRAINT_NAME')->toArray();

        if (!in_array('flash_sale_items_tour_id_foreign', $fks)) {
            Schema::table('flash_sale_items', function (Blueprint $table) {
                $table->foreign('tour_id')->references('id')->on('tours')->cascadeOnDelete();
            });
        }

        // New unique and index
        $indexes = collect(DB::select("SHOW INDEX FROM flash_sale_items"))
            ->pluck('Key_name')->unique()->toArray();

        Schema::table('flash_sale_items', function (Blueprint $table) use ($indexes) {
            if (!in_array('flash_sale_items_sale_period_unique', $indexes)) {
                $table->unique(['flash_sale_id', 'period_id'], 'flash_sale_items_sale_period_unique');
            }
            if (!in_array('flash_sale_items_flash_end_date_index', $indexes)) {
                $table->index('flash_end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('flash_sale_items', function (Blueprint $table) {
            $table->dropUnique('flash_sale_items_sale_period_unique');
            $table->dropIndex(['period_id']);
            $table->dropIndex(['flash_end_date']);
            $table->dropForeign(['period_id']);
            $table->dropColumn(['period_id', 'flash_end_date']);

            // Restore old unique
            $table->unique(['flash_sale_id', 'tour_id']);
        });
    }
};
