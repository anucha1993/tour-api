<?php

namespace App\Jobs;

use App\Models\Newsletter;
use App\Models\NewsletterLog;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public int $newsletterId
    ) {}

    public function handle(): void
    {
        $newsletter = Newsletter::find($this->newsletterId);

        if (!$newsletter || $newsletter->status === 'cancelled') {
            Log::info('Newsletter cancelled or not found', ['id' => $this->newsletterId]);
            return;
        }

        if ($newsletter->isExpired()) {
            $newsletter->update(['status' => 'cancelled']);
            Log::info('Newsletter expired', ['id' => $this->newsletterId]);
            return;
        }

        $smtpConfig = Setting::get('subscriber_smtp_config');
        if (!$smtpConfig || empty($smtpConfig['host'])) {
            Log::error('Subscriber SMTP not configured');
            $newsletter->update(['status' => 'draft']);
            return;
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

            $useTls = ($smtpConfig['encryption'] ?? 'tls') === 'ssl';
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $smtpConfig['host'],
                (int) ($smtpConfig['port'] ?? 587),
                $useTls
            );
            if (!empty($smtpConfig['username'])) {
                $transport->setUsername($smtpConfig['username']);
            }
            if (!empty($password)) {
                $transport->setPassword($password);
            }

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);
            $fromAddress = new \Symfony\Component\Mime\Address(
                $smtpConfig['from_address'],
                $smtpConfig['from_name'] ?? 'NextTrip Holiday'
            );

            $frontendUrl = rtrim(env('FRONTEND_URL', 'https://nexttrip.asia'), '/');

            // Get pending logs in batches
            $batchSize = $newsletter->batch_size;
            $batchDelay = $newsletter->batch_delay_seconds;
            $sentCount = 0;
            $failedCount = 0;

            $pendingLogs = NewsletterLog::where('newsletter_id', $newsletter->id)
                ->where('status', 'pending')
                ->with('subscriber')
                ->get();

            foreach ($pendingLogs->chunk($batchSize) as $batchIndex => $batch) {
                // Check if cancelled
                $newsletter->refresh();
                if ($newsletter->status === 'cancelled') {
                    Log::info('Newsletter cancelled during send', ['id' => $newsletter->id]);
                    break;
                }

                foreach ($batch as $log) {
                    if (!$log->subscriber || $log->subscriber->status !== 'active') {
                        $log->update(['status' => 'failed', 'error_message' => 'Subscriber not active']);
                        $failedCount++;
                        continue;
                    }

                    try {
                        $unsubscribeUrl = $frontendUrl . '/subscribe/unsubscribe?token=' . $log->subscriber->unsubscribe_token;

                        // API URL for List-Unsubscribe header (Gmail sends POST here)
                        $apiUrl = rtrim(env('APP_URL', 'https://api.nexttrip.asia'), '/') . '/api';
                        $apiUnsubscribeUrl = $apiUrl . '/subscribers/unsubscribe/' . $log->subscriber->unsubscribe_token;

                        // Add unsubscribe link to HTML
                        $htmlContent = $newsletter->content_html;
                        $htmlContent .= '<div style="text-align:center;padding:20px;border-top:1px solid #e5e7eb;margin-top:30px;">'
                            . '<p style="color:#9ca3af;font-size:12px;">© NextTrip Holiday Co., Ltd.</p>'
                            . '<a href="' . $unsubscribeUrl . '" style="color:#9ca3af;font-size:11px;text-decoration:underline;">ยกเลิกรับข่าวสาร</a>'
                            . '</div>';

                        $textContent = ($newsletter->content_text ?? strip_tags($newsletter->content_html))
                            . "\n\n---\nยกเลิกรับข่าวสาร: {$unsubscribeUrl}";

                        $email = (new \Symfony\Component\Mime\Email())
                            ->from($fromAddress)
                            ->to($log->subscriber->email)
                            ->subject($newsletter->subject)
                            ->html($htmlContent)
                            ->text($textContent);

                        if (!empty($smtpConfig['reply_to'])) {
                            $email->replyTo($smtpConfig['reply_to']);
                        }

                        // Add List-Unsubscribe header (points to API for Gmail One-Click)
                        $email->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $apiUnsubscribeUrl . '>');
                        $email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

                        $mailer->send($email);

                        $log->update([
                            'status' => 'sent',
                            'sent_at' => now(),
                        ]);
                        $sentCount++;

                    } catch (\Exception $e) {
                        $log->update([
                            'status' => 'failed',
                            'error_message' => substr($e->getMessage(), 0, 500),
                        ]);
                        $failedCount++;

                        Log::error('Failed to send newsletter email', [
                            'newsletter_id' => $newsletter->id,
                            'subscriber_email' => $log->subscriber->email,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Update progress
                $newsletter->update([
                    'sent_count' => $sentCount,
                    'failed_count' => $failedCount,
                ]);

                // Delay between batches (warm-up)
                if ($batchDelay > 0 && $batchIndex < $pendingLogs->chunk($batchSize)->count() - 1) {
                    sleep($batchDelay);
                }
            }

            // Final update
            $newsletter->update([
                'status' => 'sent',
                'sent_at' => now(),
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
            ]);

            Log::info('Newsletter sent', [
                'id' => $newsletter->id,
                'sent' => $sentCount,
                'failed' => $failedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Newsletter job failed', [
                'id' => $newsletter->id,
                'error' => $e->getMessage(),
            ]);

            $newsletter->update(['status' => 'draft']);
            throw $e;
        }
    }
}
