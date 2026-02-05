<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WebMember;
use App\Models\OtpRequest;
use App\Models\WebPasswordResetToken;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class WebAuthController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
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

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Step 2: Verify OTP and complete registration
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp_request_id' => 'required|integer|exists:otp_requests,id',
            'otp' => 'required|string|size:6',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:web_members,email',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'consent_terms' => 'required|accepted',
            'consent_privacy' => 'required|accepted',
            'consent_marketing' => 'boolean',
        ], [
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

        // Verify OTP
        $otpResult = $this->otpService->verifyOtp($request->otp_request_id, $request->otp);

        if (!$otpResult['success']) {
            return response()->json($otpResult, 400);
        }

        // Create member
        $member = WebMember::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $otpResult['phone_msisdn'],
            'password' => Hash::make($request->password),
            'phone_verified' => true,
            'phone_verified_at' => now(),
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

        // Send email (you need to configure mail)
        // TODO: Send email with reset link
        // Mail::to($member->email)->send(new PasswordResetMail($resetToken->token));

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
}
