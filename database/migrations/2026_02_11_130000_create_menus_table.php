<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('location'); // header, footer_col1, footer_col2, footer_col3
            $table->string('title'); // Display text
            $table->string('url')->nullable(); // Link URL
            $table->string('target')->default('_self'); // _self, _blank
            $table->string('icon')->nullable(); // Icon name (for footer/social)
            $table->unsignedBigInteger('parent_id')->nullable(); // For submenus
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('css_class')->nullable(); // Optional custom CSS class
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('menus')->onDelete('cascade');
            $table->index(['location', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
