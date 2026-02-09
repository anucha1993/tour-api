<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommended_tour_settings', function (Blueprint $table) {
            $table->id();
            $table->string('display_mode')->default('ordered'); // ordered, random, weighted_random
            $table->string('title')->default('ทัวร์แนะนำ');
            $table->string('subtitle')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('cache_minutes')->default(60);
            $table->timestamps();
        });

        // Seed default settings row
        DB::table('recommended_tour_settings')->insert([
            'display_mode' => 'ordered',
            'title' => 'ทัวร์แนะนำ',
            'subtitle' => null,
            'is_active' => true,
            'cache_minutes' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('recommended_tour_settings');
    }
};
