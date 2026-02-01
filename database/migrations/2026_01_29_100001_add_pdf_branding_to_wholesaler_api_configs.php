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
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            // PDF Branding - Header/Footer overlay images
            $table->string('pdf_header_image', 500)->nullable()->after('supports_modify_booking')
                ->comment('Cloudflare URL for PDF header image');
            $table->string('pdf_footer_image', 500)->nullable()->after('pdf_header_image')
                ->comment('Cloudflare URL for PDF footer image');
            $table->integer('pdf_header_height')->nullable()->after('pdf_footer_image')
                ->comment('Header image height in pixels (auto from image)');
            $table->integer('pdf_footer_height')->nullable()->after('pdf_header_height')
                ->comment('Footer image height in pixels (auto from image)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->dropColumn([
                'pdf_header_image',
                'pdf_footer_image',
                'pdf_header_height',
                'pdf_footer_height',
            ]);
        });
    }
};
