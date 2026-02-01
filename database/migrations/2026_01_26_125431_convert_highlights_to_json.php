<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add new JSON columns
        Schema::table('tours', function (Blueprint $table) {
            // Check if old columns exist and add new ones
            if (!Schema::hasColumn('tours', 'shopping_highlights')) {
                $table->json('shopping_highlights')->nullable()->after('highlights');
            }
            if (!Schema::hasColumn('tours', 'food_highlights')) {
                $table->json('food_highlights')->nullable()->after('shopping_highlights');
            }
            if (!Schema::hasColumn('tours', 'special_highlights')) {
                $table->json('special_highlights')->nullable()->after('food_highlights');
            }
        });

        // Step 2: Migrate data from old columns to new if they exist
        if (Schema::hasColumn('tours', 'shopping_highlight')) {
            DB::table('tours')->whereNotNull('shopping_highlight')->get()->each(function ($tour) {
                DB::table('tours')->where('id', $tour->id)->update([
                    'shopping_highlights' => json_encode([$tour->shopping_highlight])
                ]);
            });
        }
        
        if (Schema::hasColumn('tours', 'food_highlight')) {
            DB::table('tours')->whereNotNull('food_highlight')->get()->each(function ($tour) {
                DB::table('tours')->where('id', $tour->id)->update([
                    'food_highlights' => json_encode([$tour->food_highlight])
                ]);
            });
        }
        
        if (Schema::hasColumn('tours', 'special_highlight')) {
            DB::table('tours')->whereNotNull('special_highlight')->get()->each(function ($tour) {
                DB::table('tours')->where('id', $tour->id)->update([
                    'special_highlights' => json_encode([$tour->special_highlight])
                ]);
            });
        }

        // Step 3: Convert highlights text to JSON array (split by newlines or commas)
        DB::table('tours')->whereNotNull('highlights')->get()->each(function ($tour) {
            $highlights = $tour->highlights;
            if (!empty($highlights)) {
                // Check if it's already JSON
                $decoded = json_decode($highlights);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Already JSON array, skip
                    return;
                }
                // Split by newlines, commas, or semicolons
                $items = preg_split('/[\n,;]+/', $highlights);
                $items = array_map('trim', $items);
                $items = array_filter($items);
                $items = array_values($items);
                
                DB::table('tours')->where('id', $tour->id)->update([
                    'highlights' => json_encode($items)
                ]);
            }
        });

        // Step 4: Drop old columns
        Schema::table('tours', function (Blueprint $table) {
            if (Schema::hasColumn('tours', 'shopping_highlight')) {
                $table->dropColumn('shopping_highlight');
            }
            if (Schema::hasColumn('tours', 'food_highlight')) {
                $table->dropColumn('food_highlight');
            }
            if (Schema::hasColumn('tours', 'special_highlight')) {
                $table->dropColumn('special_highlight');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Recreate old columns
            if (!Schema::hasColumn('tours', 'shopping_highlight')) {
                $table->string('shopping_highlight', 255)->nullable();
            }
            if (!Schema::hasColumn('tours', 'food_highlight')) {
                $table->string('food_highlight', 255)->nullable();
            }
            if (!Schema::hasColumn('tours', 'special_highlight')) {
                $table->string('special_highlight', 255)->nullable();
            }
            
            // Drop new columns
            if (Schema::hasColumn('tours', 'shopping_highlights')) {
                $table->dropColumn('shopping_highlights');
            }
            if (Schema::hasColumn('tours', 'food_highlights')) {
                $table->dropColumn('food_highlights');
            }
            if (Schema::hasColumn('tours', 'special_highlights')) {
                $table->dropColumn('special_highlights');
            }
        });
    }
};
