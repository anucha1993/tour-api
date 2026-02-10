<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Period;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SystemSettingsController extends Controller
{
    /**
     * Get all system settings
     */
    public function index()
    {
        try {
            $settings = SystemSetting::getAllGrouped();
            
            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get system settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system settings',
            ], 500);
        }
    }

    /**
     * Get settings by group
     */
    public function getByGroup(string $group)
    {
        try {
            $settings = SystemSetting::getByGroup($group);
            
            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get {$group} settings: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Failed to get {$group} settings",
            ], 500);
        }
    }

    /**
     * Get sync settings (convenience endpoint)
     */
    public function getSyncSettings()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => SystemSetting::getSyncSettings(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get sync settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync settings',
            ], 500);
        }
    }

    /**
     * Update sync settings
     */
    public function updateSyncSettings(Request $request)
    {
        try {
            $validated = $request->validate([
                'respect_manual_overrides' => 'sometimes|boolean',
                'always_sync_fields' => 'sometimes|array',
                'never_sync_fields' => 'sometimes|array',
                'skip_past_periods' => 'sometimes|boolean',
                'skip_disabled_tours' => 'sometimes|boolean',
            ]);

            foreach ($validated as $key => $value) {
                SystemSetting::setValue("sync.{$key}", $value);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sync settings updated successfully',
                'data' => SystemSetting::getSyncSettings(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update sync settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sync settings',
            ], 500);
        }
    }

    /**
     * Get auto-close settings (convenience endpoint)
     */
    public function getAutoCloseSettings()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => SystemSetting::getAutoCloseSettings(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get auto-close settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get auto-close settings',
            ], 500);
        }
    }

    /**
     * Update auto-close settings
     */
    public function updateAutoCloseSettings(Request $request)
    {
        try {
            $validated = $request->validate([
                'enabled' => 'sometimes|boolean',
                'periods' => 'sometimes|boolean',
                'tours' => 'sometimes|boolean',
                'threshold_days' => 'sometimes|integer|min:0',
                'run_time' => 'sometimes|string|regex:/^\d{2}:\d{2}$/',
            ]);

            foreach ($validated as $key => $value) {
                SystemSetting::setValue("auto_close.{$key}", $value);
            }

            return response()->json([
                'success' => true,
                'message' => 'Auto-close settings updated successfully',
                'data' => SystemSetting::getAutoCloseSettings(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update auto-close settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update auto-close settings',
            ], 500);
        }
    }

    /**
     * Run auto-close manually
     */
    public function runAutoClose()
    {
        try {
            $settings = SystemSetting::getAutoCloseSettings();
            $thresholdDays = $settings['threshold_days'] ?? 0;
            $thresholdDate = Carbon::now()->subDays($thresholdDays)->toDateString();

            $periodsClosed = 0;
            $toursClosed = 0;

            // Close expired periods
            if ($settings['periods']) {
                $periodsClosed = Period::where('status', '!=', 'closed')
                    ->where('start_date', '<', $thresholdDate)
                    ->update(['status' => 'closed']);
            }

            // Close tours with no open periods
            if ($settings['tours']) {
                $toursWithNoOpenPeriods = Tour::where('status', '!=', 'closed')
                    ->whereDoesntHave('periods', function ($query) {
                        $query->where('status', '!=', 'closed');
                    })
                    ->get();

                foreach ($toursWithNoOpenPeriods as $tour) {
                    $tour->update(['status' => 'closed']);
                    $toursClosed++;
                }
            }

            Log::info("Auto-close completed: {$periodsClosed} periods, {$toursClosed} tours closed (threshold: {$thresholdDate})");

            return response()->json([
                'success' => true,
                'message' => 'Auto-close completed successfully',
                'data' => [
                    'periods_closed' => $periodsClosed,
                    'tours_closed' => $toursClosed,
                    'threshold_date' => $thresholdDate,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to run auto-close: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run auto-close: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a single setting
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'key' => 'required|string',
                'value' => 'required',
                'type' => 'sometimes|string|in:string,boolean,integer,float,json,array',
            ]);

            $setting = SystemSetting::setValue(
                $validated['key'],
                $validated['value'],
                $validated['type'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully',
                'data' => $setting,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update setting: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting',
            ], 500);
        }
    }

    /**
     * Clear settings cache
     */
    public function clearCache()
    {
        try {
            SystemSetting::clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Settings cache cleared',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear settings cache: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear settings cache',
            ], 500);
        }
    }
}
