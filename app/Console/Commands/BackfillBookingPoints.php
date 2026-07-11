<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\PointTransaction;
use App\Models\WebMember;
use App\Services\PointService;
use Illuminate\Console\Command;

/**
 * Backfill member points for bookings that are already paid/completed
 * but never received points (e.g. paid before the awarding logic existed).
 *
 * Usage:
 *   php artisan points:backfill-bookings                # award to all eligible
 *   php artisan points:backfill-bookings --dry-run      # preview only
 *   php artisan points:backfill-bookings --booking=123  # single booking
 *   php artisan points:backfill-bookings --member=45    # a specific member
 *   php artisan points:backfill-bookings --since=2026-01-01
 */
class BackfillBookingPoints extends Command
{
    protected $signature = 'points:backfill-bookings
        {--dry-run : Show what would happen without writing anything}
        {--booking= : Backfill only a specific booking id}
        {--member= : Backfill only bookings that belong to this web_member_id}
        {--since= : Only consider bookings created on/after this date (YYYY-MM-DD)}
        {--include-completed=1 : Include status=completed as well as paid (1/0)}';

    protected $description = 'Award member points retroactively for paid bookings that were missed';

    public function handle(PointService $service): int
    {
        $dry       = (bool) $this->option('dry-run');
        $onlyId    = $this->option('booking');
        $onlyMember = $this->option('member');
        $since     = $this->option('since');
        $incComp   = (string) $this->option('include-completed') === '1';

        $statuses = $incComp ? ['paid', 'completed'] : ['paid'];

        $query = Booking::query()
            ->whereIn('status', $statuses)
            ->whereNotNull('web_member_id')
            ->where('total_amount', '>', 0);

        if ($onlyId)     $query->where('id', $onlyId);
        if ($onlyMember) $query->where('web_member_id', $onlyMember);
        if ($since)      $query->where('created_at', '>=', $since);

        $total = (int) $query->count();
        if ($total === 0) {
            $this->info('No eligible bookings found.');
            return self::SUCCESS;
        }

        $this->line("Found {$total} candidate bookings" . ($dry ? ' (DRY-RUN)' : ''));

        $awarded = 0;
        $skipped = 0;
        $failed  = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunkById(200, function ($chunk) use (
            $service, $dry, &$awarded, &$skipped, &$failed, $bar
        ) {
            foreach ($chunk as $booking) {
                $bar->advance();

                // Idempotency: skip if already awarded for this booking
                $already = PointTransaction::where('source_type', Booking::class)
                    ->where('source_id', $booking->id)
                    ->where('type', 'earn')
                    ->exists();
                if ($already) { $skipped++; continue; }

                $member = WebMember::find($booking->web_member_id);
                if (!$member) { $skipped++; continue; }

                if ($dry) { $awarded++; continue; }

                try {
                    $amount = (float) $booking->total_amount;
                    $service->recordSpending($member, $amount);
                    $txn = $service->earnPoints(
                        $member,
                        'booking',
                        $amount,
                        Booking::class,
                        $booking->id,
                        "ชำระเงินการจอง {$booking->booking_code} (backfill)"
                    );
                    if ($txn) $awarded++; else $skipped++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Booking #{$booking->id} failed: " . $e->getMessage());
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Total', 'Awarded' . ($dry ? ' (would)' : ''), 'Skipped', 'Failed'],
            [[ $total, $awarded, $skipped, $failed ]],
        );

        if ($dry) {
            $this->comment('Dry-run only. Re-run without --dry-run to apply.');
        } else {
            $this->info('Backfill complete.');
        }

        return self::SUCCESS;
    }
}
