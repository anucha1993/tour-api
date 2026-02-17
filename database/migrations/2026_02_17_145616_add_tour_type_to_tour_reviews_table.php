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
        Schema::table('tour_reviews', function (Blueprint $table) {
            $table->enum('tour_type', ['individual', 'private', 'corporate'])
                  ->default('individual')
                  ->after('review_source')
                  ->comment('บุคคล/ทั่วไป, เหมาส่วนตัว, กรุ๊ปเหมาบริษัท');
            $table->index('tour_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_reviews', function (Blueprint $table) {
            $table->dropIndex(['tour_type']);
            $table->dropColumn('tour_type');
        });
    }
};
