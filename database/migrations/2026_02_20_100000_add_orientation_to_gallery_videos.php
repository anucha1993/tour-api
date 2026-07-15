<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gallery_videos', function (Blueprint $table) {
            if (!Schema::hasColumn('gallery_videos', 'orientation')) {
                // 'landscape' (16:9 - regular YouTube) | 'portrait' (9:16 - YouTube Shorts)
                $table->string('orientation', 20)->default('landscape')->after('video_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gallery_videos', function (Blueprint $table) {
            if (Schema::hasColumn('gallery_videos', 'orientation')) {
                $table->dropColumn('orientation');
            }
        });
    }
};
