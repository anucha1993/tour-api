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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('iso2', 2)->unique()->comment('ISO 3166-1 alpha-2 (TH, JP, CN)');
            $table->string('iso3', 3)->unique()->comment('ISO 3166-1 alpha-3 (THA, JPN, CHN)');
            $table->string('name_en', 100);
            $table->string('name_th', 100)->nullable();
            $table->string('slug', 100)->unique()->comment('URL slug: thailand, japan');
            $table->string('region', 50)->nullable()->comment('Asia, Europe, etc.');
            $table->string('flag_emoji', 10)->nullable()->comment('🇹🇭 🇯🇵');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
