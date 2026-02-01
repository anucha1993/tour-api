<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general'); // general, sync, aggregation, etc.
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json, array
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // ส่งให้ frontend ได้ไหม
            $table->timestamps();
            
            $table->index(['group', 'key']);
        });
        
        // Insert default aggregation settings
        DB::table('settings')->insert([
            [
                'group' => 'aggregation',
                'key' => 'tour_aggregations',
                'value' => json_encode([
                    'price_adult' => 'min',
                    'discount_adult' => 'max',
                    'min_price' => 'min',
                    'max_price' => 'max',
                    'display_price' => 'min',
                    'discount_amount' => 'max',
                ]),
                'type' => 'json',
                'description' => 'Tour aggregation methods: min, max, avg, first',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
