<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Auto-cancel bookings whose provider hold has expired.
 *
 * Runs every minute. Bookings with provider_status='held' or 'quoted'
 * and hold_expires_at < now() are flipped to 'cancelled' / 'failed' so
 * the seat / quote is released downstream.
 */
class ExpireHeldBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function handle(): void
    {
        $count = 0;

        Booking::query()
            ->whereIn('provider_status', ['quoted', 'held'])
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now())
            ->chunkById(200, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    $booking->update([
                        'provider_status' => 'cancelled',
                        'status' => $booking->status === 'pending' ? 'cancelled' : $booking->status,
                        'cancelled_by' => 'system',
                        'admin_note' => trim(($booking->admin_note ?? '')
                            . "\n[auto] hold expired at " . now()->toDateTimeString()),
                    ]);
                    $count++;
                }
            });

        if ($count > 0) {
            Log::info("ExpireHeldBookingsJob: cancelled {$count} expired holds");
        }
    }
}
