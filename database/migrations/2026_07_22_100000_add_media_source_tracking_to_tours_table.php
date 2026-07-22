<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media change-detection tracking.
 *
 * Stores a fingerprint (source URL + filename + byte size + ETag + Last-Modified)
 * of the ORIGINAL wholesaler media so the next sync can detect when a wholesaler
 * silently replaces a PDF / cover image and we can re-mirror + delete the old file.
 *
 * The *_updated_at columns are the visible "update tag" surfaced in the admin
 * tours dashboard. They are only set when a change is detected on an EXISTING
 * tour (never on the very first import).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // PDF source fingerprint
            $table->string('pdf_source_url', 1024)->nullable()->after('pdf_branding_hash');
            $table->string('pdf_source_name', 255)->nullable()->after('pdf_source_url');
            $table->unsignedBigInteger('pdf_source_size')->nullable()->after('pdf_source_name');
            $table->string('pdf_source_etag', 255)->nullable()->after('pdf_source_size');
            $table->timestamp('pdf_source_modified')->nullable()->after('pdf_source_etag');
            $table->timestamp('pdf_updated_at')->nullable()->after('pdf_source_modified');

            // Cover image source fingerprint
            $table->string('cover_source_url', 1024)->nullable()->after('pdf_updated_at');
            $table->string('cover_source_name', 255)->nullable()->after('cover_source_url');
            $table->unsignedBigInteger('cover_source_size')->nullable()->after('cover_source_name');
            $table->string('cover_source_etag', 255)->nullable()->after('cover_source_size');
            $table->timestamp('cover_source_modified')->nullable()->after('cover_source_etag');
            $table->timestamp('cover_image_updated_at')->nullable()->after('cover_source_modified');

            // Index the tag columns so the dashboard can filter/sort "recently updated"
            $table->index('pdf_updated_at');
            $table->index('cover_image_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['pdf_updated_at']);
            $table->dropIndex(['cover_image_updated_at']);
            $table->dropColumn([
                'pdf_source_url',
                'pdf_source_name',
                'pdf_source_size',
                'pdf_source_etag',
                'pdf_source_modified',
                'pdf_updated_at',
                'cover_source_url',
                'cover_source_name',
                'cover_source_size',
                'cover_source_etag',
                'cover_source_modified',
                'cover_image_updated_at',
            ]);
        });
    }
};
