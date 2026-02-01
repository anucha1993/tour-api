<?php

namespace App\Console\Commands;

use App\Models\GalleryImage;
use Illuminate\Console\Command;

class FixGalleryUrls extends Command
{
    protected $signature = 'gallery:fix-urls';
    protected $description = 'Fix gallery image URLs to use correct variants';

    public function handle()
    {
        $images = GalleryImage::all();
        $count = 0;
        
        foreach ($images as $img) {
            // Extract cloudflare_id and rebuild URLs correctly
            $cloudflareId = $img->cloudflare_id;
            $accountHash = config('services.cloudflare.images_account_hash', 'yixdo-GXTcyjkoSkBzfBcA');
            
            // Both use public variant (flexible variants not enabled)
            $img->url = "https://imagedelivery.net/{$accountHash}/{$cloudflareId}/public";
            $img->thumbnail_url = "https://imagedelivery.net/{$accountHash}/{$cloudflareId}/public";
            
            $img->save();
            $count++;
        }
        
        $this->info("Fixed {$count} images");
        return 0;
    }
}
