<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckSyncData extends Command
{
    protected $signature = 'check:sync {wholesaler_id=1}';
    protected $description = 'Check synced data for a wholesaler';

    public function handle()
    {
        $wholesalerId = $this->argument('wholesaler_id');
        
        // Show ALL mappings
        $this->info("=== ALL Mappings ===");
        $allMappings = DB::table('wholesaler_field_mappings')
            ->where('wholesaler_id', $wholesalerId)
            ->orderBy('section_name')
            ->get();
        foreach ($allMappings as $m) {
            $this->line("[{$m->section_name}] {$m->their_field} -> {$m->our_field}");
        }
        $this->line("");
        
        $this->info("=== Tours Full Details ===");
        $tours = DB::table('tours')->where('wholesaler_id', $wholesalerId)->get();
        foreach ($tours as $t) {
            $this->line("ID:{$t->id} | {$t->title}");
            $this->line("  tour_code: {$t->tour_code}");
            $this->line("  wholesaler_tour_code: {$t->wholesaler_tour_code}");
            $this->line("  days: {$t->duration_days} | nights: {$t->duration_nights}");
            $this->line("  primary_country_id: {$t->primary_country_id}");
            $this->line("  transport_id: {$t->transport_id}");
            $this->line("  hotel_star: {$t->hotel_star}");
            $this->line("  cover_image_url: " . ($t->cover_image_url ?? 'NULL'));
            $this->line("  pdf_url: " . ($t->pdf_url ?? 'NULL'));
            $this->line("  description: " . (mb_substr($t->description ?? '', 0, 50) ?: 'NULL'));
            $this->line("  highlights: " . (mb_substr($t->highlights ?? '', 0, 50) ?: 'NULL'));
            
            // Periods & Offers
            $periods = DB::table('periods')->where('tour_id', $t->id)->get();
            $this->line("  Periods: " . count($periods));
            foreach ($periods as $p) {
                $offer = DB::table('offers')->where('period_id', $p->id)->first();
                $priceAdult = $offer ? $offer->price_adult : 'N/A';
                $this->line("    P{$p->id}: {$p->start_date} - {$p->end_date} | adult:{$priceAdult} | seats:{$p->available}/{$p->capacity}");
            }
            
            $this->line("");
        }
        
        return 0;
    }
}
