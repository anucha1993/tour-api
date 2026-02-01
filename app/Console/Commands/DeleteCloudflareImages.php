<?php

namespace App\Console\Commands;

use App\Services\CloudflareImagesService;
use Illuminate\Console\Command;

class DeleteCloudflareImages extends Command
{
    protected $signature = 'images:delete {--prefix= : ลบเฉพาะ images ที่ขึ้นต้นด้วย prefix นี้} {--all : ลบทั้งหมด}';
    protected $description = 'ลบ images จาก Cloudflare Images';

    public function handle(CloudflareImagesService $cloudflare): int
    {
        $prefix = $this->option('prefix');
        $all = $this->option('all');

        if (!$prefix && !$all) {
            $this->error('กรุณาระบุ --prefix=xxx หรือ --all');
            return Command::FAILURE;
        }

        $response = $cloudflare->list();

        if (!$response || !isset($response['result']['images'])) {
            $this->info('ไม่พบ images');
            return Command::SUCCESS;
        }

        $images = $response['result']['images'];
        $this->info("Found " . count($images) . " images");

        $deleted = 0;
        foreach ($images as $image) {
            $id = $image['id'];
            
            if ($all || ($prefix && str_starts_with($id, $prefix))) {
                if ($cloudflare->delete($id)) {
                    $this->line("  ✓ Deleted: {$id}");
                    $deleted++;
                } else {
                    $this->warn("  ✗ Failed: {$id}");
                }
            }
        }

        $this->newLine();
        $this->info("Deleted {$deleted} images");

        return Command::SUCCESS;
    }
}
