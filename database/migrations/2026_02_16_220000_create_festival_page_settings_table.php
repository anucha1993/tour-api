<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festival_page_settings', function (Blueprint $table) {
            $table->id();

            // Cover image for festival listing page hero
            $table->string('cover_image_url')->nullable();
            $table->string('cover_image_cf_id')->nullable();
            $table->string('cover_image_position')->default('center');

            $table->timestamps();
        });

        // Insert default row
        DB::table('festival_page_settings')->insert([
            'cover_image_position' => 'center',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_page_settings');
    }
};
