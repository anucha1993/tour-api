<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\PeriodDisplayFilter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Get Period Display settings (Admin)
     */
    public function getPeriodDisplay(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PeriodDisplayFilter::settings(),
        ]);
    }

    /**
     * Update Period Display settings (Admin)
     */
    public function updatePeriodDisplay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hide_past' => 'required|boolean',
            'hide_full' => 'required|boolean',
        ]);

        PeriodDisplayFilter::save(
            (bool) $validated['hide_past'],
            (bool) $validated['hide_full'],
        );

        return response()->json([
            'success' => true,
            'message' => 'บันทึกการตั้งค่ารอบเดินทางสำเร็จ',
            'data' => PeriodDisplayFilter::settings(),
        ]);
    }

    /**
     * Public endpoint — expose the flags (for tour-web to know current policy)
     */
    public function getPeriodDisplayPublic(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PeriodDisplayFilter::settings(),
        ]);
    }

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

    // ═══════════════════════════════════════════
    //  CONTACT POPUP (floating LINE/QR popup)
    // ═══════════════════════════════════════════

    /**
     * Default config for the contact popup
     */
    private function contactPopupDefaults(): array
    {
        return [
            'is_active' => false,
            'heading' => 'จองผ่านไลน์',
            'subheading' => 'ติดต่อข่าวสารโปรโมชั่นทัวร์',
            'mascot_image' => '',
            'mascot_size' => 112, // px, width/height of mascot image
            'qr_image' => '',
            'line_id' => '',
            'line_url' => '',
            'phones' => [],                 // array of {number:string, tel:string}
            'hours_text' => "ทุกวัน\n08.00-20.00 น.",
            'facebook_url' => '',
            'email' => '',
            'theme_color' => '#F97316',
            'position' => 'bottom-right',    // bottom-right | bottom-left
            'display_frequency' => 'once_per_session', // always | once_per_session | once_per_day
            'delay_seconds' => 3,
            'show_close_button' => true,
            'show_on_mobile' => true,
        ];
    }

    /**
     * Get contact popup configuration (admin)
     */
    public function getContactPopupConfig(): JsonResponse
    {
        $config = array_merge(
            $this->contactPopupDefaults(),
            (array) Setting::get('contact_popup_config', [])
        );

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Get contact popup configuration (public - no auth)
     */
    public function getContactPopupConfigPublic(): JsonResponse
    {
        $config = array_merge(
            $this->contactPopupDefaults(),
            (array) Setting::get('contact_popup_config', [])
        );

        // If not active, return minimal payload so clients can short-circuit
        if (empty($config['is_active'])) {
            return response()->json([
                'success' => true,
                'data' => ['is_active' => false],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Update contact popup configuration (admin)
     */
    public function updateContactPopupConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'nullable|boolean',
            'heading' => 'nullable|string|max:100',
            'subheading' => 'nullable|string|max:255',
            'mascot_image' => 'nullable|string|max:1000',
            'mascot_size' => 'nullable|integer|min:40|max:400',
            'qr_image' => 'nullable|string|max:1000',
            'line_id' => 'nullable|string|max:100',
            'line_url' => 'nullable|string|max:500',
            'phones' => 'nullable|array|max:10',
            'phones.*.number' => 'required_with:phones|string|max:50',
            'phones.*.tel' => 'nullable|string|max:50',
            'hours_text' => 'nullable|string|max:255',
            'facebook_url' => 'nullable|string|max:500',
            'email' => 'nullable|string|max:255',
            'theme_color' => 'nullable|string|max:20',
            'position' => 'nullable|in:bottom-right,bottom-left',
            'display_frequency' => 'nullable|in:always,once_per_session,once_per_day',
            'delay_seconds' => 'nullable|integer|min:0|max:60',
            'show_close_button' => 'nullable|boolean',
            'show_on_mobile' => 'nullable|boolean',
        ]);

        $current = array_merge(
            $this->contactPopupDefaults(),
            (array) Setting::get('contact_popup_config', [])
        );
        $newConfig = array_merge($current, $validated);

        Setting::set('contact_popup_config', $newConfig, 'contact_popup', 'json');

        return response()->json([
            'success' => true,
            'message' => 'บันทึก Contact Popup สำเร็จ',
            'data' => $newConfig,
        ]);
    }

    /**
     * Upload QR or mascot image for contact popup.
     * Field name ($field) can be "qr_image" or "mascot_image".
     * Uses Cloudflare Images (same as PopupController), with R2 fallback
     * to avoid dependency on php_fileinfo.
     */
    private function uploadContactPopupImage(Request $request, string $field): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ]);

        if (!in_array($field, ['qr_image', 'mascot_image'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid image field'], 400);
        }

        try {
            $file = $request->file('image');

            $current = array_merge(
                $this->contactPopupDefaults(),
                (array) Setting::get('contact_popup_config', [])
            );

            $cloudflare = app(\App\Services\CloudflareImagesService::class);
            $cfIdKey = $field . '_cf_id';
            $imageUrl = null;

            if ($cloudflare->isConfigured()) {
                // Delete old Cloudflare image if we tracked its id
                if (!empty($current[$cfIdKey])) {
                    try { $cloudflare->delete($current[$cfIdKey]); } catch (\Throwable $e) { /* ignore */ }
                }

                $customId = 'contact-popup-' . str_replace('_', '-', $field) . '-' . uniqid() . '-' . time();
                $result = $cloudflare->uploadFromFile($file, $customId, ['type' => 'contact_popup', 'field' => $field]);

                if (!$result) {
                    return response()->json([
                        'success' => false,
                        'message' => 'อัปโหลดรูปไป Cloudflare Images ไม่สำเร็จ',
                    ], 500);
                }

                $imageUrl = $cloudflare->getDisplayUrl($result['id'], 'public');
                $current[$cfIdKey] = $result['id'];
            } else {
                // Fallback: R2 (requires php_fileinfo for MIME guessing)
                $disk = Storage::disk('r2');

                // Delete old if it lives on R2
                if (!empty($current[$field])) {
                    $r2Url = rtrim((string) env('R2_URL'), '/');
                    if ($r2Url && str_starts_with($current[$field], $r2Url . '/')) {
                        $oldPath = substr($current[$field], strlen($r2Url) + 1);
                        if ($oldPath && $disk->exists($oldPath)) {
                            try { $disk->delete($oldPath); } catch (\Throwable $e) { /* ignore */ }
                        }
                    }
                }

                $filename = 'contact-popup-' . str_replace('_', '-', $field) . '-' . time() . '.' . $file->getClientOriginalExtension();
                $path = $disk->putFileAs('contact-popup', $file, $filename);
                $imageUrl = rtrim((string) env('R2_URL'), '/') . '/' . $path;
                $current[$cfIdKey] = null;
            }

            $current[$field] = $imageUrl;
            Setting::set('contact_popup_config', $current, 'contact_popup', 'json');

            return response()->json([
                'success' => true,
                'message' => 'Upload success',
                'data' => [
                    $field => $imageUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function uploadContactPopupQrImage(Request $request): JsonResponse
    {
        return $this->uploadContactPopupImage($request, 'qr_image');
    }

    public function uploadContactPopupMascotImage(Request $request): JsonResponse
    {
        return $this->uploadContactPopupImage($request, 'mascot_image');
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
            'sender' => [
                'required',
                'string',
                'min:4',
                'max:10',
                'regex:/^[A-Za-z0-9._-]+$/',
                function ($attribute, $value, $fail) {
                    // Reject values that look like Thai phone numbers.
                    if (preg_match('/^(0\d{9}|66\d{9})$/', (string) $value)) {
                        $fail('Sender ต้องไม่เป็นหมายเลขโทรศัพท์');
                    }
                },
            ],
            'enabled' => 'boolean',
            'debug_mode' => 'boolean',
        ]);

        $validated['endpoint'] = rtrim($validated['endpoint'], '/');
        if (str_ends_with(strtolower($validated['endpoint']), '/sms')) {
            $validated['endpoint'] = substr($validated['endpoint'], 0, -4);
        }
        $validated['sender'] = trim($validated['sender']);

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

            $endpoint = rtrim((string) ($otpConfig['endpoint'] ?? 'https://api-v2.thaibulksms.com'), '/');
            if (str_ends_with(strtolower($endpoint), '/sms')) {
                $endpoint = substr($endpoint, 0, -4);
            }

            $sender = trim((string) ($otpConfig['sender'] ?? 'SMS.'));
            if ($sender === '') {
                $sender = 'SMS.';
            }

            // Send test SMS
            $response = \Illuminate\Support\Facades\Http::withBasicAuth($apiKey, $apiSecret)
                ->timeout(30)
                ->post($endpoint . '/sms', [
                    'msisdn' => $phone,
                    'message' => 'ทดสอบการส่ง SMS จากระบบ NextTrip',
                    'sender' => $sender,
                ]);

            /** @var \Illuminate\Http\Client\Response $response */

            if ($response->successful()) {
                $data = $response->json();

                if (!empty($data['error']['code']) && (int) $data['error']['code'] === 111) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sender ไม่ถูกต้องหรือยังไม่ได้อนุมัติใน ThaiBulkSMS กรุณาใช้ Sender ที่ Approved ในบัญชีของคุณ',
                    ], 400);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'ส่ง SMS ทดสอบสำเร็จ',
                    'remaining_credit' => $data['remaining_credit'] ?? null,
                ]);
            } else {
                $data = $response->json() ?? [];
                if (!empty($data['error']['code']) && (int) $data['error']['code'] === 111) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sender ไม่ถูกต้องหรือยังไม่ได้อนุมัติใน ThaiBulkSMS กรุณาใช้ Sender ที่ Approved ในบัญชีของคุณ',
                    ], 400);
                }

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

    /* ────────────────────────────────────────────────────────────────
     *  Email Templates
     * ──────────────────────────────────────────────────────────────── */

    private function defaultEmailTemplates(): array
    {
        return [
            'booking_confirmation' => [
                'enabled'  => true,
                'subject'  => 'ยืนยันการจองทัวร์ - {{booking_code}}',
                'body'     => $this->defaultBookingConfirmationBody(),
                'send_to_admin' => true,
                'admin_emails'  => '',
            ],
            'booking_status_update' => [
                'enabled'  => true,
                'subject'  => 'อัปเดตสถานะการจอง {{booking_code}} - {{status_label}}',
                'body'     => $this->defaultStatusUpdateBody(),
                'send_to_admin' => false,
                'admin_emails'  => '',
            ],
        ];
    }

    private function defaultBookingConfirmationBody(): string
    {
        return <<<'HTML'
<div style="font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;max-width:600px;margin:0 auto;padding:0;background:#f8fafc;">
  <div style="background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%);padding:32px 24px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#ffffff;font-size:22px;margin:0;">🎉 ขอบคุณที่จองทัวร์กับเรา!</h1>
    <p style="color:#bfdbfe;font-size:14px;margin:8px 0 0;">รหัสการจอง: <strong style="color:#fff;">{{booking_code}}</strong></p>
  </div>
  <div style="background:#ffffff;padding:24px;border:1px solid #e2e8f0;border-top:none;">
    <p style="color:#334155;font-size:15px;margin:0 0 16px;">สวัสดีคุณ <strong>{{customer_name}}</strong>,</p>
    <p style="color:#64748b;font-size:14px;margin:0 0 20px;">เราได้รับการจองทัวร์ของท่านเรียบร้อยแล้ว ทีมงานจะตรวจสอบและแจ้งยืนยันให้ทราบภายใน 24 ชั่วโมง</p>
    <div style="background:#f1f5f9;border-radius:8px;padding:16px;margin:0 0 20px;">
      <h3 style="color:#1e293b;font-size:15px;margin:0 0 12px;">📋 รายละเอียดการจอง</h3>
      <table style="width:100%;font-size:14px;color:#475569;" cellpadding="4">
        <tr><td style="padding:4px 0;color:#94a3b8;width:140px;">ทัวร์</td><td style="padding:4px 0;font-weight:600;">{{tour_name}}</td></tr>
        <tr><td style="padding:4px 0;color:#94a3b8;">รหัสทัวร์</td><td style="padding:4px 0;">{{tour_code}}</td></tr>
        <tr><td style="padding:4px 0;color:#94a3b8;">วันเดินทาง</td><td style="padding:4px 0;">{{travel_date}}</td></tr>
        <tr><td style="padding:4px 0;color:#94a3b8;">จำนวนผู้เดินทาง</td><td style="padding:4px 0;">{{total_passengers}} ท่าน</td></tr>
        <tr><td style="padding:4px 0;color:#94a3b8;">ยอดรวม</td><td style="padding:4px 0;font-weight:700;color:#2563eb;font-size:16px;">{{total_amount}}</td></tr>
      </table>
    </div>
    <div style="background:#f1f5f9;border-radius:8px;padding:16px;margin:0 0 20px;">
      <h3 style="color:#1e293b;font-size:15px;margin:0 0 12px;">👤 ข้อมูลผู้จอง</h3>
      <table style="width:100%;font-size:14px;color:#475569;" cellpadding="4">
        <tr><td style="padding:4px 0;color:#94a3b8;width:140px;">ชื่อ-นามสกุล</td><td style="padding:4px 0;">{{customer_name}}</td></tr>
        <tr><td style="padding:4px 0;color:#94a3b8;">โทรศัพท์</td><td style="padding:4px 0;">{{customer_phone}}</td></tr>
        <tr><td style="padding:4px 0;color:#94a3b8;">อีเมล</td><td style="padding:4px 0;">{{customer_email}}</td></tr>
      </table>
    </div>
    <p style="color:#64748b;font-size:13px;margin:20px 0 0;">หากมีข้อสงสัย กรุณาติดต่อเราที่ <strong>02-136-9144</strong> หรือตอบกลับอีเมลนี้</p>
  </div>
  <div style="text-align:center;padding:16px;color:#94a3b8;font-size:12px;border-radius:0 0 12px 12px;">
    <p style="margin:0;">© {{year}} NextTrip — เที่ยวทั่วไทย ไปทั่วโลก</p>
  </div>
</div>
HTML;
    }

    private function defaultStatusUpdateBody(): string
    {
        return <<<'HTML'
<div style="font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;max-width:600px;margin:0 auto;padding:0;background:#f8fafc;">
  <div style="background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%);padding:32px 24px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#ffffff;font-size:22px;margin:0;">อัปเดตสถานะการจอง</h1>
    <p style="color:#bfdbfe;font-size:14px;margin:8px 0 0;">รหัส: <strong style="color:#fff;">{{booking_code}}</strong></p>
  </div>
  <div style="background:#ffffff;padding:24px;border:1px solid #e2e8f0;border-top:none;">
    <p style="color:#334155;font-size:15px;margin:0 0 16px;">สวัสดีคุณ <strong>{{customer_name}}</strong>,</p>
    <p style="color:#64748b;font-size:14px;margin:0 0 20px;">สถานะการจองทัวร์ <strong>{{tour_name}}</strong> ได้เปลี่ยนเป็น:</p>
    <div style="text-align:center;margin:20px 0;">
      <span style="display:inline-block;padding:10px 28px;background:#dbeafe;color:#1d4ed8;font-weight:700;border-radius:24px;font-size:16px;">{{status_label}}</span>
    </div>
    <div style="background:#f1f5f9;border-radius:8px;padding:16px;">
      <table style="width:100%;font-size:14px;color:#475569;" cellpadding="4">
        <tr><td style="padding:4px 0;color:#94a3b8;width:140px;">วันเดินทาง</td><td style="padding:4px 0;">{{travel_date}}</td></tr>
        <tr><td style="padding:4px 0;color:#94a3b8;">ยอดรวม</td><td style="padding:4px 0;font-weight:700;color:#2563eb;">{{total_amount}}</td></tr>
      </table>
    </div>
    <p style="color:#64748b;font-size:13px;margin:20px 0 0;">หากมีข้อสงสัย กรุณาติดต่อเราที่ <strong>02-136-9144</strong></p>
  </div>
  <div style="text-align:center;padding:16px;color:#94a3b8;font-size:12px;">
    <p style="margin:0;">© {{year}} NextTrip — เที่ยวทั่วไทย ไปทั่วโลก</p>
  </div>
</div>
HTML;
    }

    /**
     * Get email templates config
     */
    public function getEmailTemplates(): JsonResponse
    {
        $templates = Setting::get('email_templates', $this->defaultEmailTemplates());

        // Ensure all template types exist (in case new ones were added)
        $defaults = $this->defaultEmailTemplates();
        foreach ($defaults as $key => $def) {
            if (!isset($templates[$key])) {
                $templates[$key] = $def;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $templates,
            'variables' => [
                'booking_code'     => 'รหัสการจอง',
                'customer_name'    => 'ชื่อ-นามสกุลผู้จอง',
                'customer_email'   => 'อีเมลผู้จอง',
                'customer_phone'   => 'โทรศัพท์ผู้จอง',
                'tour_name'        => 'ชื่อทัวร์',
                'tour_code'        => 'รหัสทัวร์',
                'travel_date'      => 'วันเดินทาง',
                'total_passengers' => 'จำนวนผู้เดินทาง',
                'total_amount'     => 'ยอดรวมทั้งหมด',
                'status_label'     => 'สถานะการจอง',
                'year'             => 'ปีปัจจุบัน',
            ],
        ]);
    }

    /**
     * Update email templates config
     */
    public function updateEmailTemplates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_confirmation'                => 'required|array',
            'booking_confirmation.enabled'        => 'required|boolean',
            'booking_confirmation.subject'        => 'required|string|max:255',
            'booking_confirmation.body'           => 'required|string',
            'booking_confirmation.send_to_admin'  => 'required|boolean',
            'booking_confirmation.admin_emails'   => 'nullable|string|max:500',

            'booking_status_update'               => 'required|array',
            'booking_status_update.enabled'       => 'required|boolean',
            'booking_status_update.subject'       => 'required|string|max:255',
            'booking_status_update.body'          => 'required|string',
            'booking_status_update.send_to_admin' => 'required|boolean',
            'booking_status_update.admin_emails'  => 'nullable|string|max:500',
        ]);

        Setting::set('email_templates', $validated, 'mail', 'json');

        return response()->json([
            'success' => true,
            'message' => 'บันทึก Email Template สำเร็จ',
        ]);
    }

    /**
     * Send test email for a specific template
     */
    public function testEmailTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_key' => 'required|in:booking_confirmation,booking_status_update',
            'to_email'     => 'required|email',
        ]);

        $smtpConfig = Setting::get('smtp_config');

        if (!$smtpConfig || empty($smtpConfig['host']) || empty($smtpConfig['enabled'])) {
            return response()->json([
                'success' => false,
                'message' => 'กรุณาตั้งค่าและเปิดใช้งาน SMTP ก่อน (/dashboard/settings/smtp)',
            ], 400);
        }

        $templates = Setting::get('email_templates', $this->defaultEmailTemplates());
        $template  = $templates[$validated['template_key']] ?? null;

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบ Template ที่ระบุ',
            ], 404);
        }

        // Sample data for test
        $sampleData = [
            'booking_code'     => 'BK-20260227-0001',
            'customer_name'    => 'สมชาย ใจดี',
            'customer_email'   => $validated['to_email'],
            'customer_phone'   => '081-234-5678',
            'tour_name'        => 'ทัวร์ญี่ปุ่น โตเกียว ฟูจิ 6วัน4คืน',
            'tour_code'        => 'JP-TYO-001',
            'travel_date'      => '15 มี.ค. 2569 — 20 มี.ค. 2569',
            'total_passengers' => '2',
            'total_amount'     => '฿59,900',
            'status_label'     => 'ยืนยันแล้ว',
            'year'             => (string) date('Y'),
        ];

        $subject = $template['subject'];
        $body    = $template['body'];

        foreach ($sampleData as $key => $value) {
            $subject = str_replace("{{{$key}}}", $value, $subject);
            $body    = str_replace("{{{$key}}}", $value, $body);
        }

        try {
            $password = '';
            if (!empty($smtpConfig['password'])) {
                try {
                    $password = decrypt($smtpConfig['password']);
                } catch (\Exception $e) {
                    $password = $smtpConfig['password'];
                }
            }

            $useTls = $smtpConfig['encryption'] === 'ssl';

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
                ->subject('[ทดสอบ] ' . $subject)
                ->html($body);

            $mailer->send($email);

            return response()->json([
                'success' => true,
                'message' => "ส่งอีเมลทดสอบ Template ไปที่ {$validated['to_email']} สำเร็จ",
            ]);

        } catch (\Exception $e) {
            \Log::error('Email template test failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถส่งอีเมลได้: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset email template to default
     */
    public function resetEmailTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_key' => 'required|in:booking_confirmation,booking_status_update',
        ]);

        $templates = Setting::get('email_templates', $this->defaultEmailTemplates());
        $defaults  = $this->defaultEmailTemplates();

        $templates[$validated['template_key']] = $defaults[$validated['template_key']];

        Setting::set('email_templates', $templates, 'mail', 'json');

        return response()->json([
            'success' => true,
            'message' => 'รีเซ็ต Template เป็นค่าเริ่มต้นสำเร็จ',
            'data'    => $templates[$validated['template_key']],
        ]);
    }

    /**
     * Get Social Auth configuration
     */
    public function getSocialAuthConfig(): JsonResponse
    {
        $config = Setting::get('social_auth_config', [
            'google_enabled' => false,
            'google_client_id' => '',
            'google_client_secret' => '',
            'facebook_enabled' => false,
            'facebook_app_id' => '',
            'facebook_app_secret' => '',
            'line_enabled' => false,
            'line_channel_id' => '',
            'line_channel_secret' => '',
        ]);

        // Mask sensitive fields
        $masked = $config;

        if (!empty($config['google_client_id'])) {
            try {
                $decrypted = decrypt($config['google_client_id']);
                $masked['google_client_id_masked'] = substr($decrypted, 0, 12) . str_repeat('•', 10);
            } catch (\Exception $e) {
                $masked['google_client_id_masked'] = substr($config['google_client_id'], 0, 12) . str_repeat('•', 10);
            }
            $masked['has_google_client_id'] = true;
        } else {
            $masked['google_client_id_masked'] = '';
            $masked['has_google_client_id'] = false;
        }

        if (!empty($config['google_client_secret'])) {
            $masked['has_google_client_secret'] = true;
            $masked['google_client_secret_masked'] = str_repeat('•', 12);
        } else {
            $masked['has_google_client_secret'] = false;
            $masked['google_client_secret_masked'] = '';
        }

        if (!empty($config['facebook_app_id'])) {
            try {
                $decrypted = decrypt($config['facebook_app_id']);
                $masked['facebook_app_id_masked'] = substr($decrypted, 0, 8) . str_repeat('•', 10);
            } catch (\Exception $e) {
                $masked['facebook_app_id_masked'] = substr($config['facebook_app_id'], 0, 8) . str_repeat('•', 10);
            }
            $masked['has_facebook_app_id'] = true;
        } else {
            $masked['facebook_app_id_masked'] = '';
            $masked['has_facebook_app_id'] = false;
        }

        if (!empty($config['facebook_app_secret'])) {
            $masked['has_facebook_app_secret'] = true;
            $masked['facebook_app_secret_masked'] = str_repeat('•', 12);
        } else {
            $masked['has_facebook_app_secret'] = false;
            $masked['facebook_app_secret_masked'] = '';
        }

        if (!empty($config['line_channel_id'])) {
            try {
                $decrypted = decrypt($config['line_channel_id']);
                $masked['line_channel_id_masked'] = substr($decrypted, 0, 8) . str_repeat('•', 10);
            } catch (\Exception $e) {
                $masked['line_channel_id_masked'] = substr($config['line_channel_id'], 0, 8) . str_repeat('•', 10);
            }
            $masked['has_line_channel_id'] = true;
        } else {
            $masked['line_channel_id_masked'] = '';
            $masked['has_line_channel_id'] = false;
        }

        if (!empty($config['line_channel_secret'])) {
            $masked['has_line_channel_secret'] = true;
            $masked['line_channel_secret_masked'] = str_repeat('•', 12);
        } else {
            $masked['has_line_channel_secret'] = false;
            $masked['line_channel_secret_masked'] = '';
        }

        unset($masked['google_client_id'], $masked['google_client_secret']);
        unset($masked['facebook_app_id'], $masked['facebook_app_secret']);
        unset($masked['line_channel_id'], $masked['line_channel_secret']);

        return response()->json(['success' => true, 'data' => $masked]);
    }

    /**
     * Update Social Auth configuration
     */
    public function updateSocialAuthConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'google_enabled' => 'sometimes|boolean',
            'google_client_id' => 'sometimes|nullable|string|max:255',
            'google_client_secret' => 'sometimes|nullable|string|max:255',
            'facebook_enabled' => 'sometimes|boolean',
            'facebook_app_id' => 'sometimes|nullable|string|max:255',
            'facebook_app_secret' => 'sometimes|nullable|string|max:255',
            'line_enabled' => 'sometimes|boolean',
            'line_channel_id' => 'sometimes|nullable|string|max:255',
            'line_channel_secret' => 'sometimes|nullable|string|max:255',
        ]);

        $currentConfig = Setting::get('social_auth_config', []);

        // Start with current config so unspecified fields are preserved
        $newConfig = $currentConfig;

        // Apply non-secret fields (boolean toggles)
        foreach (['google_enabled', 'facebook_enabled', 'line_enabled'] as $field) {
            if (array_key_exists($field, $validated)) {
                $newConfig[$field] = $validated[$field];
            }
        }

        // Handle secret fields: only update if a non-empty new value was sent;
        // otherwise keep the existing (already encrypted) value.
        $secretFields = [
            'google_client_id', 'google_client_secret',
            'facebook_app_id', 'facebook_app_secret',
            'line_channel_id', 'line_channel_secret',
        ];

        foreach ($secretFields as $field) {
            if (array_key_exists($field, $validated) && !empty($validated[$field])) {
                $newConfig[$field] = encrypt($validated[$field]);
            }
        }

        Setting::set('social_auth_config', $newConfig, 'social_auth', 'json');

        return response()->json([
            'success' => true,
            'message' => 'Social Auth configuration updated successfully',
        ]);
    }
}
