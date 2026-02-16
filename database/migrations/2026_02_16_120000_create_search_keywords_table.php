<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 255);
            $table->unsignedInteger('search_count')->default(1);
            $table->unsignedInteger('result_count')->default(0);
            $table->timestamp('last_searched_at')->useCurrent();
            $table->timestamps();

            $table->unique('keyword');
            $table->index('search_count');
            $table->index('last_searched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_keywords');
    }
};
