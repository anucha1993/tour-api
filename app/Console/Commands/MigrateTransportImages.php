<?php

namespace App\Console\Commands;

use App\Services\CloudflareImagesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateTransportImages extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'images:migrate-transports 
                            {--dry-run : แสดงผลลัพธ์โดยไม่อัพโหลดจริง}
                            {--limit= : จำกัดจำนวน records}
                            {--base-url= : Base URL ของ images เดิม}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate transport images to Cloudflare Images (convert to webp)';

    protected CloudflareImagesService $cloudflare;
    protected string $baseUrl;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        parent::__construct();
        $this->cloudflare = $cloudflare;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->baseUrl = $this->option('base-url') ?? 'https://www.nexttripholiday.com/';
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        $this->info('🚀 Starting Transport Images Migration to Cloudflare...');
        $this->info("Base URL: {$this->baseUrl}");
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual uploads will be performed');
        }

        // ดึงข้อมูล transports ที่มี image
        $query = DB::table('transports')
            ->whereNotNull('image')
            ->where('image', '!=', '');

        if ($limit) {
            $query->limit((int) $limit);
        }

        $transports = $query->get();

        if ($transports->isEmpty()) {
            $this->info('No transport images found to migrate.');
            return Command::SUCCESS;
        }

        $this->info("Found {$transports->count()} transports with images");
        $this->newLine();

        $bar = $this->output->createProgressBar($transports->count());
        $bar->start();

        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($transports as $transport) {
            $bar->advance();

            // สร้าง full URL
            $imageUrl = $this->buildImageUrl($transport->image);

            // ใช้ format: transports/{id} เพื่อจัดระเบียบใน Cloudflare
            $customId = "transports/{$transport->id}";

            if ($dryRun) {
                $this->line("\n  Would upload: {$imageUrl} → {$customId}.webp");
                $success++;
                continue;
            }

            // ตรวจสอบว่าเคยอัพโหลดแล้วหรือไม่
            if ($this->isAlreadyMigrated($transport)) {
                $skipped++;
                continue;
            }

            // อัพโหลดไป Cloudflare
            $result = $this->cloudflare->uploadFromUrl($imageUrl, $customId, [
                'folder' => 'transports',
                'type' => 'transport',
                'transport_id' => $transport->id,
                'original_path' => $transport->image,
            ]);

            if ($result) {
                // อัพเดท database
                $this->updateTransportImage($transport->id, $result);
                $success++;
            } else {
                $failed++;
                $this->error("\n  Failed: {$transport->name} ({$imageUrl})");
            }
        }

        $bar->finish();
        $this->newLine(2);

        // สรุปผล
        $this->info('📊 Migration Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Success', $success],
                ['❌ Failed', $failed],
                ['⏭️ Skipped', $skipped],
                ['📦 Total', $transports->count()],
            ]
        );

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to perform actual migration.');
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * สร้าง full URL จาก relative path
     */
    protected function buildImageUrl(string $imagePath): string
    {
        // ถ้าเป็น full URL แล้ว return เลย
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        // ลบ / นำหน้าถ้ามี
        $imagePath = ltrim($imagePath, '/');

        return rtrim($this->baseUrl, '/') . '/' . $imagePath;
    }

    /**
     * ตรวจสอบว่า transport นี้เคย migrate แล้วหรือไม่
     */
    protected function isAlreadyMigrated($transport): bool
    {
        // ตรวจสอบว่า image path เป็น cloudflare URL แล้วหรือยัง
        return str_contains($transport->image ?? '', 'imagedelivery.net');
    }

    /**
     * อัพเดท image path ใน database
     */
    protected function updateTransportImage(int $transportId, array $cloudflareResult): void
    {
        // เก็บ cloudflare image ID
        $imageId = $cloudflareResult['id'];
        
        // สร้าง URL สำหรับแสดงผล
        $displayUrl = $this->cloudflare->getDisplayUrl($imageId);

        DB::table('transports')
            ->where('id', $transportId)
            ->update([
                'image' => $displayUrl,
                'updated_at' => now(),
            ]);
    }
}
