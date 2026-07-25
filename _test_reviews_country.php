<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$slug = 'china';
$cid = DB::table('countries')->where('slug', $slug)->value('id');
$cntSlug = App\Models\TourReview::approved()->whereHas('tour.primaryCountry', function($q) use ($slug){ $q->where('slug',$slug); })->count();
$cntId = $cid ? App\Models\TourReview::approved()->whereHas('tour', function($q) use ($cid){ $q->where('primary_country_id',$cid); })->count() : 'no-country';

echo "china slug reviews={$cntSlug}, id({$cid}) reviews={$cntId}\n";
