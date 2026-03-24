<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->boolean('sync_locked')->default(false)->after('is_visible')
                ->comment('ถ้า true ข้อมูลรอบนี้จะไม่ถูก overwrite จากการ sync อัตโนมัติ');
        });
    }

    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn('sync_locked');
        });
    }
};
