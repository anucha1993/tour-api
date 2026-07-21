<?php

namespace Tests\Feature;

use App\Models\Period;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression guard for the "full-but-upcoming tour disappears from the site" bug.
 *
 * Root cause was public listing/search/menu queries hardcoding
 * ->where('status', 'open'), which excluded "sold_out" (เต็ม) periods. An
 * upcoming departure that is full then vanished from the whole website even
 * though the business rule is: a full-but-future tour MUST still be shown so
 * customers see the real price, that it is genuinely full, and can browse the
 * itinerary.
 *
 * The fix centralised visibility into Period::DISPLAY_STATUSES /
 * Period::scopeDisplayable() (the SINGLE SOURCE OF TRUTH). These tests fail
 * loudly if someone drops "sold_out" from the displayable set or flips the
 * default, which is exactly how the bug would be reintroduced.
 *
 * Kept DB-free on purpose (pre-seeds the settings cache) so it runs anywhere
 * without the full MySQL schema.
 */
class PeriodDisplayableScopeTest extends TestCase
{
    /** Cache key used by PeriodDisplayFilter::settings(). */
    private const SETTINGS_CACHE_KEY = 'period_display_settings';

    private function seedHideFull(bool $hideFull): void
    {
        Cache::put(self::SETTINGS_CACHE_KEY, [
            'hide_past' => true,
            'hide_full' => $hideFull,
        ], 300);
    }

    protected function tearDown(): void
    {
        Cache::forget(self::SETTINGS_CACHE_KEY);
        parent::tearDown();
    }

    public function test_display_statuses_constant_keeps_sold_out_public_and_hides_closed(): void
    {
        $this->assertContains(Period::STATUS_OPEN, Period::DISPLAY_STATUSES);
        $this->assertContains(
            Period::STATUS_SOLD_OUT,
            Period::DISPLAY_STATUSES,
            'A full (sold_out) upcoming departure must stay publicly visible.'
        );

        $this->assertNotContains(Period::STATUS_CLOSED, Period::DISPLAY_STATUSES);
        $this->assertNotContains(Period::STATUS_CANCELLED, Period::DISPLAY_STATUSES);
    }

    public function test_displayable_statuses_default_includes_sold_out(): void
    {
        $this->seedHideFull(false);

        $this->assertSame(
            [Period::STATUS_OPEN, Period::STATUS_SOLD_OUT],
            Period::displayableStatuses(),
            'By default (hide_full = false) full departures remain visible.'
        );
    }

    public function test_displayable_statuses_can_hide_full_when_admin_opts_in(): void
    {
        $this->seedHideFull(true);

        $this->assertSame(
            [Period::STATUS_OPEN],
            Period::displayableStatuses(),
            'When hide_full = true only open departures are shown.'
        );
    }

    public function test_displayable_scope_binds_open_and_sold_out_by_default(): void
    {
        $this->seedHideFull(false);

        $query = Period::query()->displayable();

        $this->assertStringContainsStringIgnoringCase('in (?, ?)', $query->toSql());
        $this->assertSame(
            [Period::STATUS_OPEN, Period::STATUS_SOLD_OUT],
            $query->getBindings()
        );
    }

    public function test_displayable_scope_binds_only_open_when_hide_full(): void
    {
        $this->seedHideFull(true);

        $query = Period::query()->displayable();

        $this->assertSame([Period::STATUS_OPEN], $query->getBindings());
    }
}
