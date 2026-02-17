<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_package_page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('cover_image_url')->nullable();
            $table->string('cover_image_cf_id')->nullable();
            $table->string('cover_image_position')->default('center');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_package_page_settings');
    }
};
