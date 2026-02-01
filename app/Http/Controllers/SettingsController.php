<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * Get all settings (grouped)
     */
    public function index(Request $request): JsonResponse
    {
        $group = $request->query('group');
        
        if ($group) {
            $settings = Setting::getByGroup($group);
        } else {
            $settings = Setting::all()->groupBy('group')->map(function ($items) {
                return $items->mapWithKeys(function ($item) {
                    return [$item->key => [
                        'value' => Setting::castValue($item->value, $item->type),
                        'type' => $item->type,
                        'description' => $item->description,
                    ]];
                });
            });
        }
        
        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Get a specific setting
     */
    public function show(string $key): JsonResponse
    {
        $value = Setting::get($key);
        
        if ($value === null) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }
        
        $setting = Setting::where('key', $key)->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'key' => $key,
                'value' => $value,
                'type' => $setting?->type,
                'description' => $setting?->description,
                'group' => $setting?->group,
            ],
        ]);
    }

    /**
     * Update a setting
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required',
            'group' => 'nullable|string',
            'type' => 'nullable|string|in:string,integer,boolean,json,array',
            'description' => 'nullable|string',
        ]);
        
        $setting = Setting::where('key', $key)->first();
        
        if ($setting) {
            $type = $validated['type'] ?? $setting->type;
            $value = $validated['value'];
            
            // Prepare value for storage
            if (in_array($type, ['json', 'array']) && is_array($value)) {
                $value = json_encode($value);
            }
            
            $setting->update([
                'value' => $value,
                'type' => $type,
                'description' => $validated['description'] ?? $setting->description,
                'group' => $validated['group'] ?? $setting->group,
            ]);
        } else {
            $type = $validated['type'] ?? 'string';
            $value = $validated['value'];
            
            if (in_array($type, ['json', 'array']) && is_array($value)) {
                $value = json_encode($value);
            }
            
            $setting = Setting::create([
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'group' => $validated['group'] ?? 'general',
                'description' => $validated['description'] ?? null,
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully',
            'data' => [
                'key' => $key,
                'value' => Setting::get($key),
                'type' => $setting->type,
            ],
        ]);
    }

    /**
     * Get aggregation config (global + api config overrides)
     */
    public function getAggregationConfig(): JsonResponse
    {
        $globalConfig = Setting::get('tour_aggregations', [
            'price_adult' => 'min',
            'discount_adult' => 'max',
            'min_price' => 'min',
            'max_price' => 'max',
            'display_price' => 'min',
            'discount_amount' => 'max',
        ]);
        
        // Get promotion thresholds
        $promotionThresholds = Setting::get('promotion_thresholds', [
            'fire_sale_min_percent' => 30,  // โปรไฟไหม้ >= 30%
            'normal_promo_min_percent' => 1, // โปรธรรมดา >= 1% (และ < fire_sale)
        ]);
        
        // Get all api config overrides
        $apiConfigOverrides = \App\Models\WholesalerApiConfig::whereNotNull('aggregation_config')
            ->with('wholesaler:id,name,code')
            ->get(['id', 'wholesaler_id', 'aggregation_config'])
            ->map(function ($config) {
                return [
                    'api_config_id' => $config->id,
                    'api_name' => 'API #' . $config->id,
                    'wholesaler_id' => $config->wholesaler_id,
                    'wholesaler_name' => $config->wholesaler?->name,
                    'wholesaler_code' => $config->wholesaler?->code,
                    'aggregation_config' => $config->aggregation_config,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'global' => $globalConfig,
                'promotion_thresholds' => $promotionThresholds,
                'options' => ['min', 'max', 'avg', 'first'],
                'fields' => [
                    'price_adult' => 'ราคาผู้ใหญ่',
                    'discount_adult' => 'ส่วนลดผู้ใหญ่',
                    'min_price' => 'ราคาต่ำสุด',
                    'max_price' => 'ราคาสูงสุด',
                    'display_price' => 'ราคาที่แสดง',
                    'discount_amount' => 'จำนวนส่วนลด',
                ],
                'api_config_overrides' => $apiConfigOverrides,
            ],
        ]);
    }

    /**
     * Update global aggregation config
     */
    public function updateAggregationConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'config' => 'sometimes|array',
            'config.price_adult' => 'sometimes|in:min,max,avg,first',
            'config.discount_adult' => 'sometimes|in:min,max,avg,first',
            'config.min_price' => 'sometimes|in:min,max,avg,first',
            'config.max_price' => 'sometimes|in:min,max,avg,first',
            'config.display_price' => 'sometimes|in:min,max,avg,first',
            'config.discount_amount' => 'sometimes|in:min,max,avg,first',
            'promotion_thresholds' => 'sometimes|array',
            'promotion_thresholds.fire_sale_min_percent' => 'sometimes|numeric|min:1|max:100',
            'promotion_thresholds.normal_promo_min_percent' => 'sometimes|numeric|min:0|max:100',
        ]);
        
        if (isset($validated['config'])) {
            $currentConfig = Setting::get('tour_aggregations', []);
            $newConfig = array_merge($currentConfig, $validated['config']);
            Setting::set('tour_aggregations', $newConfig, 'aggregation', 'json');
        }
        
        if (isset($validated['promotion_thresholds'])) {
            $currentThresholds = Setting::get('promotion_thresholds', []);
            $newThresholds = array_merge($currentThresholds, $validated['promotion_thresholds']);
            Setting::set('promotion_thresholds', $newThresholds, 'aggregation', 'json');
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => [
                'tour_aggregations' => Setting::get('tour_aggregations'),
                'promotion_thresholds' => Setting::get('promotion_thresholds'),
            ],
        ]);
    }
}
