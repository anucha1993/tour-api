<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ลบฟิลด์ที่ไม่ใช้แล้ว: promotion_type, tour_category, is_published, published_at
     */
    public function up(): void
    {
        // Drop promotion_type index
        DB::statement('ALTER TABLE `tours` DROP INDEX IF EXISTS `tours_promotion_type_index`');

        // Drop columns (MySQL will auto-drop the composite index that includes is_published)
        Schema::table('tours', function (Blueprint $table) {
            $columns = Schema::getColumnListing('tours');
            $toDrop = array_filter(
                ['promotion_type', 'tour_category', 'is_published', 'published_at'],
                fn($col) => in_array($col, $columns)
            );
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });

        if (Schema::hasColumn('tour_views', 'promotion_type')) {
            Schema::table('tour_views', function (Blueprint $table) {
                $table->dropColumn('promotion_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->enum('promotion_type', ['none', 'normal', 'fire_sale'])
                ->default('none')
                ->after('max_discount_percent')
                ->comment('none=ไม่มีโปร, normal=โปรธรรมดา, fire_sale=โปรไฟไหม้');
            $table->enum('tour_category', ['budget', 'premium'])
                ->nullable()
                ->after('badge')
                ->comment('ประเภททัวร์: budget=ทัวร์ราคาถูก, premium=ทัวร์พรีเมียม');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->index('promotion_type');
            $table->index(['primary_country_id', 'status', 'is_published']);
        });

        Schema::table('tour_views', function (Blueprint $table) {
            $table->string('promotion_type', 20)->nullable();
        });
    }
};
