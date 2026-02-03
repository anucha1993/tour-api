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
        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'price_single_surcharge')) {
                $table->renameColumn('price_single_surcharge', 'price_single');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'price_single')) {
                $table->renameColumn('price_single', 'price_single_surcharge');
            }
        });
    }
};
