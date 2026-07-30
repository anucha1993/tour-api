<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SendBookingToInvoice - Notify the nexttrip-invoice app when a booking is confirmed.
 *
 * Posts a booking snapshot to the invoice webhook so it can auto-create a
 * quotation (INBOUND flow). The invoice endpoint is idempotent on bookingCode,
 * so retries are safe.
 */
class SendBookingToInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 90];

    public function __construct(protected int $bookingId)
    {
    }

    public function handle(): void
    {
        $url = (string) config('services.invoice.url');
        $secret = (string) config('services.invoice.webhook_secret');
        $timeout = (int) config('services.invoice.timeout', 15);

        if ($url === '' || $secret === '') {
            Log::warning('SendBookingToInvoice: INVOICE_URL / INVOICE_WEBHOOK_SECRET not configured, skipping', [
                'booking_id' => $this->bookingId,
            ]);

            return;
        }

        $booking = Booking::with([
            'tour.primaryCountry',
            'tour.wholesaler',
            'tour.transports',
            'period',
        ])->find($this->bookingId);

        if (! $booking) {
            Log::warning('SendBookingToInvoice: booking not found', ['booking_id' => $this->bookingId]);

            return;
        }

        $payload = $this->buildPayload($booking);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'X-Webhook-Secret' => $secret,
                'Accept' => 'application/json',
            ])
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('SendBookingToInvoice: invoice webhook returned an error', [
                'booking_id' => $this->bookingId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);

            // Throwing lets the queue retry with backoff (idempotent on the invoice side).
            throw new \RuntimeException('Invoice webhook failed with HTTP ' . $response->status());
        }

        $booking->forceFill(['invoice_sent_at' => now()])->save();

        Log::info('SendBookingToInvoice: booking synced to invoice', [
            'booking_id' => $this->bookingId,
            'booking_code' => $booking->booking_code,
            'invoice_response' => $response->json(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(Booking $booking): array
    {
        $tour = $booking->tour;
        $period = $booking->period;

        $airline = $tour?->transports?->firstWhere('transport_type', 'airline');

        $pax = [
            'adult' => (int) $booking->qty_adult,
            'adultSingle' => (int) $booking->qty_adult_single,
            'childBed' => (int) $booking->qty_child_bed,
            'childNoBed' => (int) $booking->qty_child_nobed,
            'infant' => (int) $booking->qty_infant,
        ];

        return [
            'bookingId' => $booking->id,
            'bookingCode' => $booking->booking_code,
            'status' => $booking->status,
            'source' => $booking->source,
            'providerBookingRef' => $booking->provider_booking_ref,
            'confirmedAt' => now()->toIso8601String(),

            'customer' => [
                'firstName' => $booking->first_name,
                'lastName' => $booking->last_name,
                'email' => $booking->email,
                'phone' => $booking->phone,
            ],

            'tour' => [
                'tourId' => $booking->tour_id,
                'periodId' => $booking->period_id,
                'tourCode' => $tour?->tour_code,
                'wholesalerTourCode' => $tour?->wholesaler_tour_code,
                'title' => $tour?->title,
                'durationDays' => $tour?->duration_days !== null ? (int) $tour->duration_days : null,
                'durationNights' => $tour?->duration_nights !== null ? (int) $tour->duration_nights : null,
                'wholesalerId' => $tour?->wholesaler_id,
                'wholesalerName' => $tour?->wholesaler?->company_name_th ?? $tour?->wholesaler?->name,
                'countryId' => $tour?->primary_country_id,
                'countryName' => $tour?->primaryCountry?->name_th,
                'airlineId' => $airline?->transport_id,
                'airlineName' => $airline?->transport_name,
            ],

            'travel' => [
                'departureDate' => $period?->start_date?->toDateString(),
                'returnDate' => $period?->end_date?->toDateString(),
            ],

            'pax' => $pax,

            'prices' => [
                'adult' => (float) $booking->price_adult,
                'single' => (float) $booking->price_single,
                'childBed' => (float) $booking->price_child_bed,
                'childNoBed' => (float) $booking->price_child_nobed,
                'infant' => (float) $booking->price_infant,
                'total' => (float) $booking->total_amount,
                'currency' => $booking->currency ?? 'THB',
            ],

            'saleCode' => $booking->sale_code,
            'specialRequest' => $booking->special_request,
        ];
    }
}
