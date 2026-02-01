<?php

namespace App\Console\Commands;

use App\Models\WholesalerFieldMapping;
use App\Models\Tour;
use Illuminate\Console\Command;

class DebugMappings extends Command
{
    protected $signature = 'debug:mappings {wholesaler_id}';
    protected $description = 'Debug field mappings for a wholesaler';

    public function handle()
    {
        $wholesalerId = $this->argument('wholesaler_id');

        $this->info("=== Mappings for Wholesaler {$wholesalerId} ===\n");

        // All mappings with section
        $allMappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
            ->get(['section_name', 'our_field', 'their_field', 'transform_type']);
        
        $this->info("All Mappings by Section:");
        $grouped = $allMappings->groupBy('section_name');
        foreach ($grouped as $sectionName => $mappings) {
            $sectionDisplay = $sectionName ?: '(empty)';
            $this->line("\n[{$sectionDisplay}] - " . count($mappings) . " fields:");
            foreach ($mappings as $m) {
                $this->line("  {$m->our_field} ← {$m->their_field} ({$m->transform_type})");
            }
        }
        $this->line("");

        // Key fields
        $keyFields = ['primary_country_id', 'transport_id', 'hotel_star', 'cover_image_url', 'pdf_url', 'description', 'highlights', 'meta_title'];
        
        foreach ($keyFields as $field) {
            $mapping = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
                ->where('our_field', $field)
                ->first();
            
            if ($mapping) {
                $this->line("✓ {$field}:");
                $this->line("  Section: {$mapping->section_name}");
                $this->line("  Their field: {$mapping->their_field}");
                $this->line("  Transform: {$mapping->transform_type}");
                if ($mapping->transform_config) {
                    $this->line("  Config: " . json_encode($mapping->transform_config));
                }
            } else {
                $this->error("✗ {$field}: NOT MAPPED");
            }
            $this->line("");
        }

        // Check latest synced tour data
        $this->info("=== Latest Synced Tour Data ===\n");
        
        $tour = Tour::where('wholesaler_id', $wholesalerId)
            ->orderBy('updated_at', 'desc')
            ->first();
        
        if ($tour) {
            $this->table(
                ['Field', 'Value'],
                [
                    ['tour_code', $tour->tour_code],
                    ['title', mb_substr($tour->title ?? '', 0, 40) . '...'],
                    ['primary_country_id', $tour->primary_country_id ?: 'NULL'],
                    ['transport_id', $tour->transport_id ?: 'NULL'],
                    ['hotel_star', $tour->hotel_star ?: 'NULL'],
                    ['cover_image_url', $tour->cover_image_url ? 'SET' : 'NULL'],
                    ['pdf_url', $tour->pdf_url ? 'SET' : 'NULL'],
                    ['description', $tour->description ? 'SET (' . mb_strlen($tour->description) . ' chars)' : 'NULL'],
                    ['highlights', $tour->highlights ? 'SET' : 'NULL'],
                    ['meta_title', $tour->meta_title ?: 'NULL'],
                ]
            );
            
            // Check periods/pricing
            $period = $tour->periods()->with('offer')->first();
            if ($period) {
                $this->info("\n=== Period Data ===");
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['start_date', $period->start_date],
                        ['end_date', $period->end_date],
                        ['capacity', $period->capacity],
                        ['available', $period->available],
                        ['price_adult', $period->offer?->price_adult ?: 'NULL'],
                        ['price_child', $period->offer?->price_child ?: 'NULL'],
                    ]
                );
            } else {
                $this->warn("No periods found");
            }
        } else {
            $this->warn("No tours found for this wholesaler");
        }

        return 0;
    }
}
