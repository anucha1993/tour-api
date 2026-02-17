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
        Schema::dropIfExists('group_tour_testimonials');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('group_tour_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_position')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('logo_cf_id')->nullable();
            $table->text('content');
            $table->tinyInteger('rating')->default(5);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
