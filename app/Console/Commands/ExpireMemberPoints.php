<?php

namespace App\Console\Commands;

use App\Services\PointService;
use Illuminate\Console\Command;

class ExpireMemberPoints extends Command
{
    protected $signature = 'points:expire';
    protected $description = 'Expire member points that have passed their expiry date';

    public function handle(PointService $pointService): int
    {
        $this->info('Checking for expired points...');

        $count = $pointService->expirePoints();

        $this->info("Expired {$count} point transactions.");

        return self::SUCCESS;
    }
}
