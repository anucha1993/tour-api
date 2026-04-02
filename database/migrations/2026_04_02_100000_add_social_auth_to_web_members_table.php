<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_members', function (Blueprint $table) {
            $table->string('google_id', 100)->nullable()->unique()->after('line_id');
            $table->string('facebook_id', 100)->nullable()->unique()->after('google_id');
            $table->timestamp('google_linked_at')->nullable()->after('facebook_id');
            $table->timestamp('facebook_linked_at')->nullable()->after('google_linked_at');
            $table->timestamp('line_linked_at')->nullable()->after('facebook_linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('web_members', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'facebook_id', 'google_linked_at', 'facebook_linked_at', 'line_linked_at']);
        });
    }
};
