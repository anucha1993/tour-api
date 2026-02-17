<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('terms')->nullable(); // เงื่อนไขการให้บริการ
            $table->json('inclusions')->nullable(); // รวมในแพ็คเกจ (dynamic rows)
            $table->json('exclusions')->nullable(); // ไม่รวมในแพ็คเกจ (dynamic rows)
            $table->json('timeline')->nullable(); // [{day_number, detail}]
            $table->string('image_url')->nullable();
            $table->string('image_cf_id')->nullable();
            $table->string('pdf_url')->nullable();
            $table->string('pdf_path')->nullable(); // local path for deletion
            $table->json('hashtags')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_never_expire')->default(true);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Pivot table for tour_packages <-> countries (many-to-many)
        Schema::create('tour_package_country', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_package_id')->constrained('tour_packages')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tour_package_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_package_country');
        Schema::dropIfExists('tour_packages');
    }
};
