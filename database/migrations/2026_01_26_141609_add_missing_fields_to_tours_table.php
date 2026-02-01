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
        Schema::table('tours', function (Blueprint $table) {
            // Add hotel_star column if not exists
            if (!Schema::hasColumn('tours', 'hotel_star')) {
                $table->unsignedTinyInteger('hotel_star')->nullable()->after('hotel_star_max');
            }
            
            // Add description column if not exists
            if (!Schema::hasColumn('tours', 'description')) {
                $table->text('description')->nullable()->after('conditions');
            }
            
            // Add transport_id column if not exists
            if (!Schema::hasColumn('tours', 'transport_id')) {
                $table->foreignId('transport_id')->nullable()->after('tour_category')->constrained('transports')->nullOnDelete();
            }
            
            // Add price_adult column if not exists
            if (!Schema::hasColumn('tours', 'price_adult')) {
                $table->decimal('price_adult', 12, 2)->nullable()->after('display_price');
            }
            
            // Add discount_adult column if not exists
            if (!Schema::hasColumn('tours', 'discount_adult')) {
                $table->decimal('discount_adult', 12, 2)->nullable()->after('price_adult');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            if (Schema::hasColumn('tours', 'hotel_star')) {
                $table->dropColumn('hotel_star');
            }
            if (Schema::hasColumn('tours', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('tours', 'transport_id')) {
                $table->dropForeign(['transport_id']);
                $table->dropColumn('transport_id');
            }
            if (Schema::hasColumn('tours', 'price_adult')) {
                $table->dropColumn('price_adult');
            }
            if (Schema::hasColumn('tours', 'discount_adult')) {
                $table->dropColumn('discount_adult');
            }
        });
    }
};
