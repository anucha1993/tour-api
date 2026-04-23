<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->string('sync_schedule', 500)->default('0 */2 * * *')->change();
            $table->string('full_sync_schedule', 500)->default('0 3 * * *')->change();
        });
    }

    public function down(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->string('sync_schedule', 100)->default('0 */2 * * *')->change();
            $table->string('full_sync_schedule', 100)->default('0 3 * * *')->change();
        });
    }
};
