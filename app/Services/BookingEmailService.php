<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BookingEmailService
{
    /**
     * Send booking confirmation email to customer (and optionally admin)
     */
    public static function sendBookingConfirmation(Booking $booking): void
    {
        try {
            $smtpConfig = Setting::get('smtp_config');

            if (!$smtpConfig || empty($smtpConfig['host']) || empty($smtpConfig['enabled'])) {
                Log::info('BookingEmail: SMTP not configured or disabled, skipping email.');
                return;
            }

            $templates = Setting::get('email_templates');
            $template  = $templates['booking_confirmation'] ?? null;

            if (!$template || empty($template['enabled'])) {
                Log::info('BookingEmail: booking_confirmation template disabled, skipping.');
                return;
            }

            // Load relations if needed
            $booking->loadMissing(['tour', 'period']);

            $data = self::buildVariables($booking);

            $subject = self::replaceVariables($template['subject'], $data);
            $body    = self::replaceVariables($template['body'], $data);

            $mailer = self::createMailer($smtpConfig);

            // Send to customer
            $email = (new Email())
                ->from(new Address($smtpConfig['from_address'], $smtpConfig['from_name']))
                ->to($booking->email)
                ->subject($subject)
                ->html($body);

            $mailer->send($email);

            Log::info('BookingEmail: Confirmation sent to ' . $booking->email, [
                'booking_code' => $booking->booking_code,
            ]);

            // Send to admin(s) if enabled
            if (!empty($template['send_to_admin']) && !empty($template['admin_emails'])) {
                $adminEmails = array_filter(array_map('trim', explode(',', $template['admin_emails'])));

                foreach ($adminEmails as $adminEmail) {
                    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                        $adminMail = (new Email())
                            ->from(new Address($smtpConfig['from_address'], $smtpConfig['from_name']))
                            ->to($adminEmail)
                            ->subject('[Admin] ' . $subject)
                            ->html($body);

                        $mailer->send($adminMail);

                        Log::info('BookingEmail: Admin notification sent to ' . $adminEmail);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('BookingEmail: Failed to send confirmation', [
                'booking_id' => $booking->id ?? null,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send booking status update email to customer
     */
    public static function sendStatusUpdate(Booking $booking): void
    {
        try {
            $smtpConfig = Setting::get('smtp_config');

            if (!$smtpConfig || empty($smtpConfig['host']) || empty($smtpConfig['enabled'])) {
                return;
            }

            $templates = Setting::get('email_templates');
            $template  = $templates['booking_status_update'] ?? null;

            if (!$template || empty($template['enabled'])) {
                return;
            }

            $booking->loadMissing(['tour', 'period']);

            $data = self::buildVariables($booking);

            $subject = self::replaceVariables($template['subject'], $data);
            $body    = self::replaceVariables($template['body'], $data);

            $mailer = self::createMailer($smtpConfig);

            $email = (new Email())
                ->from(new Address($smtpConfig['from_address'], $smtpConfig['from_name']))
                ->to($booking->email)
                ->subject($subject)
                ->html($body);

            $mailer->send($email);

            Log::info('BookingEmail: Status update sent to ' . $booking->email, [
                'booking_code' => $booking->booking_code,
                'status'       => $booking->status,
            ]);

            // Also notify admin(s) — same pattern as sendBookingConfirmation().
            // Previously status updates (including cancellations) went ONLY to
            // the customer, so Nexttrip had no way to know a booking had been
            // cancelled without opening the dashboard. Reading the template's
            // send_to_admin / admin_emails settings keeps this configurable.
            if (!empty($template['send_to_admin']) && !empty($template['admin_emails'])) {
                $adminEmails = array_filter(array_map('trim', explode(',', $template['admin_emails'])));

                foreach ($adminEmails as $adminEmail) {
                    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                        $adminMail = (new Email())
                            ->from(new Address($smtpConfig['from_address'], $smtpConfig['from_name']))
                            ->to($adminEmail)
                            ->subject('[Admin] ' . $subject)
                            ->html($body);

                        $mailer->send($adminMail);

                        Log::info('BookingEmail: Admin status update sent to ' . $adminEmail, [
                            'booking_code' => $booking->booking_code,
                            'status'       => $booking->status,
                        ]);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('BookingEmail: Failed to send status update', [
                'booking_id' => $booking->id ?? null,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build template variable map from a Booking
     */
    private static function buildVariables(Booking $booking): array
    {
        $travelDate = 'ไม่ระบุ';
        if ($booking->period) {
            $start = \Carbon\Carbon::parse($booking->period->start_date)->locale('th')->translatedFormat('j M Y');
            $end   = \Carbon\Carbon::parse($booking->period->end_date)->locale('th')->translatedFormat('j M Y');
            $travelDate = "{$start} — {$end}";
        }

        return [
            'booking_code'     => $booking->booking_code,
            'customer_name'    => $booking->first_name . ' ' . $booking->last_name,
            'customer_email'   => $booking->email,
            'customer_phone'   => $booking->phone,
            'tour_name'        => $booking->tour->title ?? 'ไม่ระบุ',
            'tour_code'        => $booking->tour->tour_code ?? '-',
            'travel_date'      => $travelDate,
            'total_passengers' => (string) $booking->total_passengers,
            'total_amount'     => '฿' . number_format($booking->total_amount, 0),
            'status_label'     => $booking->status_label,
            'year'             => (string) date('Y'),
        ];
    }

    /**
     * Replace {{variable}} placeholders in text
     */
    private static function replaceVariables(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            $text = str_replace("{{{$key}}}", $value, $text);
        }
        return $text;
    }

    /**
     * Create a Symfony Mailer from SMTP config
     */
    private static function createMailer(array $smtpConfig): Mailer
    {
        $password = '';
        if (!empty($smtpConfig['password'])) {
            try {
                $password = decrypt($smtpConfig['password']);
            } catch (\Exception $e) {
                $password = $smtpConfig['password'];
            }
        }

        $useTls = ($smtpConfig['encryption'] ?? '') === 'ssl';

        $transport = new EsmtpTransport(
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

        return new Mailer($transport);
    }
}
