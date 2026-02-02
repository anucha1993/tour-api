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
        
        // Get sync settings
        $syncSettings = Setting::get('sync_settings', [
            'skip_past_periods' => true,           // ข้าม Period ที่วันออกเดินทางเป็นอดีต
            'past_period_threshold_days' => 0,     // จำนวนวันก่อนวันปัจจุบัน (0 = วันนี้)
            'auto_close_past_periods' => false,    // ปิด Period ที่ผ่านไปแล้วอัตโนมัติ
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
                'sync_settings' => $syncSettings,
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
            'sync_settings' => 'sometimes|array',
            'sync_settings.skip_past_periods' => 'sometimes|boolean',
            'sync_settings.past_period_threshold_days' => 'sometimes|integer|min:0|max:365',
            'sync_settings.auto_close_past_periods' => 'sometimes|boolean',
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
        
        if (isset($validated['sync_settings'])) {
            $currentSyncSettings = Setting::get('sync_settings', []);
            $newSyncSettings = array_merge($currentSyncSettings, $validated['sync_settings']);
            Setting::set('sync_settings', $newSyncSettings, 'aggregation', 'json');
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => [
                'tour_aggregations' => Setting::get('tour_aggregations'),
                'promotion_thresholds' => Setting::get('promotion_thresholds'),
                'sync_settings' => Setting::get('sync_settings'),
            ],
        ]);
    }

    /**
     * Get SMTP configuration
     */
    public function getSmtpConfig(): JsonResponse
    {
        $smtpConfig = Setting::get('smtp_config', [
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_address' => '',
            'from_name' => '',
            'enabled' => false,
        ]);

        // ซ่อน password ไม่ให้แสดงทั้งหมด
        if (!empty($smtpConfig['password'])) {
            $smtpConfig['password_masked'] = str_repeat('•', 8);
            $smtpConfig['has_password'] = true;
        } else {
            $smtpConfig['password_masked'] = '';
            $smtpConfig['has_password'] = false;
        }
        unset($smtpConfig['password']);

        return response()->json([
            'success' => true,
            'data' => $smtpConfig,
        ]);
    }

    /**
     * Update SMTP configuration
     */
    public function updateSmtpConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|in:tls,ssl,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'enabled' => 'boolean',
        ]);

        // Get current config for password handling
        $currentConfig = Setting::get('smtp_config', []);

        // If password is empty, keep the old one (already encrypted)
        if (empty($validated['password']) && !empty($currentConfig['password'])) {
            $validated['password'] = $currentConfig['password'];
        } else if (!empty($validated['password'])) {
            // Only encrypt if it's a new password
            $validated['password'] = encrypt($validated['password']);
        }

        Setting::set('smtp_config', $validated, 'mail', 'json');

        return response()->json([
            'success' => true,
            'message' => 'SMTP configuration updated successfully',
        ]);
    }

    /**
     * Test SMTP connection by sending a test email
     */
    public function testSmtpConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_email' => 'required|email',
        ]);

        $smtpConfig = Setting::get('smtp_config');

        if (!$smtpConfig || empty($smtpConfig['host'])) {
            return response()->json([
                'success' => false,
                'message' => 'SMTP configuration not found. Please save settings first.',
            ], 400);
        }

        try {
            // Decrypt password
            $password = '';
            if (!empty($smtpConfig['password'])) {
                try {
                    $password = decrypt($smtpConfig['password']);
                } catch (\Exception $e) {
                    // If decryption fails, use as-is (legacy data)
                    $password = $smtpConfig['password'];
                }
            }

            // Create a temporary mailer config
            // TLS (port 587) = STARTTLS, third param = false
            // SSL (port 465) = implicit SSL, third param = true
            // none = no encryption, third param = false
            $useTls = $smtpConfig['encryption'] === 'ssl'; // true = implicit SSL
            
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $smtpConfig['host'],
                (int) $smtpConfig['port'],
                $useTls
            );

            if (!empty($smtpConfig['username'])) {
                $transport->setUsername($smtpConfig['username']);
            }
            if (!empty($password)) {
                $transport->setPassword($password);
            }

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address(
                    $smtpConfig['from_address'],
                    $smtpConfig['from_name']
                ))
                ->to($validated['to_email'])
                ->subject('ทดสอบการส่งอีเมล - NextTrip')
                ->html('
                    <div style="font-family: sans-serif; padding: 20px;">
                        <h2 style="color: #2563eb;">🎉 ทดสอบการส่งอีเมลสำเร็จ!</h2>
                        <p>อีเมลนี้ถูกส่งจากระบบ NextTrip เพื่อทดสอบการตั้งค่า SMTP</p>
                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
                        <p style="color: #6b7280; font-size: 14px;">
                            <strong>SMTP Server:</strong> ' . $smtpConfig['host'] . '<br>
                            <strong>Port:</strong> ' . $smtpConfig['port'] . '<br>
                            <strong>Encryption:</strong> ' . strtoupper($smtpConfig['encryption']) . '<br>
                            <strong>From:</strong> ' . $smtpConfig['from_name'] . ' &lt;' . $smtpConfig['from_address'] . '&gt;
                        </p>
                        <p style="color: #9ca3af; font-size: 12px; margin-top: 30px;">
                            ส่งเมื่อ: ' . now()->format('d/m/Y H:i:s') . '
                        </p>
                    </div>
                ');

            $mailer->send($email);

            return response()->json([
                'success' => true,
                'message' => "ส่งอีเมลทดสอบไปที่ {$validated['to_email']} สำเร็จ",
            ]);

        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            \Log::error('SMTP test failed', [
                'error' => $e->getMessage(),
                'host' => $smtpConfig['host'],
                'port' => $smtpConfig['port'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถเชื่อมต่อ SMTP Server: ' . $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            \Log::error('SMTP test failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }
}
