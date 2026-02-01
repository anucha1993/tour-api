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
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->nullable()->comment('City code (BKK, TYO, etc.)');
            $table->string('name_en', 150);
            $table->string('name_th', 150)->nullable();
            $table->string('slug', 150)->unique();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('timezone', 50)->nullable()->comment('Asia/Bangkok');
            $table->string('image', 500)->nullable()->comment('City image URL');
            $table->text('description')->nullable()->comment('City description');
            $table->boolean('is_popular')->default(false)->comment('Popular destination');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('country_id');
            $table->index('is_active');
            $table->index('is_popular');
        });

        // Add city_id to airports table
        Schema::table('airports', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('country_id')->constrained('cities')->nullOnDelete();
            $table->index('city_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });

        Schema::dropIfExists('cities');
    }
};
