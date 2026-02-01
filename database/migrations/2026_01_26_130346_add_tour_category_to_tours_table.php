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
            if (!Schema::hasColumn('tours', 'tour_category')) {
                $table->enum('tour_category', ['budget', 'premium'])->nullable()->after('badge')->comment('ประเภททัวร์: budget=ทัวร์ราคาถูก, premium=ทัวร์พรีเมียม');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            if (Schema::hasColumn('tours', 'tour_category')) {
                $table->dropColumn('tour_category');
            }
        });
    }
};
