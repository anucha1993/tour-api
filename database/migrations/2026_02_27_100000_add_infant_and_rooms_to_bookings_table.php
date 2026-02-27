<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Infant quantity and price
            $table->unsignedSmallInteger('qty_infant')->default(0)->after('qty_child_nobed');
            $table->decimal('price_infant', 12, 2)->default(0)->after('price_child_nobed');

            // Room type quantities
            $table->unsignedSmallInteger('qty_triple')->default(0)->after('qty_infant');
            $table->unsignedSmallInteger('qty_twin')->default(0)->after('qty_triple');
            $table->unsignedSmallInteger('qty_double')->default(0)->after('qty_twin');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'qty_infant',
                'price_infant',
                'qty_triple',
                'qty_twin',
                'qty_double',
            ]);
        });
    }
};
