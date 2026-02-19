<?php
/**
 * seed_promotions_update.php
 * - อัปเดต max_claims ให้รายการเดิม (ID 1-5)
 * - เพิ่มรายการใหม่ 3 รายการ
 * Usage: php seed_promotions_update.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PromotionNotification;

// ── อัปเดต max_claims ของรายการเดิม ──────────────────────────────────────
$maxClaimsMap = [
    1 => 50,   // โปรโมชั่นพิเศษ ลด 20%
    2 => 20,   // Flash Sale ญี่ปุ่น
    3 => null, // Birthday — ไม่จำกัด
    4 => null, // สะสมแต้ม 2x — ไม่จำกัด
    5 => 100,  // ทัวร์ในประเทศ 1,990
];

$updated = 0;
foreach ($maxClaimsMap as $id => $max) {
    $n = PromotionNotification::find($id);
    if ($n && $n->max_claims === null && $max !== null) {
        $n->max_claims = $max;
        $n->save();
        echo "✅ Updated ID={$id} max_claims={$max}: {$n->title}\n";
        $updated++;
    } elseif ($n && $max === null) {
        echo "ℹ️  ID={$id} ไม่จำกัดสิทธิ์ (ไม่ต้องอัปเดต): {$n->title}\n";
    } elseif (!$n) {
        echo "⚠️  ไม่พบ ID={$id}\n";
    } else {
        echo "ℹ️  ID={$id} มี max_claims={$n->max_claims} อยู่แล้ว\n";
    }
}

// ── เพิ่มรายการใหม่ ────────────────────────────────────────────────────────
$newItems = [
    [
        'title'       => '🇰🇷 Korea Early Bird! จองล่วงหน้าได้ราคาดีที่สุด',
        'description' => "จองทัวร์เกาหลีล่วงหน้า 60 วัน รับส่วนลดพิเศษสูงสุด 3,000 บาทต่อท่าน\nโปรแกรม 5 วัน 3 คืน ครบทุก Highlight ทั้ง Seoul · Nami · Everland",
        'how_to_use'  => "1. กดรับสิทธิ์เพื่อล็อกราคา Early Bird\n2. ติดต่อเจ้าหน้าที่ภายใน 7 วันเพื่อเลือกวันเดินทาง\n3. แจ้งรหัสสิทธิ์และวางมัดจำ 5,000 บาทต่อท่าน\n4. รับส่วนลด 3,000 บาท เมื่อชำระยอดเต็ม\n\n📅 ราคา Early Bird ใช้ได้กับการเดินทางตั้งแต่เดือนมีนาคมเป็นต้นไป",
        'max_claims'  => 30,
        'banner_url'  => null,
        'type'        => 'promotion',
        'target_type' => 'all',
        'is_active'   => true,
        'starts_at'   => now(),
        'ends_at'     => now()->addDays(45),
    ],
    [
        'title'       => '⭐ สมาชิก VIP เท่านั้น! อัปเกรดห้องพักฟรี',
        'description' => "สำหรับสมาชิกระดับ Gold ขึ้นไปเท่านั้น\nจองทัวร์ยุโรปหรือทัวร์ญี่ปุ่นรับการอัปเกรดห้องพักเป็น Superior Room โดยไม่มีค่าใช้จ่ายเพิ่มเติม",
        'how_to_use'  => "1. กดรับสิทธิ์เพื่อยืนยันสิทธิ์ VIP ของคุณ\n2. เลือกทัวร์ยุโรปหรือทัวร์ญี่ปุ่นที่ต้องการ\n3. แจ้งรหัสสิทธิ์กับเจ้าหน้าที่พร้อมระบุว่าต้องการอัปเกรดห้อง\n4. เจ้าหน้าที่จะดำเนินการอัปเกรดให้ก่อนออกเอกสาร\n\n⭐ สิทธิ์นี้ใช้ได้ 1 ครั้งต่อการจอง และไม่สามารถโอนสิทธิ์ให้ผู้อื่นได้",
        'max_claims'  => 15,
        'banner_url'  => null,
        'type'        => 'special',
        'target_type' => 'all',
        'is_active'   => true,
        'starts_at'   => now(),
        'ends_at'     => now()->addDays(90),
    ],
    [
        'title'       => '📢 เปิดตัวโปรแกรมใหม่! ทัวร์สแกนดิเนเวีย 10 วัน',
        'description' => "เปิดตัวโปรแกรมใหม่ล่าสุด ทัวร์สแกนดิเนเวีย ผ่าน 3 ประเทศ นอร์เวย์ สวีเดน เดนมาร์ก\nรวมตั๋วเครื่องบิน โรงแรม 4 ดาว และ Northern Lights Experience\nราคาเริ่มต้น 89,900 บาท",
        'how_to_use'  => "1. กดรับสิทธิ์เพื่อรับข้อมูล Brochure พร้อมราคาพิเศษ Early Member\n2. เจ้าหน้าที่จะติดต่อกลับภายใน 24 ชั่วโมงเพื่อนำเสนอรายละเอียด\n3. สมาชิกที่กดรับสิทธิ์ภายในเดือนนี้รับส่วนลดเพิ่ม 2,000 บาท\n\n🌍 โปรแกรมนี้เปิดรับจองสำหรับสมาชิกก่อนเปิดขายทั่วไป",
        'max_claims'  => null,
        'banner_url'  => null,
        'type'        => 'custom',
        'target_type' => 'all',
        'is_active'   => true,
        'starts_at'   => now(),
        'ends_at'     => now()->addDays(14),
    ],
];

$added = 0;
foreach ($newItems as $data) {
    $exists = PromotionNotification::where('title', $data['title'])->exists();
    if ($exists) {
        echo "ℹ️  มีอยู่แล้ว (ข้าม): {$data['title']}\n";
        continue;
    }
    $n = PromotionNotification::create($data);
    echo "✅ Added ID={$n->id}: {$n->title}" . ($n->max_claims ? " (max={$n->max_claims})" : " (ไม่จำกัด)") . "\n";
    $added++;
}

echo "\n✅ อัปเดต {$updated} รายการ | เพิ่มใหม่ {$added} รายการ\n";
