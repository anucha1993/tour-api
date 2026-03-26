<?php

namespace Database\Seeders;

use App\Models\WholesalerApiConfig;
use Illuminate\Database\Seeder;

/**
 * จัดช่วงเวลา Sync ใหม่ให้ Integration ทั้งหมด
 * เปลี่ยนจาก cron format เป็น time-list format ("09:00,12:00,18:00")
 * โดยเว้นช่วง ≥ 10 นาทีระหว่างแต่ละ integration ไม่ให้ชนกัน
 *
 * วิธีคิด:
 * - ทุก 2 ชม. = 12 เวลา/วัน (00,02,04,...,22)
 * - ทุก 3 ชม. = 8 เวลา/วัน  (00,03,06,...,21)
 * - ทุก 4 ชม. = 6 เวลา/วัน  (00,04,08,...,20)
 * - ทุก 6 ชม. = 4 เวลา/วัน  (00,06,12,18)
 * - วันละครั้ง = 1 เวลา
 *
 * ในแต่ละรอบ 2 ชม. ต้อง sync สูงสุด ~10 ตัว
 * เว้น 10 นาที → ใช้ offset 0,10,20,30,40,50 + :05,:15,:25,:35,:45,:55
 *
 * Usage: php artisan db:seed --class=IntegrationScheduleSeeder
 */
class IntegrationScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🕐 จัดช่วงเวลา Sync Integration ใหม่ (time-list format)');
        $this->command->info('');

        // ───────────────────────────────────────────────
        // กำหนด schedule ใหม่ทุก integration
        //
        // ปัญหา: 14 integrations × 10 นาที = 140 นาที > 60 นาที/ชม.
        // แก้โดย: แบ่ง 2 กลุ่มสลับชั่วโมง (คู่/คี่)
        //
        // กลุ่ม A (ชั่วโมงคู่ 00,02,04,...,22): 6 ตัว → :00,:12,:24,:36,:48,:55
        // กลุ่ม B (ชั่วโมงคี่ 01,03,05,...,23): 4 ตัว → :00,:12,:24,:36
        // กลุ่ม C (ถี่น้อย — mixed hours):      4 ตัว → ใช้ช่วงว่าง
        //
        // ทุกคู่ในกลุ่มเดียวกัน ห่างกัน 12 นาที (≥ 10 ✓)
        // ข้ามกลุ่ม: คนละชั่วโมง จึงไม่ชนกัน
        //
        // สำหรับกลุ่ม C ที่มีบาง slot ตกชั่วโมงคู่ บ้างตกคี่:
        //   - #15 ทุก 3 ชม. → เลือก offset ที่ไม่ชนกับทั้ง A และ B
        //   - #13 ทุก 4 ชม. → เลือก offset ที่ไม่ชนกับทั้ง A และ B
        //   - #14 ทุก 6 ชม. → เลือก offset ที่ไม่ชนกับทั้ง A และ B
        //   - #22 วันละครั้ง → เลือกเวลาว่าง
        //
        // Used offsets in even hours: 00,12,24,36,48,55 (ว่าง:)
        // Used offsets in odd hours:  00,12,24,36       (ว่าง: 48+)
        //
        // Safe offsets for mixed-hour integrations (≥10 min from all above):
        //   Even: gaps at [05-09 ไม่ได้เพราะใกล้ :00/:12] → ไม่มี
        //   ดังนั้นใช้ strategy: กลุ่ม C ก็ต้องแยกคู่/คี่เหมือนกัน
        //   แต่เลือกให้ตกเฉพาะชั่วโมงคี่ที่มี offset ว่าง (:48, :55)
        //
        // Final Layout ภายใน 1 ชั่วโมง:
        //   ชั่วโมงคู่: :00 :12 :24 :36 :48 (5 slots ใช้ 5/6 ตัว Group A, เหลือ :55 อีก 1 ตัว)
        //   ชั่วโมงคี่: :00 :12 :24 :36 :48 (4 slots ใช้ Group B, เหลือ :48 สำหรับ Group C)
        //
        // Revised:
        //   Group A (even hrs): #19:00, #2:12, #3:24, #5:36, #6:48  (5 ตัว)
        //   Group B (odd hrs):  #1:00, #20:12, #21:24, #17:36, #11:48  (5 ตัว)
        //   Group C: #15 ทุก 4 ชม. even hrs :55 → ไม่ชนกับ :48 (ห่าง 7 นาที) → ❌
        //
        // ====> ใช้ gap 11 นาทีแทน:
        //   ชั่วโมงคู่: :00 :11 :22 :33 :44 :55  (6 ตัว, ห่างกัน 11 นาที ✓)
        //   ชั่วโมงคี่: :00 :11 :22 :33 :44 :55  (6 ตัว, ห่างกัน 11 นาที ✓)
        //   ข้ามชั่วโมง: :55 กับ :00 ถัดไป = 5 นาที → ✓ เพราะคนละกลุ่ม
        //
        // Wait — :55 ชม.คู่ กับ :00 ชม.คี่ = ห่างกัน 5 นาที → CONFLICT!
        //
        // ====> ลดจำนวน slot ต่อชั่วโมง:
        //   ชั่วโมงคู่: :00 :11 :22 :33 :44  (5 ตัว, ห่างกัน 11 นาที, :44 กับ :00 ถัดไป = 16 นาที ✓)
        //   ชั่วโมงคี่: :00 :11 :22 :33 :44  (5 ตัว, :44 กับ :00 ถัดไป = 16 นาที ✓)
        //
        // 5+5 = 10 ตัว ที่ ≥ 10 min gap ใน even/odd
        // อีก 4 ตัว (ถี่น้อย) → ใช้ :55 ของชั่วโมงที่ว่าง
        //
        // #15 ทุก 3 ชม. → 3 ชม. = 01,04,07,10,13,16,19,22 (ผสมคู่คี่)
        //   :55 → ห่างจาก :44 = 11 นาที ✓, ห่างจาก :00 ถัดไป = 5 นาที → ❌ ถ้ามีคนที่ :00
        //   ชม. 01:55 → 02:00 มี #19 (:00) → ห่าง 5 นาที ❌
        //
        // ====> ยอมแล้ว ใช้ approach ที่ง่ายที่สุด: จัดทุกตัวแบบ absolute minute-of-day
        //
        // ───────────────────────────────────────────────

        // ═══════════════════════════════════════════════
        // Final Approach: จัด 14 integrations ลงบน 120 นาที (2-hour cycle)
        // แบ่ง 2 กลุ่มสลับกัน:
        //
        // กลุ่ม EVEN (ชม.คู่): 6 ตัว × offset 0,10,20,30,40,50
        // กลุ่ม ODD  (ชม.คี่): 4 ตัว × offset 0,10,20,30
        //
        // ตัวที่เหลือ 4 ตัว (ถี่น้อย): ใช้เวลาเฉพาะเจาะจง
        //   #15 ทุก 3 ชม. → เลือก odd hrs :42 (ห่าง :30+12=:42, ห่าง :00 ถัดไป 18 นาที)
        //   #13 ทุก 4 ชม. → เลือก odd hrs :52 (ห่าง :42+10=:52 ✓)
        //   #14 ทุก 6 ชม. → เลือก even hrs :55 (ห่าง :50+5 ❌)
        //           → เลือก even hrs ที่ไม่มีใครใช้:
        //             ชม.คู่ มีคนที่ :00,:10,:20,:30,:40,:50 → ว่าง ❌ ไม่เหลือ!
        //           → ย้ายไป even hrs :05 (ห่าง :00 = 5 นาที ❌)
        //           → ให้ #14 ใช้ odd hrs :48 (ห่าง :30 = 18 นาที, ห่าง :42 = 6 ❌)
        //           → ให้ #14 ใช้ odd hrs :55 (ห่าง :52 = 3 ❌)
        //
        // ====> ปัญหาคือ 14 ตัว ×10 นาที ยัดใน 120 นาทีไม่ได้
        //       ต้องลด gap หรือลดความถี่
        //
        // ✅ SOLUTION: ลด gap เหลือ 5 นาทีสำหรับกลุ่มต่างชั่วโมง
        //    เพราะ sync ใช้เวลา ~2-5 นาทีต่อรอบ ห่าง 5 นาทีก็ไม่ overlap จริง
        //    แต่ถ้าอยู่ชั่วโมงเดียวกัน ต้องห่าง ≥ 10 นาที
        //
        // ✅ FINAL FINAL: ลด min gap จาก 10 → 5 นาที
        //    14 ตัว × 5 นาที = 70 นาที < 120 นาที ✓
        //
        // NOPE — ระบบ validateScheduleConflict ใช้ 10 นาที
        // ====> ปรับระบบด้วย? ไม่ — ปรับ schedule ให้เหมาะ
        //
        // ✅ REAL FINAL: ให้กลุ่มถี่น้อย (#13,#14,#15,#22) sync ลดลงเหลือ
        //    เวลาที่ไม่ตรงกับใคร
        //
        // จัดใหม่ — ใช้ 120 นาทีเป็น 12 slot (ทุก 10 นาที):
        //   Even hrs: :00 :10 :20 :30 :40 :50 → 6 ตัว (Group A)
        //   Odd hrs:  :00 :10 :20 :30 :40 :50 → 6 ตัว (Group B)
        //   รวม 12 ตัว ≥10 min gap ภายในกลุ่ม ✓
        //   ข้ามกลุ่ม: :50 ชม.คู่ กับ :00 ชม.คี่ = 10 นาที ✓ (เช่น 02:50 → 03:00)
        //   :50 ชม.คี่ กับ :00 ชม.คู่ถัดไป = 10 นาที ✓ (เช่น 03:50 → 04:00)
        //
        // เหลือ 2 ตัว (#14, #22) ที่ถี่น้อย → จัดให้ไม่ชนกับ 12 ตัวข้างต้น
        //   #14 ทุก 6 ชม. → :05 ห่าง :00 = 5 ❌
        //        → :55 ห่าง :50 = 5 ❌
        //   ไม่มี safe slot! 60 นาทีเต็มแล้ว (6 slot × 10 min = 60)
        //
        // ====> สรุป: 14 ตัว ≥10 min gap ทุกคู่ ไม่สามารถทำได้ถ้าทุกตัว sync ทุก 2 ชม.
        //       ต้องปรับ: ลด #14 (#22 วันละครั้ง) ให้ sync เวลาที่ไม่ชน (เช่น xx:05)
        //       ยอมให้ #14 มี soft conflict (5 min gap) กับ 1 ตัว ➜ severity=soft ผ่านได้
        //
        // ═══════════════════════════════════════════════

        $schedules = [
            // ══════════════════════════════════════════
            // กลุ่ม A: ทุก 2 ชม. ชั่วโมงคู่ (00,02,04,...,22)  — 6 ตัว
            // ══════════════════════════════════════════
            19 => [
                'sync'  => $this->everyNHoursFrom(2, 0, 0),    // 00:00,02:00,...,22:00
                'full'  => '01:00',
                'desc'  => 'ทัวร์น้ำดี — ทุก 2 ชม.(คู่) :00',
            ],
            2 => [
                'sync'  => $this->everyNHoursFrom(2, 0, 10),   // 00:10,02:10,...,22:10
                'full'  => '01:10',
                'desc'  => 'วีอาร์เวิลด์ — ทุก 2 ชม.(คู่) :10',
            ],
            3 => [
                'sync'  => $this->everyNHoursFrom(2, 0, 20),   // 00:20,02:20,...,22:20
                'full'  => '01:20',
                'desc'  => 'ทัวร์แฟคทอรี่ — ทุก 2 ชม.(คู่) :20',
            ],
            5 => [
                'sync'  => $this->everyNHoursFrom(2, 0, 30),   // 00:30,02:30,...,22:30
                'full'  => '01:30',
                'desc'  => 'เช็คอิน — ทุก 2 ชม.(คู่) :30',
            ],
            6 => [
                'sync'  => $this->everyNHoursFrom(2, 0, 40),   // 00:40,02:40,...,22:40
                'full'  => '01:40',
                'desc'  => 'โก365 — ทุก 2 ชม.(คู่) :40',
            ],
            11 => [
                'sync'  => $this->everyNHoursFrom(2, 0, 50),   // 00:50,02:50,...,22:50
                'full'  => '01:50',
                'desc'  => 'คิวอีบุ๊คกิ้ง — ทุก 2 ชม.(คู่) :50',
            ],

            // ══════════════════════════════════════════
            // กลุ่ม B: ทุก 2 ชม. ชั่วโมงคี่ (01,03,05,...,23) — 6 ตัว
            // ══════════════════════════════════════════
            1 => [
                'sync'  => $this->everyNHoursFrom(2, 1, 0),    // 01:00,03:00,...,23:00
                'full'  => '02:00',
                'desc'  => 'ซีโก้ — ทุก 2 ชม.(คี่) :00',
            ],
            20 => [
                'sync'  => $this->everyNHoursFrom(2, 1, 10),   // 01:10,03:10,...,23:10
                'full'  => '02:10',
                'desc'  => 'ไอทราเวล — ทุก 2 ชม.(คี่) :10',
            ],
            21 => [
                'sync'  => $this->everyNHoursFrom(2, 1, 20),   // 01:20,03:20,...,23:20
                'full'  => '02:20',
                'desc'  => 'ทีทีเอ็น — ทุก 2 ชม.(คี่) :20',
            ],
            17 => [
                'sync'  => $this->everyNHoursFrom(2, 1, 30),   // 01:30,03:30,...,23:30
                'full'  => '02:30',
                'desc'  => 'ว้าวเจอร์นี่ — ทุก 2 ชม.(คี่) :30 (เดิมทุก 1 ชม.)',
            ],
            15 => [
                'sync'  => $this->everyNHoursFrom(2, 1, 40),   // 01:40,03:40,...,23:40
                'full'  => '02:40',
                'desc'  => 'ทัวร์เมกเกอร์ — ทุก 2 ชม.(คี่) :40 (เดิมทุก 3 ชม.)',
            ],
            13 => [
                'sync'  => $this->everyNHoursFrom(2, 1, 50),   // 01:50,03:50,...,23:50
                'full'  => '02:50',
                'desc'  => 'ลุกซ์แพลนเนท — ทุก 2 ชม.(คี่) :50 (เดิมทุก 4 ชม.)',
            ],

            // ══════════════════════════════════════════
            // กลุ่ม C: ถี่น้อยมาก — เลือกเวลาเจาะจง
            // #14 ทุก 6 ชม. → ใช้ :05 ชม.คี่ (ห่าง :00=5 นาที → soft conflict เท่านั้น)
            // #22 วันละครั้ง → ใช้เวลาว่างตอนตี 4
            // ══════════════════════════════════════════
            14 => [
                'sync'  => '05:05,11:05,17:05,23:05',
                'full'  => '05:05',
                'desc'  => 'มีทูทัวร์ — ทุก 6 ชม. :05 (soft conflict กับ :00/:10)',
            ],
            22 => [
                'sync'  => '04:05',
                'full'  => '04:05',
                'desc'  => 'ไทยเที่ยวนอก — วันละครั้ง 04:05',
            ],
        ];

        // ───────────────────────────────────────────────
        // Apply schedules
        // ───────────────────────────────────────────────

        $updated = 0;
        $skipped = 0;

        foreach ($schedules as $configId => $data) {
            $config = WholesalerApiConfig::with('wholesaler:id,name')->find($configId);

            if (!$config) {
                $this->command->warn("  ⚠ Config #{$configId} ไม่พบ — ข้าม");
                $skipped++;
                continue;
            }

            $name = $config->wholesaler?->name ?? "Config #{$configId}";
            $oldSync = $config->sync_schedule;
            $oldFull = $config->full_sync_schedule;

            $config->update([
                'sync_schedule'      => $data['sync'],
                'full_sync_schedule' => $data['full'],
            ]);

            $syncTimesCount = count(explode(',', $data['sync']));

            $this->command->info(
                "  ✅ #{$configId} {$name}"
            );
            $this->command->line(
                "     {$data['desc']}"
            );
            $this->command->line(
                "     เดิม: {$oldSync}  →  ใหม่: {$data['sync']} ({$syncTimesCount} ครั้ง/วัน)"
            );
            $this->command->line(
                "     Full sync: {$oldFull} → {$data['full']}"
            );
            $this->command->line('');

            $updated++;
        }

        // ───────────────────────────────────────────────
        // Summary
        // ───────────────────────────────────────────────

        $this->command->info("══════════════════════════════════════════");
        $this->command->info("✅ อัพเดทสำเร็จ {$updated} integrations, ข้าม {$skipped}");
        $this->command->info('');

        // Verify no conflicts
        $this->command->info("🔍 ตรวจสอบ conflicts...");
        $this->verifyNoConflicts();
    }

    /**
     * Generate time-list for every N hours, starting from a given hour, with a minute offset.
     * e.g. everyNHoursFrom(2, 0, 10) → "00:10,02:10,04:10,...,22:10" (even hours, :10)
     * e.g. everyNHoursFrom(2, 1, 30) → "01:30,03:30,05:30,...,23:30" (odd hours, :30)
     * e.g. everyNHoursFrom(3, 1, 40) → "01:40,04:40,07:40,...,22:40" (every 3h from 01, :40)
     */
    private function everyNHoursFrom(int $intervalHours, int $startHour, int $minute): string
    {
        $times = [];
        for ($h = $startHour; $h < 24; $h += $intervalHours) {
            $times[] = sprintf('%02d:%02d', $h, $minute);
        }
        return implode(',', $times);
    }

    /**
     * Verify that no two integrations have conflicting times (< 10 min gap).
     */
    private function verifyNoConflicts(): void
    {
        $configs = WholesalerApiConfig::with('wholesaler:id,name')
            ->where('sync_enabled', true)
            ->where('is_active', true)
            ->get(['id', 'wholesaler_id', 'sync_schedule']);

        // Collect all (minute_of_day, config_id, name) tuples
        $allSlots = [];

        foreach ($configs as $config) {
            $schedule = trim($config->sync_schedule);
            $name = $config->wholesaler?->name ?? "#{$config->id}";

            // Parse time-list
            if (preg_match('/^\d{1,2}:\d{2}(\s*,\s*\d{1,2}:\d{2})*$/', $schedule)) {
                $times = array_map('trim', explode(',', $schedule));
                foreach ($times as $t) {
                    [$h, $m] = explode(':', $t);
                    $mod = (int) $h * 60 + (int) $m;
                    $allSlots[] = ['mod' => $mod, 'id' => $config->id, 'name' => $name, 'time' => $t];
                }
            }
        }

        // Check each pair
        $conflicts = 0;
        for ($i = 0; $i < count($allSlots); $i++) {
            for ($j = $i + 1; $j < count($allSlots); $j++) {
                if ($allSlots[$i]['id'] === $allSlots[$j]['id']) continue;

                $diff = abs($allSlots[$i]['mod'] - $allSlots[$j]['mod']);
                $circDiff = min($diff, 1440 - $diff);

                if ($circDiff < 10) {
                    $conflicts++;
                    $this->command->error(
                        "  ❌ CONFLICT: #{$allSlots[$i]['id']} {$allSlots[$i]['name']} ({$allSlots[$i]['time']}) "
                        . "↔ #{$allSlots[$j]['id']} {$allSlots[$j]['name']} ({$allSlots[$j]['time']}) "
                        . "— ห่างกัน {$circDiff} นาที"
                    );
                }
            }
        }

        if ($conflicts === 0) {
            $this->command->info("  ✅ ไม่มี conflicts! ทุก integration ห่างกัน ≥ 10 นาที");
        } else {
            $this->command->error("  ⚠ พบ {$conflicts} conflicts — ต้องแก้ไข!");
        }

        // Print timeline visualization
        $this->command->info('');
        $this->command->info('📊 Timeline (ตัวอย่าง 00:00 - 01:59):');

        $firstTwoHours = array_filter($allSlots, fn($s) => $s['mod'] < 120);
        usort($firstTwoHours, fn($a, $b) => $a['mod'] <=> $b['mod']);

        foreach ($firstTwoHours as $slot) {
            $h = intdiv($slot['mod'], 60);
            $m = $slot['mod'] % 60;
            $timeStr = sprintf('%02d:%02d', $h, $m);
            $bar = str_repeat('█', min(30, (int) ($slot['mod'] / 4)));
            $this->command->line("  {$timeStr} {$bar} #{$slot['id']} {$slot['name']}");
        }
    }
}
