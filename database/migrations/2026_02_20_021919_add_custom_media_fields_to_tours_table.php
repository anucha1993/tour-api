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
        Schema::table('tours', function (Blueprint $table) {
            // Custom cover image (bypass API sync)
            $table->string('custom_cover_image_url', 500)->nullable()->after('cover_image_alt');
            $table->string('custom_cover_image_alt', 255)->nullable()->after('custom_cover_image_url');
            
            // Custom PDF (bypass API sync)
            $table->string('custom_pdf_url', 500)->nullable()->after('pdf_url');
            
            // Track which media source to use: 'api' or 'custom'
            $table->enum('cover_image_source', ['api', 'custom'])->default('api')->after('custom_cover_image_alt');
            $table->enum('pdf_source', ['api', 'custom'])->default('api')->after('custom_pdf_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn([
                'custom_cover_image_url',
                'custom_cover_image_alt',
                'cover_image_source',
                'custom_pdf_url',
                'pdf_source',
            ]);
        });
    }
};
