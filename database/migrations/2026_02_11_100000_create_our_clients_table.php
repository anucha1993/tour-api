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
        Schema::create('our_clients', function (Blueprint $table) {
            $table->id();
            $table->string('cloudflare_id')->nullable();
            $table->string('url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('filename');
            $table->string('name', 255)->comment('ชื่อลูกค้า/บริษัท');
            $table->string('alt', 255)->nullable()->comment('Alt Text สำหรับ SEO');
            $table->text('description')->nullable()->comment('รายละเอียดเพิ่มเติม');
            $table->string('website_url', 500)->nullable()->comment('เว็บไซต์ลูกค้า');
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('our_clients');
    }
};
