<?php

/**
 * Seed manual_override_fields for existing tours
 * 
 * This script marks commonly edited fields as "manually overridden" for existing tours.
 * Run: php artisan tinker < seed_manual_overrides.php
 * Or:  php seed_manual_overrides.php (from tour-api directory)
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tour;
use Illuminate\Support\Facades\DB;

echo "=== Seeding manual_override_fields for existing tours ===\n\n";

// Fields that are commonly edited manually and should be protected from API overwrites
$fieldsToMark = [
    'title',
    'description', 
    'short_description',
    'highlights',
    'conditions',
    'inclusions',
    'exclusions',
    'notes',
    'important_notes',
    'payment_terms',
    'cancellation_policy',
    'visa_info',
    'hashtags',
    'themes',
    'suitable_for',
    'hotel_star',
    'region',
    'sub_region',
    'badge',
    'seo_title',
    'seo_description',
    'seo_keywords',
    'og_title',
    'og_description',
];

// Get count first
$totalTours = Tour::whereNotNull('wholesaler_id')
    ->whereNull('manual_override_fields')
    ->count();

echo "Found {$totalTours} tours without manual_override_fields\n";

if ($totalTours === 0) {
    echo "Nothing to do - all tours already have override fields set.\n";
    exit(0);
}

// Option 1: Mark ALL fields for ALL tours (most protective)
echo "\nOptions:\n";
echo "1. Mark all editable fields for ALL tours (recommended)\n";
echo "2. Mark only tours with custom edits (title != original API title)\n";
echo "3. Mark status field only (minimal protection)\n";
echo "4. Skip - don't seed anything\n";
echo "\nChoose option (1-4): ";

$handle = fopen("php://stdin", "r");
$option = trim(fgets($handle));
fclose($handle);

$timestamp = now()->toIso8601String();
$processed = 0;
$skipped = 0;

switch ($option) {
    case '1':
        // Mark all fields for all tours
        echo "\nMarking all editable fields for all tours...\n";
        
        $overrides = [];
        foreach ($fieldsToMark as $field) {
            $overrides[$field] = $timestamp;
        }
        
        $updated = Tour::whereNotNull('wholesaler_id')
            ->whereNull('manual_override_fields')
            ->update(['manual_override_fields' => json_encode($overrides)]);
        
        echo "Updated {$updated} tours\n";
        break;
        
    case '2':
        // Mark only tours that appear to have custom edits
        echo "\nFinding tours with custom edits...\n";
        
        $tours = Tour::whereNotNull('wholesaler_id')
            ->whereNull('manual_override_fields')
            ->cursor();
        
        foreach ($tours as $tour) {
            $overrides = [];
            
            // Check which fields have values (likely edited)
            foreach ($fieldsToMark as $field) {
                $value = $tour->$field;
                if (!empty($value) && $value !== null) {
                    // Mark this field as having been edited
                    $overrides[$field] = $timestamp;
                }
            }
            
            if (!empty($overrides)) {
                $tour->manual_override_fields = $overrides;
                $tour->save();
                $processed++;
                
                if ($processed % 100 === 0) {
                    echo "Processed {$processed} tours...\n";
                }
            } else {
                $skipped++;
            }
        }
        
        echo "Done! Processed: {$processed}, Skipped: {$skipped}\n";
        break;
        
    case '3':
        // Mark status field only
        echo "\nMarking status field only...\n";
        
        $updated = Tour::whereNotNull('wholesaler_id')
            ->whereNull('manual_override_fields')
            ->update(['manual_override_fields' => json_encode(['status' => $timestamp])]);
        
        echo "Updated {$updated} tours with status override\n";
        break;
        
    case '4':
        echo "\nSkipped - no changes made.\n";
        break;
        
    default:
        echo "\nInvalid option. Exiting.\n";
        exit(1);
}

echo "\n=== Done! ===\n";

// Show summary
$withOverrides = Tour::whereNotNull('manual_override_fields')->count();
$withoutOverrides = Tour::whereNull('manual_override_fields')->count();

echo "\nSummary:\n";
echo "- Tours with overrides: {$withOverrides}\n";
echo "- Tours without overrides: {$withoutOverrides}\n";
