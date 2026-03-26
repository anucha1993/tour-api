<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            // Integration type: 'config' = standard mapping-based, 'headcode' = custom PHP file
            $table->enum('integration_type', ['config', 'headcode'])
                ->default('config')
                ->after('wholesaler_id')
                ->comment('config = uses field mappings, headcode = custom PHP adapter file');

            // For headcode type: filename in storage/headcode/ (without .php extension)
            // Class name = Headcode{StudlyCase(filename)}Adapter
            $table->string('headcode_file', 100)
                ->nullable()
                ->after('integration_type')
                ->comment('Headcode PHP filename in storage/headcode/ e.g. lookplanets → HeadcodeLookplanetsAdapter');
        });
    }

    public function down(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->dropColumn(['integration_type', 'headcode_file']);
        });
    }
};
