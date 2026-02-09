<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Services\SlugService;
use Illuminate\Console\Command;

class GenerateTourSlugs extends Command
{
    protected $signature = 'tours:generate-slugs 
                            {--dry-run : แสดงผลลัพธ์โดยไม่บันทึก}
                            {--force : อัพเดท slug ทุกตัว รวมถึงที่มีอยู่แล้ว}
                            {--active-only : เฉพาะทัวร์ที่สถานะ active}';

    protected $description = 'Generate slug สำหรับทัวร์ที่ยังไม่มี slug (แปลไทย→อังกฤษอัตโนมัติ)';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');
        $activeOnly = $this->option('active-only');

        $query = Tour::query();
        
        if (!$isForce) {
            $query->where(function ($q) {
                $q->whereNull('slug')->orWhere('slug', '');
            });
        }

        if ($activeOnly) {
            $query->where('status', 'active');
        }

        $tours = $query->get();

        if ($tours->isEmpty()) {
            $this->info('✅ ไม่มีทัวร์ที่ต้อง generate slug');
            return 0;
        }

        $this->info("พบ {$tours->count()} ทัวร์ที่ต้อง generate slug");
        
        if ($isDryRun) {
            $this->warn('🔍 [DRY RUN] จะไม่บันทึกลงฐานข้อมูล');
        }

        $updated = 0;

        foreach ($tours as $tour) {
            $title = $tour->title;

            if (empty($title)) {
                $this->warn("  ⚠ Tour #{$tour->id} ({$tour->tour_code}) - ไม่มี title, ใช้ tour_code แทน");
                $title = $tour->tour_code ?: "tour-{$tour->id}";
            }

            $slug = SlugService::generateSlug($title, $tour->id, $tour->tour_code);

            $oldSlug = $tour->slug;

            $this->line(
                "  #{$tour->id} [{$tour->tour_code}] " .
                ($oldSlug ? "'{$oldSlug}' → " : '') .
                "<info>{$slug}</info>"
            );

            if (!$isDryRun) {
                $tour->update(['slug' => $slug]);
                $updated++;
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->info("🔍 [DRY RUN] จะอัพเดท {$tours->count()} ทัวร์");
        } else {
            $this->info("✅ อัพเดท slug สำเร็จ {$updated} ทัวร์");
        }

        return 0;
    }
}
