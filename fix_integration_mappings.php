<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// 1. Update aggregation_config
$newConfig = json_encode([
    'data_structure' => [
        'departures' => ['path' => 'periods[].tour_period[]'],
        'itineraries' => ['path' => 'tour_daily[]'],
        'cities' => ['path' => 'tour_city[]'],
    ]
]);

DB::table('wholesaler_api_configs')
    ->where('id', 11)
    ->update(['aggregation_config' => $newConfig]);

echo "✅ Updated aggregation_config\n";

// 2. Update itinerary mappings (correct path and add missing fields)
// Delete old incorrect mapping
DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 7)
    ->where('section_name', 'itinerary')
    ->delete();

// Insert correct itinerary mappings
$itineraryMappings = [
    [
        'wholesaler_id' => 7,
        'section_name' => 'itinerary',
        'our_field' => 'day_number',
        'their_field' => 'tour_daily[].day_num',
        'their_field_path' => 'tour_daily[].day_num',
        'transform_type' => 'direct',
        'is_active' => 1,
        'sort_order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'wholesaler_id' => 7,
        'section_name' => 'itinerary',
        'our_field' => 'title',
        'their_field' => 'tour_daily[].day_topics',
        'their_field_path' => 'tour_daily[].day_topics',
        'transform_type' => 'direct',
        'is_active' => 1,
        'sort_order' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'wholesaler_id' => 7,
        'section_name' => 'itinerary',
        'our_field' => 'description',
        'their_field' => 'tour_daily[].day_list',
        'their_field_path' => 'tour_daily[].day_list',
        'transform_type' => 'direct',
        'is_active' => 1,
        'sort_order' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ],
];

DB::table('wholesaler_field_mappings')->insert($itineraryMappings);
echo "✅ Updated itinerary mappings\n";

// 3. Add city mappings
$cityMappingExists = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 7)
    ->where('section_name', 'city')
    ->exists();

if (!$cityMappingExists) {
    $cityMappings = [
        [
            'wholesaler_id' => 7,
            'section_name' => 'city',
            'our_field' => 'external_id',
            'their_field' => 'tour_city[].city_id',
            'their_field_path' => 'tour_city[].city_id',
            'transform_type' => 'direct',
            'is_active' => 1,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'wholesaler_id' => 7,
            'section_name' => 'city',
            'our_field' => 'name_th',
            'their_field' => 'tour_city[].city_name_th',
            'their_field_path' => 'tour_city[].city_name_th',
            'transform_type' => 'direct',
            'is_active' => 1,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'wholesaler_id' => 7,
            'section_name' => 'city',
            'our_field' => 'name_en',
            'their_field' => 'tour_city[].city_name_en',
            'their_field_path' => 'tour_city[].city_name_en',
            'transform_type' => 'direct',
            'is_active' => 1,
            'sort_order' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'wholesaler_id' => 7,
            'section_name' => 'city',
            'our_field' => 'country_id',
            'their_field' => 'tour_city[].country_id',
            'their_field_path' => 'tour_city[].country_id',
            'transform_type' => 'direct',
            'is_active' => 1,
            'sort_order' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];

    DB::table('wholesaler_field_mappings')->insert($cityMappings);
    echo "✅ Added city mappings\n";
} else {
    echo "⏭️ City mappings already exist\n";
}

echo "\n=== Done ===\n";
