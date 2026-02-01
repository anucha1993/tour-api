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
        Schema::create('airports', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique()->comment('IATA code (BKK, DMK, HKG)');
            $table->string('name_en', 150);
            $table->string('name_th', 150)->nullable();
            $table->string('city_en', 100)->nullable();
            $table->string('city_th', 100)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('timezone', 50)->nullable()->comment('Asia/Bangkok');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('country_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airports');
    }
};
