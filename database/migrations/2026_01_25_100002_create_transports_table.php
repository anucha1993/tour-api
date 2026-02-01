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
        Schema::create('transports', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->nullable()->comment('IATA code 2 ตัว (TG, AQ, FD)');
            $table->string('code1', 100)->nullable()->comment('ICAO code 3 ตัว (THA, ANK, AFR)');
            $table->string('name', 250)->nullable()->comment('ชื่อผู้ให้บริการ');
            $table->enum('type', ['airline', 'bus', 'boat', 'train', 'van', 'other'])->default('airline')->comment('ประเภทยานพาหนะ');
            $table->string('image', 255)->nullable()->comment('รูปโลโก้');
            $table->enum('status', ['on', 'off'])->default('on');
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transports');
    }
};
