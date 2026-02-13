<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Get footer configuration
     */
    public function getFooterConfig(): JsonResponse
    {
        $footerConfig = Setting::get('footer_config', [
            'newsletter_title' => 'ติดตามเพื่อรับโปรโมชั่น',
            'newsletter_show' => true,
            'scam_warning_title' => 'ระวัง !! กลุ่มมิจฉาชีพขายทัวร์และบริการอื่นๆ',
            'scam_warning_text' => 'โดยแอบอ้างใช้ชื่อบริษัทเน็กซ์ ทริป ฮอลิเดย์ กรุณาชำระค่าบริการผ่านธนาคารชื่อบัญชีบริษัท "เน็กซ์ ทริป ฮอลิเดย์ จำกัด" เท่านั้น',
            'scam_warning_show' => true,
            'company_description' => 'บริษัททัวร์ชั้นนำ ให้บริการจัดทัวร์ท่องเที่ยวทั้งในและต่างประเทศ ด้วยประสบการณ์กว่า 10 ปี พร้อมทีมงานมืออาชีพดูแลตลอดการเดินทาง',
            'license_number' => 'TAT: 11/07440',
            'line_id' => '@nexttripholiday',
            'line_url' => 'https://line.me/R/ti/p/@nexttripholiday',
            'line_qr_image' => '',
            'col1_title' => 'ทัวร์ยอดนิยม',
            'col2_title' => 'บริษัท',
            'col3_title' => 'ช่วยเหลือ',
            'features' => [
                ['icon' => 'Shield', 'label' => 'ใบอนุญาตถูกต้อง'],
                ['icon' => 'CreditCard', 'label' => 'ชำระเงินปลอดภัย'],
                ['icon' => 'Headphones', 'label' => 'บริการ 24 ชม.'],
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $footerConfig,
        ]);
    }

    /**
     * Get footer configuration (public - no auth)
     */
    public function getFooterConfigPublic(): JsonResponse
    {
        $footerConfig = Setting::get('footer_config', [
            'newsletter_title' => 'ติดตามเพื่อรับโปรโมชั่น',
            'newsletter_show' => true,
            'scam_warning_title' => 'ระวัง !! กลุ่มมิจฉาชีพขายทัวร์และบริการอื่นๆ',
            'scam_warning_text' => 'โดยแอบอ้างใช้ชื่อบริษัทเน็กซ์ ทริป ฮอลิเดย์ กรุณาชำระค่าบริการผ่านธนาคารชื่อบัญชีบริษัท "เน็กซ์ ทริป ฮอลิเดย์ จำกัด" เท่านั้น',
            'scam_warning_show' => true,
            'company_description' => 'บริษัททัวร์ชั้นนำ ให้บริการจัดทัวร์ท่องเที่ยวทั้งในและต่างประเทศ ด้วยประสบการณ์กว่า 10 ปี พร้อมทีมงานมืออาชีพดูแลตลอดการเดินทาง',
            'license_number' => 'TAT: 11/07440',
            'line_id' => '@nexttripholiday',
            'line_url' => 'https://line.me/R/ti/p/@nexttripholiday',
            'line_qr_image' => '',
            'col1_title' => 'ทัวร์ยอดนิยม',
            'col2_title' => 'บริษัท',
            'col3_title' => 'ช่วยเหลือ',
            'features' => [
                ['icon' => 'Shield', 'label' => 'ใบอนุญาตถูกต้อง'],
                ['icon' => 'CreditCard', 'label' => 'ชำระเงินปลอดภัย'],
                ['icon' => 'Headphones', 'label' => 'บริการ 24 ชม.'],
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $footerConfig,
        ]);
    }

    /**
     * Update footer configuration
     */
    public function updateFooterConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'newsletter_title' => 'nullable|string|max:255',
            'newsletter_show' => 'nullable|boolean',
            'scam_warning_title' => 'nullable|string|max:500',
            'scam_warning_text' => 'nullable|string|max:1000',
            'scam_warning_show' => 'nullable|boolean',
            'company_description' => 'nullable|string|max:1000',
            'license_number' => 'nullable|string|max:100',
            'line_id' => 'nullable|string|max:100',
            'line_url' => 'nullable|string|max:500',
            'col1_title' => 'nullable|string|max:100',
            'col2_title' => 'nullable|string|max:100',
            'col3_title' => 'nullable|string|max:100',
            'features' => 'nullable|array|max:10',
            'features.*.icon' => 'required_with:features|string|max:50',
            'features.*.label' => 'required_with:features|string|max:100',
        ]);

        // Merge with current config
        $currentConfig = Setting::get('footer_config', []);
        $newConfig = array_merge($currentConfig, $validated);

        Setting::set('footer_config', $newConfig, 'footer', 'json');

        return response()->json([
            'success' => true,
            'message' => 'Footer configuration updated successfully',
            'data' => Setting::get('footer_config'),
        ]);
    }

    /**
     * Upload LINE QR code image
     */
    public function uploadLineQrImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ]);

        try {
            $file = $request->file('image');
            $disk = Storage::disk('r2');

            // Delete old QR if exists
            $currentConfig = Setting::get('footer_config', []);
            if (!empty($currentConfig['line_qr_image'])) {
                $r2Url = rtrim(env('R2_URL'), '/');
                $oldPath = str_replace($r2Url . '/', '', $currentConfig['line_qr_image']);
                if ($oldPath && $disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }
            }

            // Upload new QR image
            $filename = 'line-qr-' . time() . '.' . $file->getClientOriginalExtension();
            $path = $disk->putFileAs('footer', $file, $filename);
            $imageUrl = rtrim(env('R2_URL'), '/') . '/' . $path;

            // Update config
            $currentConfig['line_qr_image'] = $imageUrl;
            Setting::set('footer_config', $currentConfig, 'footer', 'json');

            return response()->json([
                'success' => true,
                'message' => 'QR code image uploaded successfully',
                'data' => [
                    'line_qr_image' => $imageUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get OTP configuration
     */
    public function getOtpConfig(): JsonResponse
    {
        $otpConfig = Setting::get('otp_config', [
            'endpoint' => 'https://api-v2.thaibulksms.com',
            'api_key' => '',
            'api_secret' => '',
            'sender' => 'SMS.',
            'enabled' => true,
            'debug_mode' => false,
        ]);

        // Mask sensitive fields
        if (!empty($otpConfig['api_key'])) {
            $otpConfig['api_key_masked'] = substr($otpConfig['api_key'], 0, 8) . str_repeat('•', 10);
            $otpConfig['has_api_key'] = true;
        } else {
            $otpConfig['api_key_masked'] = '';
            $otpConfig['has_api_key'] = false;
        }

        if (!empty($otpConfig['api_secret'])) {
            $otpConfig['api_secret_masked'] = str_repeat('•', 12);
            $otpConfig['has_api_secret'] = true;
        } else {
            $otpConfig['api_secret_masked'] = '';
            $otpConfig['has_api_secret'] = false;
        }

        unset($otpConfig['api_key'], $otpConfig['api_secret']);

        return response()->json([
            'success' => true,
            'data' => $otpConfig,
        ]);
    }

    /**
     * Update OTP configuration
     */
    public function updateOtpConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|url|max:255',
            'api_key' => 'nullable|string|max:255',
            'api_secret' => 'nullable|string|max:255',
            'sender' => 'required|string|max:50',
            'enabled' => 'boolean',
            'debug_mode' => 'boolean',
        ]);

        // Get current config
        $currentConfig = Setting::get('otp_config', []);

        // Keep existing api_key if not provided
        if (empty($validated['api_key']) && !empty($currentConfig['api_key'])) {
            $validated['api_key'] = $currentConfig['api_key'];
        } else if (!empty($validated['api_key'])) {
            $validated['api_key'] = encrypt($validated['api_key']);
        }

        // Keep existing api_secret if not provided
        if (empty($validated['api_secret']) && !empty($currentConfig['api_secret'])) {
            $validated['api_secret'] = $currentConfig['api_secret'];
        } else if (!empty($validated['api_secret'])) {
            $validated['api_secret'] = encrypt($validated['api_secret']);
        }

        Setting::set('otp_config', $validated, 'otp', 'json');

        return response()->json([
            'success' => true,
            'message' => 'OTP configuration updated successfully',
        ]);
    }

    /**
     * Test OTP by sending a test SMS
     */
    public function testOtpConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:10|max:15',
        ]);

        $otpConfig = Setting::get('otp_config');

        if (!$otpConfig || empty($otpConfig['api_key'])) {
            return response()->json([
                'success' => false,
                'message' => 'OTP configuration not found. Please save settings first.',
            ], 400);
        }

        try {
            // Decrypt credentials
            $apiKey = '';
            $apiSecret = '';

            if (!empty($otpConfig['api_key'])) {
                try {
                    $apiKey = decrypt($otpConfig['api_key']);
                } catch (\Exception $e) {
                    $apiKey = $otpConfig['api_key'];
                }
            }

            if (!empty($otpConfig['api_secret'])) {
                try {
                    $apiSecret = decrypt($otpConfig['api_secret']);
                } catch (\Exception $e) {
                    $apiSecret = $otpConfig['api_secret'];
                }
            }

            // Normalize phone
            $phone = preg_replace('/[^\d]/', '', $validated['phone']);
            if (preg_match('/^0\d{9}$/', $phone)) {
                $phone = '66' . substr($phone, 1);
            }

            // Send test SMS
            $response = \Illuminate\Support\Facades\Http::withBasicAuth($apiKey, $apiSecret)
                ->timeout(30)
                ->post($otpConfig['endpoint'] . '/sms', [
                    'msisdn' => $phone,
                    'message' => 'ทดสอบการส่ง SMS จากระบบ NextTrip',
                    'sender' => $otpConfig['sender'] ?? 'SMS.',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'ส่ง SMS ทดสอบสำเร็จ',
                    'remaining_credit' => $data['remaining_credit'] ?? null,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'ส่ง SMS ไม่สำเร็จ: ' . $response->body(),
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ==================== Why Choose Us ====================

    private function whyChooseUsDefaults(): array
    {
        return [
            'title' => 'ทำไมต้องเลือกเรา?',
            'subtitle' => 'NextTrip พร้อมให้บริการคุณด้วยมาตรฐานสูงสุด',
            'show' => true,
            'items' => [
                ['icon' => 'Shield', 'title' => 'ใบอนุญาตถูกต้อง', 'description' => 'ได้รับใบอนุญาตจาก ททท. และ กรมการท่องเที่ยว'],
                ['icon' => 'Award', 'title' => 'ประสบการณ์กว่า 10 ปี', 'description' => 'ทีมงานมืออาชีพพร้อมดูแลตลอดการเดินทาง'],
                ['icon' => 'Clock', 'title' => 'บริการ 24 ชั่วโมง', 'description' => 'ติดต่อเราได้ตลอดเวลาทั้งก่อนและระหว่างเดินทาง'],
                ['icon' => 'Plane', 'title' => 'สายการบินชั้นนำ', 'description' => 'ร่วมกับสายการบินชั้นนำระดับโลก'],
            ],
        ];
    }

    public function getWhyChooseUsConfig(): JsonResponse
    {
        $config = Setting::get('why_choose_us_config', $this->whyChooseUsDefaults());

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    public function getWhyChooseUsConfigPublic(): JsonResponse
    {
        $config = Setting::get('why_choose_us_config', $this->whyChooseUsDefaults());

        // If hidden, return empty
        if (empty($config['show'])) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    public function updateWhyChooseUsConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'show' => 'nullable|boolean',
            'items' => 'nullable|array|max:12',
            'items.*.icon' => 'required_with:items|string|max:50',
            'items.*.title' => 'required_with:items|string|max:100',
            'items.*.description' => 'required_with:items|string|max:500',
        ]);

        $currentConfig = Setting::get('why_choose_us_config', $this->whyChooseUsDefaults());
        $newConfig = array_merge($currentConfig, $validated);

        Setting::set('why_choose_us_config', $newConfig, 'website', 'json');

        return response()->json([
            'success' => true,
            'message' => 'Why Choose Us configuration updated successfully',
            'data' => Setting::get('why_choose_us_config'),
        ]);
    }
}
