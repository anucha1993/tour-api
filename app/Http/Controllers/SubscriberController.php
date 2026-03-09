<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Models\Newsletter;
use App\Models\NewsletterLog;
use App\Models\Setting;
use App\Jobs\SendNewsletterJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SubscriberController extends Controller
{
    // ==================== Public Endpoints ====================

    /**
     * Subscribe (public) - creates pending subscriber and sends confirmation email
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'source_page' => 'nullable|string|max:255',
            'interest_country' => 'nullable|string|max:255',
        ]);

        $email = strtolower(trim($validated['email']));

        // Check existing subscriber
        $existing = Subscriber::where('email', $email)->first();

        if ($existing) {
            if ($existing->status === 'active') {
                return response()->json([
                    'success' => true,
                    'message' => 'อีเมลนี้สมัครรับข่าวสารแล้ว',
                    'already_subscribed' => true,
                ]);
            }

            if ($existing->status === 'unsubscribed') {
                // Re-subscribe: reset to pending
                $token = $existing->generateConfirmationToken();
                $existing->update([
                    'status' => 'pending',
                    'source_page' => $validated['source_page'] ?? $existing->source_page,
                    'interest_country' => $validated['interest_country'] ?? $existing->interest_country,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'unsubscribed_at' => null,
                ]);

                $this->sendConfirmationEmail($existing, $token);

                return response()->json([
                    'success' => true,
                    'message' => 'กรุณาตรวจสอบอีเมลเพื่อยืนยันการสมัครรับข่าวสาร',
                ]);
            }

            if ($existing->status === 'pending') {
                // Resend confirmation if token expired
                if (!$existing->isTokenValid()) {
                    $token = $existing->generateConfirmationToken();
                    $this->sendConfirmationEmail($existing, $token);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'กรุณาตรวจสอบอีเมลเพื่อยืนยันการสมัครรับข่าวสาร',
                ]);
            }
        }

        // Create new subscriber
        $subscriber = Subscriber::create([
            'email' => $email,
            'status' => 'pending',
            'source_page' => $validated['source_page'] ?? null,
            'interest_country' => $validated['interest_country'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $token = $subscriber->generateConfirmationToken();
        $this->sendConfirmationEmail($subscriber, $token);

        return response()->json([
            'success' => true,
            'message' => 'กรุณาตรวจสอบอีเมลเพื่อยืนยันการสมัครรับข่าวสาร',
        ]);
    }

    /**
     * Confirm subscription (double opt-in) - public
     */
    public function confirm(string $token): JsonResponse
    {
        $subscriber = Subscriber::where('confirmation_token', $token)->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'ลิงก์ยืนยันไม่ถูกต้อง',
            ], 404);
        }

        if (!$subscriber->isTokenValid()) {
            return response()->json([
                'success' => false,
                'message' => 'ลิงก์ยืนยันหมดอายุแล้ว กรุณาสมัครใหม่อีกครั้ง',
                'expired' => true,
            ], 400);
        }

        $confirmed = $subscriber->confirm();

        if ($confirmed) {
            // Send welcome email
            $this->sendWelcomeEmail($subscriber);

            return response()->json([
                'success' => true,
                'message' => 'ยืนยันการสมัครรับข่าวสารสำเร็จ! ขอบคุณที่ติดตามเรา',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'ไม่สามารถยืนยันการสมัครได้',
        ], 400);
    }

    /**
     * Unsubscribe - public one-click
     */
    public function unsubscribe(string $token): JsonResponse
    {
        $subscriber = Subscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'ลิงก์ยกเลิกไม่ถูกต้อง',
            ], 404);
        }

        if ($subscriber->status === 'unsubscribed') {
            return response()->json([
                'success' => true,
                'message' => 'คุณได้ยกเลิกการรับข่าวสารแล้ว',
            ]);
        }

        $subscriber->unsubscribe();

        return response()->json([
            'success' => true,
            'message' => 'ยกเลิกการรับข่าวสารสำเร็จ',
        ]);
    }

    // ==================== Admin Endpoints ====================

    /**
     * List all subscribers with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subscriber::query();

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by source
        if ($request->has('source_page') && $request->source_page !== '') {
            $query->where('source_page', $request->source_page);
        }

        // Filter by country interest
        if ($request->has('interest_country') && $request->interest_country !== '') {
            $query->where('interest_country', 'like', "%{$request->interest_country}%");
        }

        // Search by email
        if ($request->has('search') && $request->search !== '') {
            $query->where('email', 'like', "%{$request->search}%");
        }

        // Stats
        $stats = [
            'total' => Subscriber::count(),
            'active' => Subscriber::active()->count(),
            'pending' => Subscriber::pending()->count(),
            'unsubscribed' => Subscriber::unsubscribed()->count(),
        ];

        $subscribers = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $subscribers->items(),
            'stats' => $stats,
            'pagination' => [
                'current_page' => $subscribers->currentPage(),
                'last_page' => $subscribers->lastPage(),
                'per_page' => $subscribers->perPage(),
                'total' => $subscribers->total(),
            ],
        ]);
    }

    /**
     * Get subscriber detail
     */
    public function show(Subscriber $subscriber): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $subscriber,
        ]);
    }

    /**
     * Delete subscriber (admin only)
     */
    public function destroy(Subscriber $subscriber): JsonResponse
    {
        $subscriber->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบ subscriber สำเร็จ',
        ]);
    }

    /**
     * Export subscribers as CSV
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = Subscriber::query();

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $subscribers = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="subscribers_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($subscribers) {
            $handle = fopen('php://output', 'w');
            // BOM for UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['Email', 'Status', 'Source', 'Country Interest', 'Subscribed At', 'Created At']);
            foreach ($subscribers as $sub) {
                fputcsv($handle, [
                    $sub->email,
                    $sub->status,
                    $sub->source_page,
                    $sub->interest_country,
                    $sub->subscribed_at?->format('Y-m-d H:i:s'),
                    $sub->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Get subscriber stats for dashboard
     */
    public function stats(): JsonResponse
    {
        $thirtyDaysAgo = now()->subDays(30);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => Subscriber::count(),
                'active' => Subscriber::active()->count(),
                'pending' => Subscriber::pending()->count(),
                'unsubscribed' => Subscriber::unsubscribed()->count(),
                'new_this_month' => Subscriber::where('created_at', '>=', $thirtyDaysAgo)->count(),
                'sources' => Subscriber::selectRaw('source_page, count(*) as count')
                    ->whereNotNull('source_page')
                    ->groupBy('source_page')
                    ->pluck('count', 'source_page'),
            ],
        ]);
    }

    // ==================== Newsletter Admin ====================

    /**
     * List newsletters
     */
    public function newsletterIndex(Request $request): JsonResponse
    {
        $query = Newsletter::query();

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $newsletters = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $newsletters->items(),
            'pagination' => [
                'current_page' => $newsletters->currentPage(),
                'last_page' => $newsletters->lastPage(),
                'per_page' => $newsletters->perPage(),
                'total' => $newsletters->total(),
            ],
        ]);
    }

    /**
     * Create newsletter
     */
    public function newsletterStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content_html' => 'required|string',
            'content_text' => 'nullable|string',
            'template' => 'nullable|string|in:welcome,promotion,review',
            'scheduled_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:now',
            'recipient_filter' => 'nullable|array',
            'recipient_filter.type' => 'nullable|string|in:all,active,country',
            'recipient_filter.country' => 'nullable|string',
            'recipient_filter.subscriber_ids' => 'nullable|array',
            'batch_size' => 'nullable|integer|min:1|max:500',
            'batch_delay_seconds' => 'nullable|integer|min:0|max:3600',
        ]);

        $newsletter = Newsletter::create([
            'subject' => $validated['subject'],
            'content_html' => $validated['content_html'],
            'content_text' => $validated['content_text'] ?? strip_tags($validated['content_html']),
            'template' => $validated['template'] ?? 'promotion',
            'status' => 'draft',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'recipient_filter' => $validated['recipient_filter'] ?? ['type' => 'all'],
            'batch_size' => $validated['batch_size'] ?? 50,
            'batch_delay_seconds' => $validated['batch_delay_seconds'] ?? 60,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'สร้าง newsletter สำเร็จ',
            'data' => $newsletter,
        ], 201);
    }

    /**
     * Show newsletter
     */
    public function newsletterShow(Newsletter $newsletter): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $newsletter->load('logs'),
        ]);
    }

    /**
     * Update newsletter (only draft)
     */
    public function newsletterUpdate(Request $request, Newsletter $newsletter): JsonResponse
    {
        if ($newsletter->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'สามารถแก้ไขได้เฉพาะ newsletter ที่เป็น draft เท่านั้น',
            ], 400);
        }

        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
            'content_html' => 'nullable|string',
            'content_text' => 'nullable|string',
            'template' => 'nullable|string|in:welcome,promotion,review',
            'scheduled_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:now',
            'recipient_filter' => 'nullable|array',
            'batch_size' => 'nullable|integer|min:1|max:500',
            'batch_delay_seconds' => 'nullable|integer|min:0|max:3600',
        ]);

        $newsletter->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'อัปเดต newsletter สำเร็จ',
            'data' => $newsletter->fresh(),
        ]);
    }

    /**
     * Delete newsletter (only draft)
     */
    public function newsletterDestroy(Newsletter $newsletter): JsonResponse
    {
        if ($newsletter->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'สามารถลบได้เฉพาะ newsletter ที่เป็น draft เท่านั้น',
            ], 400);
        }

        $newsletter->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบ newsletter สำเร็จ',
        ]);
    }

    /**
     * Send newsletter (dispatch job)
     */
    public function newsletterSend(Newsletter $newsletter): JsonResponse
    {
        if (!$newsletter->canSend()) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถส่ง newsletter นี้ได้ (สถานะ: ' . $newsletter->status . ')',
            ], 400);
        }

        // Get subscriber SMTP config
        $smtpConfig = Setting::get('subscriber_smtp_config');
        if (!$smtpConfig || empty($smtpConfig['host']) || empty($smtpConfig['enabled'])) {
            return response()->json([
                'success' => false,
                'message' => 'กรุณาตั้งค่า SMTP สำหรับ subscriber ก่อนส่ง newsletter',
            ], 400);
        }

        // Determine recipients
        $recipientQuery = Subscriber::active();
        $filter = $newsletter->recipient_filter ?? ['type' => 'all'];

        if (isset($filter['type'])) {
            if ($filter['type'] === 'country' && !empty($filter['country'])) {
                $recipientQuery->where('interest_country', 'like', "%{$filter['country']}%");
            }
        }

        if (!empty($filter['subscriber_ids'])) {
            $recipientQuery->whereIn('id', $filter['subscriber_ids']);
        }

        $totalRecipients = $recipientQuery->count();

        if ($totalRecipients === 0) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่มี subscriber ที่ตรงตามเงื่อนไข',
            ], 400);
        }

        // Update newsletter status
        $newsletter->update([
            'status' => 'sending',
            'total_recipients' => $totalRecipients,
        ]);

        // Create log entries and dispatch job
        $recipientIds = $recipientQuery->pluck('id');

        foreach ($recipientIds as $subscriberId) {
            NewsletterLog::firstOrCreate([
                'newsletter_id' => $newsletter->id,
                'subscriber_id' => $subscriberId,
            ], [
                'status' => 'pending',
            ]);
        }

        // Dispatch batch sending job
        SendNewsletterJob::dispatch($newsletter->id);

        return response()->json([
            'success' => true,
            'message' => "กำลังส่ง newsletter ไปยัง {$totalRecipients} คน",
            'data' => $newsletter->fresh(),
        ]);
    }

    /**
     * Cancel a sending newsletter
     */
    public function newsletterCancel(Newsletter $newsletter): JsonResponse
    {
        if (!in_array($newsletter->status, ['sending', 'scheduled'])) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถยกเลิกได้',
            ], 400);
        }

        $newsletter->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'ยกเลิก newsletter สำเร็จ',
        ]);
    }

    /**
     * Preview newsletter count
     */
    public function newsletterPreviewCount(Request $request): JsonResponse
    {
        $filter = $request->input('recipient_filter', ['type' => 'all']);
        $query = Subscriber::active();

        if (isset($filter['type']) && $filter['type'] === 'country' && !empty($filter['country'])) {
            $query->where('interest_country', 'like', "%{$filter['country']}%");
        }

        if (!empty($filter['subscriber_ids'])) {
            $query->whereIn('id', $filter['subscriber_ids']);
        }

        return response()->json([
            'success' => true,
            'data' => ['count' => $query->count()],
        ]);
    }

    // ==================== Subscriber SMTP Settings ====================

    /**
     * Get subscriber SMTP configuration (separate from main SMTP)
     */
    public function getSubscriberSmtp(): JsonResponse
    {
        $config = Setting::get('subscriber_smtp_config', [
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_address' => '',
            'from_name' => 'NextTrip Holiday',
            'reply_to' => '',
            'enabled' => false,
        ]);

        // Mask password
        if (!empty($config['password'])) {
            $config['password_masked'] = str_repeat('•', 8);
            $config['has_password'] = true;
        } else {
            $config['password_masked'] = '';
            $config['has_password'] = false;
        }
        unset($config['password']);

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Update subscriber SMTP configuration
     */
    public function updateSubscriberSmtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|in:tls,ssl,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'reply_to' => 'nullable|email|max:255',
            'enabled' => 'boolean',
        ]);

        $currentConfig = Setting::get('subscriber_smtp_config', []);

        if (empty($validated['password']) && !empty($currentConfig['password'])) {
            $validated['password'] = $currentConfig['password'];
        } elseif (!empty($validated['password'])) {
            $validated['password'] = encrypt($validated['password']);
        }

        Setting::set('subscriber_smtp_config', $validated, 'subscriber_mail', 'json');

        return response()->json([
            'success' => true,
            'message' => 'บันทึกการตั้งค่า SMTP สำหรับ Subscriber สำเร็จ',
        ]);
    }

    /**
     * Test subscriber SMTP
     */
    public function testSubscriberSmtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_email' => 'required|email',
        ]);

        $smtpConfig = Setting::get('subscriber_smtp_config');

        if (!$smtpConfig || empty($smtpConfig['host'])) {
            return response()->json([
                'success' => false,
                'message' => 'กรุณาบันทึกการตั้งค่า SMTP ก่อน',
            ], 400);
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
                ->subject('ทดสอบ SMTP Subscriber - NextTrip')
                ->html('
                    <div style="font-family: sans-serif; padding: 20px; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #2563eb;">✅ ทดสอบ SMTP สำหรับ Subscriber สำเร็จ!</h2>
                        <p>การตั้งค่า SMTP สำหรับระบบ Newsletter/Subscriber ใช้งานได้ปกติ</p>
                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
                        <p style="color: #6b7280; font-size: 14px;">
                            <strong>SMTP Server:</strong> ' . $smtpConfig['host'] . '<br>
                            <strong>Port:</strong> ' . $smtpConfig['port'] . '<br>
                            <strong>From:</strong> ' . $smtpConfig['from_name'] . ' &lt;' . $smtpConfig['from_address'] . '&gt;
                        </p>
                        <p style="color: #9ca3af; font-size: 12px;">ส่งเมื่อ: ' . now()->format('d/m/Y H:i:s') . '</p>
                    </div>
                ');

            $mailer->send($email);

            return response()->json([
                'success' => true,
                'message' => "ส่งอีเมลทดสอบไปที่ {$validated['to_email']} สำเร็จ",
            ]);

        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถเชื่อมต่อ SMTP: ' . $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ==================== Private Helpers ====================

    /**
     * Send confirmation email using subscriber SMTP
     */
    private function sendConfirmationEmail(Subscriber $subscriber, string $token): void
    {
        $smtpConfig = Setting::get('subscriber_smtp_config');

        // Fallback to main SMTP config if subscriber SMTP not configured
        if (!$smtpConfig || empty($smtpConfig['host']) || empty($smtpConfig['enabled'])) {
            $smtpConfig = Setting::get('smtp_config');
            if (!$smtpConfig || empty($smtpConfig['host'])) {
                Log::warning('No SMTP configured (subscriber or main), skipping confirmation email', [
                    'email' => $subscriber->email,
                ]);
                return;
            }
        }

        try {
            $frontendUrl = rtrim(env('FRONTEND_URL', 'https://nexttrip.asia'), '/');
            $confirmUrl = $frontendUrl . '/subscribe/confirm?token=' . $token;

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

            $html = $this->getConfirmationEmailHtml($confirmUrl);
            $text = "ยืนยันการสมัครรับข่าวสาร NextTrip Holiday\n\n"
                . "กรุณาคลิกลิงก์ด้านล่างเพื่อยืนยัน:\n{$confirmUrl}\n\n"
                . "ลิงก์นี้จะหมดอายุใน 24 ชั่วโมง\n"
                . "หากคุณไม่ได้สมัคร กรุณาเพิกเฉยอีเมลนี้";

            // API URL for List-Unsubscribe header (Gmail sends POST here directly)
            $apiUrl = rtrim(env('APP_URL', 'https://api.nexttrip.world'), '/') . '/api';
            $apiUnsubscribeUrl = $apiUrl . '/subscribers/unsubscribe/' . $subscriber->unsubscribe_token;

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address(
                    $smtpConfig['from_address'],
                    $smtpConfig['from_name'] ?? 'NextTrip Holiday'
                ))
                ->to($subscriber->email)
                ->subject('ยืนยันการสมัครรับข่าวสาร - NextTrip Holiday')
                ->html($html)
                ->text($text);

            // Add List-Unsubscribe headers (Gmail sends POST to this URL for One-Click)
            $email->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $apiUnsubscribeUrl . '>');
            $email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

            if (!empty($smtpConfig['reply_to'])) {
                $email->replyTo($smtpConfig['reply_to']);
            }

            $mailer->send($email);

            Log::info('Confirmation email sent', ['email' => $subscriber->email]);
        } catch (\Exception $e) {
            Log::error('Failed to send confirmation email', [
                'email' => $subscriber->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send welcome email after confirmation
     */
    private function sendWelcomeEmail(Subscriber $subscriber): void
    {
        $smtpConfig = Setting::get('subscriber_smtp_config');

        // Fallback to main SMTP config
        if (!$smtpConfig || empty($smtpConfig['host']) || empty($smtpConfig['enabled'])) {
            $smtpConfig = Setting::get('smtp_config');
            if (!$smtpConfig || empty($smtpConfig['host'])) {
                return;
            }
        }

        try {
            $frontendUrl = rtrim(env('FRONTEND_URL', 'https://nexttrip.asia'), '/');
            $unsubscribeUrl = $frontendUrl . '/subscribe/unsubscribe?token=' . $subscriber->unsubscribe_token;

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

            $html = $this->getWelcomeEmailHtml($frontendUrl, $unsubscribeUrl);

            // API URL for List-Unsubscribe header (Gmail sends POST here directly)
            $apiUrl = rtrim(env('APP_URL', 'https://api.nexttrip.world'), '/') . '/api';
            $apiUnsubscribeUrl = $apiUrl . '/subscribers/unsubscribe/' . $subscriber->unsubscribe_token;

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address(
                    $smtpConfig['from_address'],
                    $smtpConfig['from_name'] ?? 'NextTrip Holiday'
                ))
                ->to($subscriber->email)
                ->subject('ยินดีต้อนรับสู่ NextTrip Holiday! 🎉')
                ->html($html)
                ->text("ยินดีต้อนรับสู่ NextTrip Holiday!\n\nขอบคุณที่สมัครรับข่าวสาร คุณจะได้รับโปรโมชั่นและข่าวสารดีๆ จากเรา\n\nยกเลิกรับข่าวสาร: {$unsubscribeUrl}");

            // Add List-Unsubscribe headers (Gmail sends POST to this URL for One-Click)
            $email->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $apiUnsubscribeUrl . '>');
            $email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

            if (!empty($smtpConfig['reply_to'])) {
                $email->replyTo($smtpConfig['reply_to']);
            }

            $mailer->send($email);
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'email' => $subscriber->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ==================== Email Templates ====================

    private function getConfirmationEmailHtml(string $confirmUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
<div style="max-width:600px;margin:0 auto;padding:20px;">
  <div style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:32px;text-align:center;">
      <h1 style="color:#ffffff;margin:0;font-size:24px;">NextTrip Holiday</h1>
      <p style="color:#bfdbfe;margin:8px 0 0;font-size:14px;">ยืนยันการสมัครรับข่าวสาร</p>
    </div>

    <!-- Content -->
    <div style="padding:32px;">
      <h2 style="color:#1f2937;font-size:20px;margin:0 0 16px;">สวัสดีครับ/ค่ะ 👋</h2>
      <p style="color:#4b5563;font-size:15px;line-height:1.6;margin:0 0 24px;">
        ขอบคุณที่สนใจรับข่าวสารจาก NextTrip Holiday กรุณากดปุ่มด้านล่างเพื่อยืนยันการสมัคร
      </p>

      <div style="text-align:center;margin:32px 0;">
        <a href="{$confirmUrl}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:14px 40px;border-radius:8px;font-weight:600;font-size:16px;">
          ยืนยันการสมัคร
        </a>
      </div>

      <p style="color:#6b7280;font-size:13px;line-height:1.5;margin:0;">
        ลิงก์นี้จะหมดอายุใน 24 ชั่วโมง หากคุณไม่ได้สมัครรับข่าวสาร กรุณาเพิกเฉยอีเมลนี้
      </p>
    </div>

    <!-- Footer -->
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

    private function getWelcomeEmailHtml(string $siteUrl, string $unsubscribeUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
<div style="max-width:600px;margin:0 auto;padding:20px;">
  <div style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:32px;text-align:center;">
      <h1 style="color:#ffffff;margin:0;font-size:24px;">🎉 ยินดีต้อนรับ!</h1>
      <p style="color:#bfdbfe;margin:8px 0 0;font-size:14px;">NextTrip Holiday</p>
    </div>

    <!-- Content -->
    <div style="padding:32px;">
      <h2 style="color:#1f2937;font-size:20px;margin:0 0 16px;">ขอบคุณที่ติดตามเรา</h2>
      <p style="color:#4b5563;font-size:15px;line-height:1.6;margin:0 0 16px;">
        คุณจะได้รับข่าวสารเกี่ยวกับ:
      </p>
      <ul style="color:#4b5563;font-size:15px;line-height:1.8;padding-left:20px;margin:0 0 24px;">
        <li>โปรโมชั่นทัวร์สุดพิเศษ</li>
        <li>ทัวร์ใหม่ที่น่าสนใจ</li>
        <li>เคล็ดลับการเดินทาง</li>
        <li>ข่าวสารและกิจกรรมพิเศษ</li>
      </ul>

      <div style="text-align:center;margin:32px 0;">
        <a href="{$siteUrl}/tours" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:14px 40px;border-radius:8px;font-weight:600;font-size:16px;">
          ดูทัวร์ทั้งหมด
        </a>
      </div>
    </div>

    <!-- Footer -->
    <div style="background:#f9fafb;padding:20px 32px;border-top:1px solid #e5e7eb;text-align:center;">
      <p style="color:#9ca3af;font-size:12px;margin:0 0 8px;">
        © NextTrip Holiday Co., Ltd. | ใบอนุญาตนำเที่ยว TAT: 11/07440
      </p>
      <a href="{$unsubscribeUrl}" style="color:#9ca3af;font-size:11px;text-decoration:underline;">
        ยกเลิกรับข่าวสาร
      </a>
    </div>
  </div>
</div>
</body>
</html>
HTML;
    }
}
