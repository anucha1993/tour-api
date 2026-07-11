<?php

namespace App\Services;

use App\Models\MemberLevel;
use App\Models\PointRedemption;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\WebMember;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PointService
{
    /**
     * Award points to a member for an action
     *
     * @param WebMember $member
     * @param string $action  e.g. 'page_view', 'review', 'booking'
     * @param float $amount   Amount for percent-based calculations (optional)
     * @param string|null $sourceType Polymorphic source class
     * @param int|null $sourceId Polymorphic source ID
     * @param string|null $description Custom description
     * @return PointTransaction|null
     */
    public function earnPoints(
        WebMember $member,
        string $action,
        float $amount = 0,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $description = null
    ): ?PointTransaction {
        $rule = PointRule::getByAction($action);
        if (!$rule) {
            return null;
        }

        // Check cooldown
        if ($rule->cooldown_minutes > 0 && $sourceType && $sourceId) {
            $recentEarn = PointTransaction::where('member_id', $member->id)
                ->where('rule_id', $rule->id)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('created_at', '>=', now()->subMinutes($rule->cooldown_minutes))
                ->exists();

            if ($recentEarn) {
                return null; // Still in cooldown
            }
        }

        // Check daily cap
        if ($rule->max_points_per_day) {
            $todayPoints = PointTransaction::where('member_id', $member->id)
                ->where('rule_id', $rule->id)
                ->where('type', 'earn')
                ->whereDate('created_at', today())
                ->sum('points');

            if ($todayPoints >= $rule->max_points_per_day) {
                return null; // Daily limit reached
            }
        }

        // Calculate points (apply level multiplier)
        $basePoints = $rule->calculatePoints($amount);
        if ($basePoints <= 0) {
            return null;
        }

        $multiplier = $member->level?->point_multiplier ?? 1.0;
        $points = (int) round($basePoints * $multiplier);

        // Enforce daily cap remaining
        if ($rule->max_points_per_day) {
            $todayPoints = $todayPoints ?? 0;
            $remaining = $rule->max_points_per_day - $todayPoints;
            $points = min($points, $remaining);
            if ($points <= 0) return null;
        }

        return $this->createTransaction(
            member: $member,
            type: 'earn',
            points: $points,
            ruleId: $rule->id,
            sourceType: $sourceType,
            sourceId: $sourceId,
            description: $description ?? $rule->name,
            expireDays: $rule->expire_days
        );
    }

    /**
     * Spend (redeem) points
     */
    public function spendPoints(
        WebMember $member,
        int $points,
        ?string $description = null,
        ?string $bookingCode = null
    ): ?PointRedemption {
        if ($points <= 0 || $member->total_points < $points) {
            return null;
        }

        $level = $member->level;
        $redemptionRate = $level?->redemption_rate ?? 1.0;
        $discountAmount = $points * $redemptionRate;

        return DB::transaction(function () use ($member, $points, $description, $bookingCode, $redemptionRate, $discountAmount) {
            $transaction = $this->createTransaction(
                member: $member,
                type: 'spend',
                points: -$points,
                description: $description ?? "แลกส่วนลด ฿" . number_format($discountAmount, 2)
            );

            $redemption = PointRedemption::create([
                'member_id' => $member->id,
                'transaction_id' => $transaction->id,
                'points_used' => $points,
                'discount_amount' => $discountAmount,
                'redemption_rate' => $redemptionRate,
                'booking_code' => $bookingCode,
                'status' => 'applied',
            ]);

            return $redemption;
        });
    }

    /**
     * Admin manual adjustment (positive = add, negative = deduct)
     */
    public function adjustPoints(
        WebMember $member,
        int $points,
        ?string $description = null
    ): PointTransaction {
        $rule = PointRule::getByAction('manual');

        // Choose the correct transaction type so history/stats stay consistent
        $type = $points >= 0 ? 'earn' : 'spend';

        return $this->createTransaction(
            member: $member,
            type: $type,
            points: $points,
            ruleId: $rule?->id,
            description: $description ?? 'ปรับคะแนนโดยแอดมิน',
            expireDays: $type === 'earn' ? ($rule?->expire_days ?? 365) : null,
        );
    }

    /**
     * Expire outdated points
     * Called by scheduled job daily
     */
    public function expirePoints(): int
    {
        $expiredCount = 0;

        // Find all earn transactions that have expired and haven't been marked
        $expiredTransactions = PointTransaction::where('type', 'earn')
            ->where('is_expired', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('points', '>', 0)
            ->get();

        foreach ($expiredTransactions as $txn) {
            DB::transaction(function () use ($txn, &$expiredCount) {
                $member = $txn->member;
                if (!$member) return;

                // Mark as expired
                $txn->update(['is_expired' => true]);

                // Create expire transaction
                $this->createTransaction(
                    member: $member,
                    type: 'expire',
                    points: -$txn->points,
                    description: "คะแนนหมดอายุ (#{$txn->id})"
                );

                $expiredCount++;
            });
        }

        Log::info("PointService: Expired {$expiredCount} transactions");
        return $expiredCount;
    }

    /**
     * Check and upgrade/downgrade member level based on spending
     */
    public function checkLevelUpgrade(WebMember $member): bool
    {
        $newLevel = MemberLevel::getLevelForSpending((float) $member->lifetime_spending);
        if (!$newLevel) return false;

        $currentLevelId = $member->current_level_id;
        if ($newLevel->id !== $currentLevelId) {
            $member->update([
                'current_level_id' => $newLevel->id,
                'level_upgraded_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Record tour booking spending and check level upgrade
     */
    public function recordSpending(WebMember $member, float $amount): void
    {
        $member->increment('lifetime_spending', $amount);
        $member->refresh();
        $this->checkLevelUpgrade($member);
    }

    /**
     * Get member point summary
     */
    public function getSummary(WebMember $member): array
    {
        $level = $member->level ?? MemberLevel::getDefault();
        $spending = (float) $member->lifetime_spending;

        $nextLevel = MemberLevel::active()
            ->where('min_spending', '>', $spending)
            ->orderBy('min_spending')
            ->first();

        $expiringPoints = PointTransaction::where('member_id', $member->id)
            ->where('type', 'earn')
            ->where('is_expired', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('points', '>', 0)
            ->sum('points');

        $thisMonthEarned = PointTransaction::where('member_id', $member->id)
            ->where('type', 'earn')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('points');

        return [
            'total_points' => $member->total_points,
            'lifetime_points' => $member->lifetime_points,
            'lifetime_spending' => $spending,
            'level' => $level ? [
                'id' => $level->id,
                'name' => $level->name,
                'slug' => $level->slug,
                'icon' => $level->icon,
                'color' => $level->color,
                'discount_percent' => (float) $level->discount_percent,
                'point_multiplier' => (float) $level->point_multiplier,
                'redemption_rate' => (float) $level->redemption_rate,
            ] : null,
            'next_level' => $nextLevel ? [
                'name' => $nextLevel->name,
                'icon' => $nextLevel->icon,
                'min_spending' => (float) $nextLevel->min_spending,
                'spending_needed' => max(0, (float) $nextLevel->min_spending - $spending),
                'progress_percent' => $level ? min(100, round(
                    ($spending - (float) $level->min_spending) /
                    max(1, (float) $nextLevel->min_spending - (float) $level->min_spending) * 100
                )) : 0,
            ] : null,
            'expiring_points' => $expiringPoints,
            'this_month_earned' => $thisMonthEarned,
        ];
    }

    /**
     * Core: create a point transaction and update member balance
     */
    private function createTransaction(
        WebMember $member,
        string $type,
        int $points,
        ?int $ruleId = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $description = null,
        ?int $expireDays = null
    ): PointTransaction {
        return DB::transaction(function () use ($member, $type, $points, $ruleId, $sourceType, $sourceId, $description, $expireDays) {
            // Update member balance
            $member->total_points = max(0, $member->total_points + $points);
            if ($points > 0 && $type === 'earn') {
                $member->lifetime_points += $points;
            }
            $member->save();

            // Create transaction
            $txn = PointTransaction::create([
                'member_id' => $member->id,
                'rule_id' => $ruleId,
                'type' => $type,
                'points' => $points,
                'balance_after' => $member->total_points,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'expires_at' => ($type === 'earn' && $expireDays)
                    ? now()->addDays($expireDays)
                    : null,
                'is_expired' => false,
            ]);

            // Check level upgrade
            if ($type === 'earn') {
                $this->checkLevelUpgrade($member);
            }

            return $txn;
        });
    }
}
