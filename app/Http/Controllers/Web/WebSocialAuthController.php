<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WebMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebSocialAuthController extends Controller
{
    private array $config;

    public function __construct()
    {
        $this->config = Setting::get('social_auth_config', [
            'google_enabled' => false,
            'google_client_id' => '',
            'google_client_secret' => '',
            'facebook_enabled' => false,
            'facebook_app_id' => '',
            'facebook_app_secret' => '',
            'frontend_url' => '',
        ]);
    }

    /**
     * Get social auth status (which providers are enabled)
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'google' => !empty($this->config['google_enabled']) && !empty($this->config['google_client_id']),
                'facebook' => !empty($this->config['facebook_enabled']) && !empty($this->config['facebook_app_id']),
            ],
        ]);
    }

    /**
     * Get OAuth redirect URL for a provider
     */
    public function redirect(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json(['success' => false, 'message' => 'Provider ไม่ถูกต้อง'], 400);
        }

        if (!$this->isProviderEnabled($provider)) {
            return response()->json(['success' => false, 'message' => 'การเข้าสู่ระบบด้วย ' . ucfirst($provider) . ' ถูกปิดอยู่'], 400);
        }

        $redirectUri = $request->input('redirect_uri');
        if (!$redirectUri) {
            return response()->json(['success' => false, 'message' => 'redirect_uri is required'], 400);
        }

        $state = Str::random(40);

        if ($provider === 'google') {
            $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $this->getClientId('google'),
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'access_type' => 'offline',
                'prompt' => 'select_account',
            ]);
        } else {
            $params = http_build_query([
                'client_id' => $this->getClientId('facebook'),
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'state' => $state,
            ]);
            $url = 'https://www.facebook.com/v19.0/dialog/oauth?' . $params . '&scope=public_profile,email';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'state' => $state,
            ],
        ]);
    }

    /**
     * Exchange authorization code for user info and login/register
     */
    public function callback(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json(['success' => false, 'message' => 'Provider ไม่ถูกต้อง'], 400);
        }

        if (!$this->isProviderEnabled($provider)) {
            return response()->json(['success' => false, 'message' => 'การเข้าสู่ระบบด้วย ' . ucfirst($provider) . ' ถูกปิดอยู่'], 400);
        }

        $request->validate([
            'code' => 'required|string',
            'redirect_uri' => 'required|string',
        ]);

        try {
            // Exchange code for tokens and get user info
            $socialUser = $provider === 'google'
                ? $this->getGoogleUser($request->code, $request->redirect_uri)
                : $this->getFacebookUser($request->code, $request->redirect_uri);

            if (!$socialUser || empty($socialUser['id'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'social_auth_failed',
                    'message' => 'ไม่สามารถดึงข้อมูลจาก ' . ucfirst($provider) . ' ได้',
                ], 400);
            }

            // Find or create member
            $providerIdField = "{$provider}_id";
            $member = WebMember::where($providerIdField, $socialUser['id'])->first();

            if ($member) {
                // Existing user — login
                if (!$member->isActive()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'account_inactive',
                        'message' => 'บัญชีถูกระงับการใช้งาน',
                    ], 403);
                }

                $member->updateLastLogin($request->ip());

                // Update avatar if not set
                if (!$member->avatar && !empty($socialUser['avatar'])) {
                    $member->avatar = $socialUser['avatar'];
                    $member->save();
                }
            } else {
                // Check if email already registered
                if (!empty($socialUser['email'])) {
                    $existingMember = WebMember::where('email', $socialUser['email'])->first();

                    if ($existingMember) {
                        // Link social account to existing member
                        $existingMember->{$providerIdField} = $socialUser['id'];
                        $existingMember->{"{$provider}_linked_at"} = now();
                        if (!$existingMember->avatar && !empty($socialUser['avatar'])) {
                            $existingMember->avatar = $socialUser['avatar'];
                        }
                        $existingMember->save();
                        $existingMember->updateLastLogin($request->ip());
                        $member = $existingMember;
                    }
                }

                if (!$member) {
                    // Create new member (auto-register)
                    $member = WebMember::create([
                        'first_name' => $socialUser['first_name'] ?? '',
                        'last_name' => $socialUser['last_name'] ?? '',
                        'email' => $socialUser['email'] ?? "{$provider}_{$socialUser['id']}@social.local",
                        'phone' => null,
                        'password' => Hash::make(Str::random(32)),
                        $providerIdField => $socialUser['id'],
                        "{$provider}_linked_at" => now(),
                        'email_verified' => !empty($socialUser['email_verified']),
                        'email_verified_at' => !empty($socialUser['email_verified']) ? now() : null,
                        'avatar' => $socialUser['avatar'] ?? null,
                        'consent_terms' => true,
                        'consent_privacy' => true,
                        'consent_at' => now(),
                        'status' => 'active',
                    ]);

                    $member->updateLastLogin($request->ip());
                }
            }

            $token = $member->createToken('web-member')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'เข้าสู่ระบบสำเร็จ',
                'is_new' => $member->wasRecentlyCreated,
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

        } catch (\Exception $e) {
            Log::error("Social auth {$provider} callback failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'social_auth_error',
                'message' => 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ กรุณาลองใหม่',
            ], 500);
        }
    }

    // ─── Private helpers ───

    private function isProviderEnabled(string $provider): bool
    {
        $enabledKey = "{$provider}_enabled";
        $idKey = $provider === 'facebook' ? "{$provider}_app_id" : "{$provider}_client_id";

        return !empty($this->config[$enabledKey]) && !empty($this->config[$idKey]);
    }

    private function getClientId(string $provider): string
    {
        if ($provider === 'facebook') {
            $encrypted = $this->config['facebook_app_id'] ?? '';
        } else {
            $encrypted = $this->config["{$provider}_client_id"] ?? '';
        }

        try {
            return decrypt($encrypted);
        } catch (\Exception $e) {
            return $encrypted;
        }
    }

    private function getClientSecret(string $provider): string
    {
        $key = $provider === 'facebook' ? 'facebook_app_secret' : "{$provider}_client_secret";
        $encrypted = $this->config[$key] ?? '';

        try {
            return decrypt($encrypted);
        } catch (\Exception $e) {
            return $encrypted;
        }
    }

    /**
     * Exchange Google auth code for user info
     */
    private function getGoogleUser(string $code, string $redirectUri): ?array
    {
        // Exchange code for tokens
        $tokenResponse = \Illuminate\Support\Facades\Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->getClientId('google'),
            'client_secret' => $this->getClientSecret('google'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$tokenResponse->successful()) {
            Log::error('Google token exchange failed', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);
            return null;
        }

        $tokens = $tokenResponse->json();
        $accessToken = $tokens['access_token'] ?? null;

        if (!$accessToken) {
            return null;
        }

        // Get user info
        $userResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (!$userResponse->successful()) {
            return null;
        }

        $user = $userResponse->json();

        return [
            'id' => $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'email_verified' => $user['verified_email'] ?? false,
            'first_name' => $user['given_name'] ?? '',
            'last_name' => $user['family_name'] ?? '',
            'avatar' => $user['picture'] ?? null,
        ];
    }

    /**
     * Exchange Facebook auth code for user info
     */
    private function getFacebookUser(string $code, string $redirectUri): ?array
    {
        // Exchange code for token
        $tokenResponse = \Illuminate\Support\Facades\Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
            'client_id' => $this->getClientId('facebook'),
            'client_secret' => $this->getClientSecret('facebook'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (!$tokenResponse->successful()) {
            Log::error('Facebook token exchange failed', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);
            return null;
        }

        $accessToken = $tokenResponse->json('access_token');

        if (!$accessToken) {
            return null;
        }

        // Get user info
        $userResponse = \Illuminate\Support\Facades\Http::get('https://graph.facebook.com/v19.0/me', [
            'access_token' => $accessToken,
            'fields' => 'id,first_name,last_name,email,picture.type(large)',
        ]);

        if (!$userResponse->successful()) {
            return null;
        }

        $user = $userResponse->json();

        return [
            'id' => $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'email_verified' => !empty($user['email']),
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'avatar' => $user['picture']['data']['url'] ?? null,
        ];
    }
}
