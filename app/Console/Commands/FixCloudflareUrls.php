<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCloudflareUrls extends Command
{
    protected $signature = 'images:fix-urls';
    protected $description = 'แก้ไข Cloudflare Image URLs ให้ใช้ Account Hash ที่ถูกต้อง';

    public function handle(): int
    {
        $oldHash = 'fc0fd33fbddb3f62463bf5bf87c9e50e';
        $newHash = config('services.cloudflare.account_hash');

        if (!$newHash) {
            $this->error('CLOUDFLARE_ACCOUNT_HASH not set in .env');
            return Command::FAILURE;
        }

        $this->info("Replacing: {$oldHash} → {$newHash}");

        $affected = DB::table('transports')
            ->whereNotNull('image')
            ->where('image', 'like', "%{$oldHash}%")
            ->update([
                'image' => DB::raw("REPLACE(image, '{$oldHash}', '{$newHash}')")
            ]);

        $this->info("Updated {$affected} records");

        return Command::SUCCESS;
    }
}
