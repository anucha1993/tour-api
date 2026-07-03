<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WebMember;
use App\Models\WebPasswordResetToken;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class WebAuthController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Return whether OTP sending is currently enabled (public endpoint)
     */
    public function getOtpStatus()
    {
        $otpConfig = Setting::get('otp_config', []);
        $enabled = $otpConfig['enabled'] ?? true;

        return response()->json([
            'success' => true,
            'data' => ['enabled' => (bool) $enabled],
        ]);
    }

    /**
     * Step 1: Request OTP for registration
     */
    public function requestRegisterOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if phone already registered
        try {
            $normalizedPhone = WebMember::normalizePhone($request->phone);
            
            if (WebMember::where('phone', $normalizedPhone)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'phone_exists',
                    'message' => 'หมายเลขโทรศัพท์นี้ถูกใช้งานแล้ว',
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_phone',
                'message' => 'หมายเลขโทรศัพท์ไม่ถูกต้อง',
            ], 400);
        }

        // Request OTP
        $result = $this->otpService->requestOtp(
            $request->phone,
            'register',
            $request->ip(),
            $request->userAgent()
        );

        // If OTP is disabled, allow registration to proceed without OTP
        if (!$result['success'] && ($result['error'] ?? '') === 'disabled') {
            return response()->json([
                'success' => true,
                'otp_disabled' => true,
                'message' => 'OTP ถูกปิดใช้งาน ดำเนินการสมัครสมาชิกได้เลย',
            ]);
        }

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Step 2: Verify OTP and complete registration
     */
    public function register(Request $request)
    {
        $otpConfig = Setting::get('otp_config', []);
        $otpEnabled = $otpConfig['enabled'] ?? true;

        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:web_members,email',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'consent_terms' => 'required|accepted',
            'consent_privacy' => 'required|accepted',
            'consent_marketing' => 'boolean',
        ];

        if ($otpEnabled) {
            $rules['otp_request_id'] = 'required|integer|exists:otp_requests,id';
            $rules['otp'] = 'required|string|size:6';
        } else {
            // When OTP is disabled we still need the phone to create the member
            $rules['phone'] = 'required|string|min:10|max:15';
        }

        $validator = Validator::make($request->all(), $rules, [
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร',
            'password.letters' => 'รหัสผ่านต้องมีตัวอักษร',
            'password.mixed' => 'รหัสผ่านต้องมีตัวพิมพ์เล็กและตัวพิมพ์ใหญ่',
            'password.numbers' => 'รหัสผ่านต้องมีตัวเลข',
            'password.symbols' => 'รหัสผ่านต้องมีอักขระพิเศษ',
            'consent_terms.accepted' => 'กรุณายอมรับข้อกำหนดและเงื่อนไข',
            'consent_privacy.accepted' => 'กรุณายอมรับนโยบายความเป็นส่วนตัว',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Resolve phone: via OTP verification or direct input when OTP disabled
        if ($otpEnabled) {
            $otpResult = $this->otpService->verifyOtp($request->otp_request_id, $request->otp);

            if (!$otpResult['success']) {
                return response()->json($otpResult, 400);
            }

            $phone = $otpResult['phone_msisdn'];
            $phoneVerified = true;
        } else {
            try {
                $phone = WebMember::normalizePhone($request->phone);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_phone',
                    'message' => 'หมายเลขโทรศัพท์ไม่ถูกต้อง',
                ], 400);
            }

            $phoneVerified = false;
        }

        // Check phone uniqueness
        if (WebMember::where('phone', $phone)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'phone_exists',
                'message' => 'หมายเลขโทรศัพท์นี้ถูกใช้งานแล้ว',
            ], 400);
        }

        // Create member
        $member = WebMember::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $phone,
            'password' => Hash::make($request->password),
            'phone_verified' => $phoneVerified,
            'phone_verified_at' => $phoneVerified ? now() : null,
            'consent_terms' => true,
            'consent_privacy' => true,
            'consent_marketing' => $request->consent_marketing ?? false,
            'consent_at' => now(),
            'status' => 'active',
        ]);

        // Create token
        $token = $member->createToken('web-member')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'สมัครสมาชิกสำเร็จ',
            'member' => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'phone' => $member->phone,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Login with email/phone and password
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // email or phone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find member by email or phone
        $login = $request->login;
        $member = null;

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $member = WebMember::where('email', $login)->first();
        } else {
            try {
                $normalizedPhone = WebMember::normalizePhone($login);
                $member = WebMember::where('phone', $normalizedPhone)->first();
            } catch (\Exception $e) {
                // Invalid phone format, try as email
                $member = WebMember::where('email', $login)->first();
            }
        }

        if (!$member) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_credentials',
                'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
            ], 401);
        }

        // Check if account is locked
        if ($member->isLocked()) {
            return response()->json([
                'success' => false,
                'error' => 'account_locked',
                'message' => 'บัญชีถูกล็อกชั่วคราว กรุณาลองใหม่ภายหลัง',
            ], 403);
        }

        // Check if account is active
        if (!$member->isActive()) {
            return response()->json([
                'success' => false,
                'error' => 'account_inactive',
                'message' => 'บัญชีถูกระงับการใช้งาน',
            ], 403);
        }

        // Verify password
        if (!Hash::check($request->password, $member->password)) {
            $member->incrementFailedAttempts();

            return response()->json([
                'success' => false,
                'error' => 'invalid_credentials',
                'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
            ], 401);
        }

        // Update last login
        $member->updateLastLogin($request->ip());

        // Create token
        $token = $member->createToken('web-member')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'member' => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'phone' => $member->phone,
                'avatar' => $member->avatar,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Login with OTP (phone only)
     */
    public function requestLoginOtp(Request $request)
    {
        $otpConfig = Setting::get('otp_config', []);
        $otpEnabled = $otpConfig['enabled'] ?? true;

        if (!$otpEnabled) {
            return response()->json([
                'success' => false,
                'error' => 'otp_disabled',
                'message' => 'ระบบ OTP ถูกปิดใช้งาน',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if phone exists
        try {
            $normalizedPhone = WebMember::normalizePhone($request->phone);
            $member = WebMember::where('phone', $normalizedPhone)->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'error' => 'phone_not_found',
                    'message' => 'ไม่พบหมายเลขโทรศัพท์นี้ในระบบ',
                ], 404);
            }

            if (!$member->isActive()) {
                return response()->json([
                    'success' => false,
                    'error' => 'account_inactive',
                    'message' => 'บัญชีถูกระงับการใช้งาน',
                ], 403);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_phone',
                'message' => 'หมายเลขโทรศัพท์ไม่ถูกต้อง',
            ], 400);
        }

        // Request OTP
        $result = $this->otpService->requestOtp(
            $request->phone,
            'login',
            $request->ip(),
            $request->userAgent(),
            $member->id
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Verify OTP login
     */
    public function verifyLoginOtp(Request $request)
    {
        $otpConfig = Setting::get('otp_config', []);
        $otpEnabled = $otpConfig['enabled'] ?? true;

        if (!$otpEnabled) {
            return response()->json([
                'success' => false,
                'error' => 'otp_disabled',
                'message' => 'ระบบ OTP ถูกปิดใช้งาน',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'otp_request_id' => 'required|integer|exists:otp_requests,id',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify OTP
        $otpResult = $this->otpService->verifyOtp($request->otp_request_id, $request->otp);

        if (!$otpResult['success']) {
            return response()->json($otpResult, 400);
        }

        // Find member
        $member = WebMember::where('phone', $otpResult['phone_msisdn'])->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'error' => 'member_not_found',
                'message' => 'ไม่พบข้อมูลสมาชิก',
            ], 404);
        }

        // Update last login
        $member->updateLastLogin($request->ip());

        // Create token
        $token = $member->createToken('web-member')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'member' => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'phone' => $member->phone,
                'avatar' => $member->avatar,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Request password reset (via email)
     */
    public function requestPasswordReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $member = WebMember::where('email', $request->email)->first();

        // Always return success to prevent email enumeration
        if (!$member) {
            return response()->json([
                'success' => true,
                'message' => 'หากอีเมลนี้มีอยู่ในระบบ คุณจะได้รับลิงก์รีเซ็ตรหัสผ่าน',
            ]);
        }

        // Create reset token
        $resetToken = WebPasswordResetToken::createForEmail($member->email);

        // Send password reset email via configured SMTP (uses Setting smtp_config,
        // same fallback chain as the newsletter subscriber confirmation email).
        $this->sendPasswordResetEmail($member, $resetToken->token);

        return response()->json([
            'success' => true,
            'message' => 'หากอีเมลนี้มีอยู่ในระบบ คุณจะได้รับลิงก์รีเซ็ตรหัสผ่าน',
            // For development only - remove in production
            'debug_token' => config('app.debug') ? $resetToken->token : null,
        ]);
    }

    /**
     * Reset password with token
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $resetToken = WebPasswordResetToken::findValidToken($request->token);

        if (!$resetToken) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_token',
                'message' => 'ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุ',
            ], 400);
        }

        $member = WebMember::where('email', $resetToken->email)->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'error' => 'member_not_found',
                'message' => 'ไม่พบข้อมูลสมาชิก',
            ], 404);
        }

        // Update password
        $member->password = Hash::make($request->password);
        $member->save();

        // Mark token as used
        $resetToken->markAsUsed();

        // Revoke all tokens
        $member->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'รีเซ็ตรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบใหม่',
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'ออกจากระบบสำเร็จ',
        ]);
    }

    /**
     * Get current member profile
     */
    public function me(Request $request)
    {
        $member = $request->user();

        $level = $member->level;

        return response()->json([
            'success' => true,
            'member' => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'phone' => $member->phone,
                'line_id' => $member->line_id,
                'email_verified' => $member->email_verified,
                'phone_verified' => $member->phone_verified,
                'is_verified' => $member->phone_verified || $member->email_verified,
                'avatar' => $member->avatar,
                'birth_date' => $member->birth_date?->format('Y-m-d'),
                'gender' => $member->gender,
                'consent_marketing' => $member->consent_marketing,
                'created_at' => $member->created_at->format('Y-m-d H:i:s'),
                'total_points' => (int) ($member->total_points ?? 0),
                'lifetime_points' => (int) ($member->lifetime_points ?? 0),
                'lifetime_spending' => (float) ($member->lifetime_spending ?? 0),
                'level' => $level ? [
                    'name' => $level->name,
                    'icon' => $level->icon,
                    'color' => $level->color,
                ] : null,
            ],
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $member = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|nullable|email|unique:web_members,email,' . $member->id,
            'line_id' => 'sometimes|nullable|string|max:100',
            'birth_date' => 'sometimes|nullable|date',
            'gender' => 'sometimes|nullable|in:male,female,other',
            'consent_marketing' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $member->fill($request->only([
            'first_name',
            'last_name',
            'email',
            'line_id',
            'birth_date',
            'gender',
            'consent_marketing',
        ]));
        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'อัปเดตข้อมูลสำเร็จ',
            'member' => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'phone' => $member->phone,
                'line_id' => $member->line_id,
                'birth_date' => $member->birth_date?->format('Y-m-d'),
                'gender' => $member->gender,
                'is_verified' => $member->is_verified,
            ],
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $member = $request->user();

        if (!Hash::check($request->current_password, $member->password)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_password',
                'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
            ], 400);
        }

        $member->password = Hash::make($request->password);
        $member->save();

        // Revoke all other tokens
        $member->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'เปลี่ยนรหัสผ่านสำเร็จ',
        ]);
    }

    // ==================== Password Reset Email ====================

    /**
     * Send password reset email via SMTP (Setting::smtp_config, same pattern
     * as the newsletter subscriber confirmation flow). Failures are logged
     * but never bubble up — we always return a generic success message to
     * the user to avoid email enumeration.
     */
    private function sendPasswordResetEmail(WebMember $member, string $token): void
    {
        // Prefer subscriber SMTP if configured (it's usually verified for
        // bulk delivery), otherwise fall back to the main smtp_config.
        $smtpConfig = Setting::get('subscriber_smtp_config');
        if (!$smtpConfig || empty($smtpConfig['host']) || empty($smtpConfig['enabled'])) {
            $smtpConfig = Setting::get('smtp_config');
        }
        if (!$smtpConfig || empty($smtpConfig['host'])) {
            Log::warning('No SMTP configured, skipping password reset email', [
                'email' => $member->email,
            ]);
            return;
        }

        try {
            $frontendUrl = rtrim(env('FRONTEND_URL', 'https://nexttripholiday.com'), '/');
            $resetUrl = $frontendUrl . '/reset-password?token=' . $token;

            $password = '';
            if (!empty($smtpConfig['password'])) {
                try {
                    $password = decrypt($smtpConfig['password']);
                } catch (\Exception $e) {
                    $password = $smtpConfig['password'];
                }
            }

            $useTls = ($smtpConfig['encryption'] ?? '') === 'ssl';
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

            $html = $this->getPasswordResetEmailHtml($resetUrl, $member->first_name ?? '');
            $text = "รีเซ็ตรหัสผ่าน NextTrip Holiday\n\n"
                . "กรุณาคลิกลิงก์ด้านล่างเพื่อตั้งรหัสผ่านใหม่:\n{$resetUrl}\n\n"
                . "ลิงก์นี้จะหมดอายุใน 60 นาที\n"
                . "หากคุณไม่ได้ขอรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยอีเมลนี้";

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address(
                    $smtpConfig['from_address'],
                    $smtpConfig['from_name'] ?? 'NextTrip Holiday'
                ))
                ->to($member->email)
                ->subject('รีเซ็ตรหัสผ่าน - NextTrip Holiday')
                ->html($html)
                ->text($text);

            if (!empty($smtpConfig['reply_to'])) {
                $email->replyTo($smtpConfig['reply_to']);
            }

            $mailer->send($email);

            Log::info('Password reset email sent', ['email' => $member->email]);
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'email' => $member->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ==================== Avatar Upload ====================

    /**
     * Upload member avatar to R2 storage
     */
    public function uploadAvatar(Request $request)
    {
        $member = $request->user();

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ], [
            'avatar.required' => 'กรุณาเลือกรูปภาพ',
            'avatar.image' => 'ไฟล์ต้องเป็นรูปภาพ',
            'avatar.mimes' => 'รองรับเฉพาะไฟล์ JPG, PNG, WebP',
            'avatar.max' => 'ขนาดไฟล์ต้องไม่เกิน 5MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $disk = Storage::disk('r2');
            $r2Url = rtrim(env('R2_URL'), '/');

            // Delete old avatar if it was uploaded to R2 (skip OAuth-provided URLs)
            if ($member->avatar && str_starts_with($member->avatar, $r2Url)) {
                $oldPath = str_replace($r2Url . '/', '', $member->avatar);
                $disk->delete($oldPath);
            }

            $file = $request->file('avatar');
            $path = 'member-avatars/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $disk->put($path, file_get_contents($file->getRealPath()), 'public');

            $member->avatar = $r2Url . '/' . $path;
            $member->save();

            return response()->json([
                'success' => true,
                'message' => 'อัปโหลดรูปสำเร็จ',
                'avatar' => $member->avatar,
            ]);
        } catch (\Exception $e) {
            Log::error('Avatar upload failed', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่',
            ], 500);
        }
    }

    /**
     * Remove member avatar
     */
    public function deleteAvatar(Request $request)
    {
        $member = $request->user();

        if ($member->avatar) {
            $r2Url = rtrim(env('R2_URL'), '/');
            if (str_starts_with($member->avatar, $r2Url)) {
                try {
                    $oldPath = str_replace($r2Url . '/', '', $member->avatar);
                    Storage::disk('r2')->delete($oldPath);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete avatar from R2', [
                        'member_id' => $member->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $member->avatar = null;
            $member->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'ลบรูปโปรไฟล์เรียบร้อย',
        ]);
    }

    // ==================== Account Deletion (PDPA) ====================

    /**
     * Delete member account (soft delete + revoke all tokens)
     * Requires password confirmation OR confirm token if user has no password (social-only login)
     */
    public function deleteAccount(Request $request)
    {
        $member = $request->user();

        $hasPassword = !empty($member->password);

        $validator = Validator::make($request->all(), [
            'password' => $hasPassword ? 'required|string' : 'nullable',
            'confirmation' => 'required|string|in:DELETE',
        ], [
            'password.required' => 'กรุณากรอกรหัสผ่านเพื่อยืนยัน',
            'confirmation.required' => 'กรุณายืนยันการลบบัญชี',
            'confirmation.in' => 'กรุณาพิมพ์ DELETE เพื่อยืนยัน',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($hasPassword && !Hash::check($request->password, $member->password)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_password',
                'message' => 'รหัสผ่านไม่ถูกต้อง',
            ], 400);
        }

        try {
            // Revoke all tokens
            $member->tokens()->delete();

            // Anonymize unique fields so user can re-register with same email/phone
            $suffix = '_deleted_' . $member->id . '_' . time();
            $member->email = $member->email ? $member->email . $suffix : null;
            $member->phone = $member->phone ? $member->phone . $suffix : null;
            $member->status = 'inactive';
            $member->save();

            // Soft delete
            $member->delete();

            Log::info('Member account deleted', [
                'member_id' => $member->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'ลบบัญชีเรียบร้อย ขอบคุณที่ใช้บริการ',
            ]);
        } catch (\Exception $e) {
            Log::error('Account deletion failed', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'ลบบัญชีไม่สำเร็จ กรุณาลองใหม่หรือติดต่อทีมงาน',
            ], 500);
        }
    }

    // ==================== Linked Social Accounts ====================

    /**
     * Get linked social accounts status
     */
    public function getLinkedAccounts(Request $request)
    {
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'has_password' => !empty($member->password),
                'google' => [
                    'linked' => !empty($member->google_id),
                    'linked_at' => $member->google_linked_at?->format('Y-m-d H:i:s'),
                ],
                'facebook' => [
                    'linked' => !empty($member->facebook_id),
                    'linked_at' => $member->facebook_linked_at?->format('Y-m-d H:i:s'),
                ],
                'line' => [
                    'linked' => !empty($member->line_id) && !empty($member->line_linked_at),
                    'linked_at' => $member->line_linked_at?->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }

    /**
     * Unlink a social provider from current account
     */
    public function unlinkSocial(Request $request, string $provider)
    {
        if (!in_array($provider, ['google', 'facebook', 'line'])) {
            return response()->json(['success' => false, 'message' => 'Provider ไม่ถูกต้อง'], 400);
        }

        $member = $request->user();

        // Safety: don't allow unlink if it's the only login method (no password and no other social)
        $hasPassword = !empty($member->password);
        $linkedCount = (!empty($member->google_id) ? 1 : 0)
            + (!empty($member->facebook_id) ? 1 : 0)
            + (!empty($member->line_id) && !empty($member->line_linked_at) ? 1 : 0);

        if (!$hasPassword && $linkedCount <= 1) {
            return response()->json([
                'success' => false,
                'error' => 'last_login_method',
                'message' => 'ไม่สามารถยกเลิกการเชื่อมต่อได้ เนื่องจากเป็นช่องทางเข้าสู่ระบบเดียวที่คุณมี กรุณาตั้งรหัสผ่านก่อน',
            ], 400);
        }

        $providerIdField = $provider === 'line' ? 'line_id' : "{$provider}_id";
        $linkedAtField = "{$provider}_linked_at";

        $member->{$providerIdField} = null;
        $member->{$linkedAtField} = null;
        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'ยกเลิกการเชื่อมต่อ ' . ucfirst($provider) . ' เรียบร้อย',
        ]);
    }

    private function getPasswordResetEmailHtml(string $resetUrl, string $firstName = ''): string
    {
        $greet = $firstName !== '' ? "สวัสดีคุณ {$firstName}" : 'สวัสดีครับ/ค่ะ';
        return <<<HTML
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
<div style="max-width:600px;margin:0 auto;padding:20px;">
  <div style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
    <div style="background:linear-gradient(135deg,#f59e0b,#ea580c);padding:32px;text-align:center;">
      <h1 style="color:#ffffff;margin:0;font-size:24px;">NextTrip Holiday</h1>
      <p style="color:#fed7aa;margin:8px 0 0;font-size:14px;">รีเซ็ตรหัสผ่าน</p>
    </div>
    <div style="padding:32px;">
      <h2 style="color:#1f2937;font-size:20px;margin:0 0 16px;">{$greet} 👋</h2>
      <p style="color:#4b5563;font-size:15px;line-height:1.6;margin:0 0 24px;">
        เราได้รับคำขอรีเซ็ตรหัสผ่านสำหรับบัญชีของคุณ กรุณากดปุ่มด้านล่างเพื่อตั้งรหัสผ่านใหม่
      </p>
      <div style="text-align:center;margin:32px 0;">
        <a href="{$resetUrl}" style="display:inline-block;background:#ea580c;color:#ffffff;text-decoration:none;padding:14px 40px;border-radius:8px;font-weight:600;font-size:16px;">
          ตั้งรหัสผ่านใหม่
        </a>
      </div>
      <p style="color:#6b7280;font-size:13px;line-height:1.5;margin:0 0 12px;">
        หรือคัดลอกลิงก์นี้ไปวางในเบราว์เซอร์:
      </p>
      <p style="word-break:break-all;color:#ea580c;font-size:12px;margin:0 0 24px;">
        {$resetUrl}
      </p>
      <p style="color:#6b7280;font-size:13px;line-height:1.5;margin:0;">
        ลิงก์นี้จะหมดอายุใน 60 นาที หากคุณไม่ได้ขอรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยอีเมลนี้ — รหัสผ่านของคุณจะไม่เปลี่ยนแปลง
      </p>
    </div>
    <div style="background:#f9fafb;padding:20px 32px;border-top:1px solid #e5e7eb;">
      <p style="color:#9ca3af;font-size:12px;margin:0;text-align:center;">
        © NextTrip Holiday Co., Ltd. | ใบอนุญาตนำเที่ยว TAT: 11/07440
      </p>
    </div>
  </div>
</div>
</body>
</html>
HTML;
    }
}
