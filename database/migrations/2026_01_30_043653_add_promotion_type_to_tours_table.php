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
            // Promotion type: none, normal, fire_sale
            $table->enum('promotion_type', ['none', 'normal', 'fire_sale'])
                ->default('none')
                ->after('discount_amount')
                ->comment('none=ไม่มีโปร, normal=โปรธรรมดา, fire_sale=โปรไฟไหม้');
            
            // Max discount percent from all periods (for filtering/sorting)
            $table->decimal('max_discount_percent', 5, 2)
                ->default(0)
                ->after('promotion_type')
                ->comment('ส่วนลดสูงสุด % จากทุก Period');
            
            // Index for filtering
            $table->index('promotion_type');
            $table->index('max_discount_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['promotion_type']);
            $table->dropIndex(['max_discount_percent']);
            $table->dropColumn(['promotion_type', 'max_discount_percent']);
        });
    }
};
