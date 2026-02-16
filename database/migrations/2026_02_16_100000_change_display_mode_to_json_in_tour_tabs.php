<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * เปลี่ยน display_mode จาก ENUM เป็น JSON (display_modes) 
     * เพื่อรองรับการเลือกหลายประเภทการแสดงผล
     * เช่น ['tab', 'badge', 'promotion']
     */
    public function up(): void
    {
        // 1. Add new JSON column
        Schema::table('tour_tabs', function (Blueprint $table) {
            $table->json('display_modes')->nullable()->after('display_mode');
        });

        // 2. Migrate data from old enum to new JSON array
        $tabs = DB::table('tour_tabs')->get();
        foreach ($tabs as $tab) {
            $oldMode = $tab->display_mode ?? 'tab';
            
            // Convert old single mode to array
            switch ($oldMode) {
                case 'both':
                    $modes = ['tab', 'badge'];
                    break;
                case 'tab':
                    $modes = ['tab'];
                    break;
                case 'badge':
                    $modes = ['badge'];
                    break;
                case 'period':
                    $modes = ['period'];
                    break;
                default:
                    $modes = ['tab'];
            }
            
            DB::table('tour_tabs')
                ->where('id', $tab->id)
                ->update(['display_modes' => json_encode($modes)]);
        }

        // 3. Remove old enum column
        Schema::table('tour_tabs', function (Blueprint $table) {
            $table->dropColumn('display_mode');
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        // 1. Re-add old enum column
        DB::statement("ALTER TABLE tour_tabs ADD COLUMN display_mode ENUM('tab', 'badge', 'both', 'period') NOT NULL DEFAULT 'tab' AFTER badge_color");

        // 2. Migrate data back
        $tabs = DB::table('tour_tabs')->get();
        foreach ($tabs as $tab) {
            $modes = json_decode($tab->display_modes, true) ?? ['tab'];
            
            if (in_array('tab', $modes) && in_array('badge', $modes)) {
                $mode = 'both';
            } elseif (in_array('badge', $modes)) {
                $mode = 'badge';
            } elseif (in_array('period', $modes)) {
                $mode = 'period';
            } else {
                $mode = 'tab';
            }
            
            DB::table('tour_tabs')
                ->where('id', $tab->id)
                ->update(['display_mode' => $mode]);
        }

        // 3. Remove new JSON column
        Schema::table('tour_tabs', function (Blueprint $table) {
            $table->dropColumn('display_modes');
        });
    }
};
