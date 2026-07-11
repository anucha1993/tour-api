<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Tour;
use App\Models\WholesalerApiConfig;
use App\Jobs\ProcessTourMediaJob;

$cfg = WholesalerApiConfig::find(39);
$wcode = $cfg->wholesaler?->code ?? 'GS';

// เลือก tour จริงของ GS ที่มี pdf และ hash ยัง NULL
$tour = Tour::where('wholesaler_id', 9)
    ->whereNotNull('pdf_url')
    ->whereNull('pdf_branding_hash')
    ->first(['id','wholesaler_tour_code','pdf_url','pdf_branding_hash']);

echo "BEFORE:\n";
echo "  tour #{$tour->id} {$tour->wholesaler_tour_code}\n";
echo "  pdf_url = " . substr($tour->pdf_url,0,80) . "\n";
echo "  pdf_branding_hash = " . ($tour->pdf_branding_hash ?? 'NULL') . "\n\n";

// จำลองการ dispatch เหมือน SyncToursJob ทำ (แต่รัน handle() ตรงๆ แบบ synchronous)
$job = new ProcessTourMediaJob(
    $tour->id,
    $tour->pdf_url,          // pdf_url (external/ปัจจุบัน) → จะถูก brand ใหม่
    null,                    // cover_image_url
    $wcode,
    $cfg->pdf_header_image,
    $cfg->pdf_header_height,
    $cfg->pdf_footer_image,
    $cfg->pdf_footer_height,
    $tour->pdf_url,          // old_pdf_url
    null
);

echo "Running ProcessTourMediaJob synchronously...\n\n";
$job->handle();

$fresh = Tour::find($tour->id);
echo "AFTER:\n";
echo "  pdf_url = " . substr($fresh->pdf_url,0,90) . "\n";
echo "  pdf_branding_hash = " . ($fresh->pdf_branding_hash ?? 'NULL') . "\n\n";

$expected = md5(($cfg->pdf_header_image ?? '') . '|' . ($cfg->pdf_footer_image ?? ''));
echo "  expected hash = $expected\n";
echo "  => BRANDING " . ($fresh->pdf_branding_hash === $expected ? "INSERTED ✅" : "NOT inserted ❌") . "\n";
