<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Targeted SEO data fixes (safe, idempotent):
 *  1. /about  — give the page a page-specific og:image (reuse the About hero
 *     image) instead of falling back to the global OG image. Only sets it when
 *     the About row currently has no og_image, so an admin-set value is kept.
 *  2. /tours/international — clear the stale canonical_url (historically pointed
 *     at /tours/country/all, which de-indexed every ?country_id= variant). With
 *     it null the page self-canonicalizes to /tours/international.
 *
 * Run:  php artisan db:seed --class=SeoFixSeeder
 */
class SeoFixSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('seo_settings')) {
            $this->command->warn('seo_settings table missing — nothing to do.');
            return;
        }

        // ---- 1) /about og:image -------------------------------------------
        $aboutHeroImg = null;
        if (Schema::hasTable('about_page_settings')) {
            $aboutHeroImg = DB::table('about_page_settings')->value('hero_image_url');
        }

        $about = DB::table('seo_settings')->where('page_slug', 'about')->first();
        if (! $about) {
            $this->command->warn("about: no seo_settings row (page_slug='about') — skipped.");
        } elseif (! empty($about->og_image)) {
            $this->command->line("about: og_image already set ({$about->og_image}) — left unchanged.");
        } elseif (empty($aboutHeroImg)) {
            $this->command->warn('about: no About hero image found — cannot set a page-specific og:image (still falls back to global). Upload one in admin.');
        } else {
            DB::table('seo_settings')->where('page_slug', 'about')->update([
                'og_image'   => $aboutHeroImg,
                'updated_at' => now(),
            ]);
            $this->command->info("about: og_image set to About hero image -> {$aboutHeroImg}");
        }

        // ---- 2) /tours/international canonical -----------------------------
        $intl = DB::table('seo_settings')->where('page_slug', 'tours-international')->first();
        if (! $intl) {
            $this->command->warn("tours-international: no seo_settings row (page_slug='tours-international') — skipped.");
        } elseif (empty($intl->canonical_url)) {
            $this->command->line('tours-international: canonical_url already empty — left unchanged.');
        } else {
            $this->command->line("tours-international: canonical_url was '{$intl->canonical_url}'");
            DB::table('seo_settings')->where('page_slug', 'tours-international')->update([
                'canonical_url' => null,
                'updated_at'    => now(),
            ]);
            $this->command->info('tours-international: canonical_url cleared (now self-canonical).');
        }
    }
}
