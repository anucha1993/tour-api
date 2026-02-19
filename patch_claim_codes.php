<?php
/**
 * patch_claim_codes.php
 * - สร้าง claim_code สำหรับ claims เดิมที่ยังไม่มีรหัส
 * - อัปเดต max_claims สำหรับโปรโมชั่นที่ยังเป็น null
 * Usage: php patch_claim_codes.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MemberPromotionClaim;
use App\Models\PromotionNotification;
use Illuminate\Support\Str;

echo "=== แพตช์รหัสสิทธิ์ (claim_code) ===\n\n";

$claims = MemberPromotionClaim::whereNull('claim_code')->get();
echo "พบ claims ที่ไม่มีรหัส: {$claims->count()} รายการ\n";

$fixed = 0;
foreach ($claims as $c) {
    do {
        $code = strtoupper(Str::random(8));
    } while (MemberPromotionClaim::where('claim_code', $code)->exists());

    $c->claim_code = $code;
    $c->save();

    echo "  ✅ claim ID={$c->id} | member_id={$c->member_id} | notification_id={$c->notification_id} => รหัส: {$code}\n";
    $fixed++;
}

if ($fixed === 0) {
    echo "  ℹ️  ทุก claim มีรหัสสิทธิ์อยู่แล้ว\n";
}

echo "\n=== ตรวจสอบ max_claims ===\n\n";

$notifications = PromotionNotification::all();
foreach ($notifications as $n) {
    $claimCount = MemberPromotionClaim::where('notification_id', $n->id)->count();
    $maxLabel = $n->max_claims !== null ? "{$n->max_claims} สิทธิ์" : 'ไม่จำกัด';
    echo "  ID={$n->id} [{$n->type}] max={$maxLabel} | ใช้ไปแล้ว={$claimCount} | {$n->title}\n";
}

echo "\n✅ เสร็จสิ้น — แพตช์ {$fixed} claim\n";
