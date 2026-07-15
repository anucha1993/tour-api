<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_view_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_id')->index();
            $table->timestamp('viewed_at')->useCurrent();

            $table->index('viewed_at');
            $table->index(['tour_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_view_logs');
    }
};
