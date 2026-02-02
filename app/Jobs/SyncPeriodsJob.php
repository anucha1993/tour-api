<?php

namespace App\Jobs;

use App\Models\Tour;
use App\Models\Period;
use App\Models\Offer;
use App\Models\Setting;
use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use App\Models\SyncLog;
use App\Services\WholesalerAdapters\AdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SyncPeriodsJob - Phase 2 of Two-Phase Sync
 * 
 * ดึงข้อมูลรอบเดินทาง (periods/schedules) จาก API แยก
 * ใช้สำหรับ Wholesaler ที่แยก endpoint ระหว่าง tours และ periods
 * 
 * @see docs/api/two-phase-sync.md
 */
class SyncPeriodsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    protected int $tourId;
    protected string $externalId;
    protected int $wholesalerId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $tourId, string $externalId, int $wholesalerId)
    {
        $this->tourId = $tourId;
        $this->externalId = $externalId;
        $this->wholesalerId = $wholesalerId;
        $this->onQueue('periods');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SyncPeriodsJob: Starting', [
            'tour_id' => $this->tourId,
            'external_id' => $this->externalId,
            'wholesaler_id' => $this->wholesalerId,
        ]);

        $tour = Tour::find($this->tourId);
        if (!$tour) {
            Log::warning('SyncPeriodsJob: Tour not found', ['tour_id' => $this->tourId]);
            return;
        }

        $config = WholesalerApiConfig::where('wholesaler_id', $this->wholesalerId)->first();
        if (!$config) {
            Log::warning('SyncPeriodsJob: Config not found', ['wholesaler_id' => $this->wholesalerId]);
            return;
        }

        try {
            // Get periods endpoint from auth_credentials
            $credentials = $config->auth_credentials ?? [];
            $endpoints = $credentials['endpoints'] ?? [];
            $periodsEndpoint = $endpoints['periods'] ?? null;

            if (!$periodsEndpoint) {
                Log::warning('SyncPeriodsJob: No periods endpoint configured', [
                    'wholesaler_id' => $this->wholesalerId,
                ]);
                return;
            }

            // Build URL - replace placeholders
            $url = str_replace(
                ['{external_id}', '{tour_code}', '{wholesaler_tour_code}'],
                [$this->externalId, $tour->tour_code, $tour->wholesaler_tour_code ?? ''],
                $periodsEndpoint
            );

            // Fetch periods from API
            $adapter = AdapterFactory::create($this->wholesalerId);
            $result = $adapter->fetchPeriods($url);

            if (!$result->success) {
                Log::error('SyncPeriodsJob: Failed to fetch periods', [
                    'tour_id' => $this->tourId,
                    'error' => $result->errorMessage,
                ]);
                return;
            }

            // Get field mappings for periods
            $mappings = WholesalerFieldMapping::where('wholesaler_id', $this->wholesalerId)
                ->where('section_name', 'departure')
                ->where('is_active', true)
                ->get();

            // Process each period
            $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
            
            foreach ($result->periods as $rawPeriod) {
                try {
                    $periodData = $this->transformPeriodData($rawPeriod, $mappings);
                    $this->syncPeriod($tour, $periodData, $stats);
                } catch (\Exception $e) {
                    Log::error('SyncPeriodsJob: Error processing period', [
                        'tour_id' => $this->tourId,
                        'error' => $e->getMessage(),
                    ]);
                    $stats['skipped']++;
                }
            }

            // Update tour's aggregated fields
            $this->updateTourAggregates($tour);

            Log::info('SyncPeriodsJob: Completed', [
                'tour_id' => $this->tourId,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('SyncPeriodsJob: Failed', [
                'tour_id' => $this->tourId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Transform raw period data using field mappings
     */
    protected function transformPeriodData(array $rawPeriod, $mappings): array
    {
        $result = [];

        foreach ($mappings as $mapping) {
            $fieldPath = $mapping->their_field_path ?? $mapping->their_field ?? null;
            if (empty($fieldPath)) {
                continue;
            }
            
            $value = $this->extractValue($rawPeriod, $fieldPath);
            
            if ($value !== null) {
                $value = $this->applyTransform($value, $mapping->transform_type, $mapping->transform_config);
            }

            $result[$mapping->our_field] = $value;
        }

        return $result;
    }

    /**
     * Extract value from raw data using field path
     */
    protected function extractValue(array $data, string $fieldPath): mixed
    {
        // Strip array prefix like "periods[]." since we're already iterating over the array
        $fieldPath = preg_replace('/^[Pp]eriods\[\]\./', '', $fieldPath);
        $fieldPath = preg_replace('/^[Ss]chedules\[\]\./', '', $fieldPath);
        $fieldPath = preg_replace('/^[Dd]epartures\[\]\./', '', $fieldPath);
        
        $parts = explode('.', $fieldPath);
        $value = $data;

        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Apply transformation to value
     */
    protected function applyTransform(mixed $value, ?string $type, ?array $config): mixed
    {
        if (!$type || $type === 'direct') {
            return $value;
        }

        switch ($type) {
            case 'date':
                return date('Y-m-d', strtotime($value));
            case 'datetime':
                return date('Y-m-d H:i:s', strtotime($value));
            case 'number':
                return is_numeric($value) ? (float) $value : 0;
            case 'integer':
                return (int) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'map':
                $mapping = $config['mapping'] ?? [];
                return $mapping[$value] ?? $value;
            default:
                return $value;
        }
    }

    /**
     * Sync a single period
     */
    protected function syncPeriod(Tour $tour, array $data, array &$stats): void
    {
        // Map departure_date to start_date if needed
        if (!empty($data['departure_date']) && empty($data['start_date'])) {
            $data['start_date'] = $data['departure_date'];
        }
        
        // Skip if no start date
        if (empty($data['start_date'])) {
            $stats['skipped']++;
            return;
        }
        
        // Get sync settings and check for past periods
        $syncSettings = Setting::get('sync_settings', [
            'skip_past_periods' => true,
            'past_period_threshold_days' => 0,
        ]);
        
        $skipPastPeriods = $syncSettings['skip_past_periods'] ?? true;
        $thresholdDays = $syncSettings['past_period_threshold_days'] ?? 0;
        $thresholdDate = now()->subDays($thresholdDays)->toDateString();
        
        // Skip past periods if enabled
        if ($skipPastPeriods && $data['start_date'] < $thresholdDate) {
            $stats['skipped']++;
            Log::debug('SyncPeriodsJob: Skipped past period', [
                'tour_id' => $tour->id,
                'start_date' => $data['start_date'],
                'threshold_date' => $thresholdDate,
            ]);
            return;
        }

        // Generate period code if not provided
        $periodCode = $data['period_code'] ?? $data['external_id'] ?? null;
        if (!$periodCode) {
            $periodCode = $tour->tour_code . '-' . date('Ymd', strtotime($data['start_date']));
        }

        // Find or create period
        $period = Period::where('tour_id', $tour->id)
            ->where(function($q) use ($periodCode, $data) {
                $q->where('period_code', $periodCode)
                  ->orWhere('start_date', $data['start_date']);
            })
            ->first();

        $periodData = [
            'tour_id' => $tour->id,
            'external_id' => $data['external_id'] ?? null,
            'period_code' => $periodCode,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? $data['start_date'],
            'capacity' => $data['capacity'] ?? 30,
            'booked' => $data['booked'] ?? 0,
            'available' => $data['available'] ?? ($data['capacity'] ?? 30) - ($data['booked'] ?? 0),
            'status' => $this->mapPeriodStatus($data['status'] ?? null),
            'is_visible' => $data['is_visible'] ?? true,
            'sale_status' => $data['sale_status'] ?? 'available',
        ];

        if ($period) {
            $period->update($periodData);
            $stats['updated']++;
        } else {
            $period = Period::create($periodData);
            $stats['created']++;
        }

        // Sync offer (pricing)
        if (!empty($data['price_adult']) || !empty($data['price'])) {
            $this->syncOffer($period, $data);
        }
    }

    /**
     * Sync offer/pricing for period
     */
    protected function syncOffer(Period $period, array $data): void
    {
        $offerData = [
            'period_id' => $period->id,
            'tour_id' => $period->tour_id,
            'price_adult' => $data['price_adult'] ?? $data['price'] ?? 0,
            'price_child' => $data['price_child'] ?? null,
            'price_child_nobed' => $data['price_child_nobed'] ?? null,
            'price_single_surcharge' => $data['price_single_surcharge'] ?? null,
            'discount_adult' => $data['discount_adult'] ?? 0,
            'discount_child_bed' => $data['discount_child_bed'] ?? 0,
            'discount_child_nobed' => $data['discount_child_nobed'] ?? 0,
            'discount_single' => $data['discount_single'] ?? 0,
            'deposit' => $data['deposit'] ?? null,
        ];

        Offer::updateOrCreate(
            ['period_id' => $period->id],
            $offerData
        );
    }

    /**
     * Update tour's aggregated fields after syncing periods
     */
    protected function updateTourAggregates(Tour $tour): void
    {
        $periods = $tour->periods()->with('offer')->get();

        if ($periods->isEmpty()) {
            return;
        }

        $activePeriods = $periods->where('status', 'open');
        
        // Get min/max prices
        $prices = $activePeriods->map(fn($p) => $p->offer?->price_adult)->filter();
        $discountedPrices = $activePeriods->map(function($p) {
            if (!$p->offer) return null;
            $price = $p->offer->price_adult ?? 0;
            $discount = $p->offer->discount_adult ?? 0;
            return $price > 0 ? $price - $discount : null;
        })->filter();

        // Get dates
        $startDates = $activePeriods->pluck('start_date')->filter();

        $tour->update([
            'min_price' => $discountedPrices->min() ?? $prices->min(),
            'max_price' => $prices->max(),
            'display_price' => $discountedPrices->min() ?? $prices->min(),
            'next_departure_date' => $startDates->sort()->first(),
            'total_departures' => $activePeriods->count(),
            'available_seats' => $activePeriods->sum('available'),
            'has_promotion' => $activePeriods->some(fn($p) => ($p->offer?->discount_adult ?? 0) > 0),
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncPeriodsJob: Job failed permanently', [
            'tour_id' => $this->tourId,
            'external_id' => $this->externalId,
            'wholesaler_id' => $this->wholesalerId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Map status string to Period status enum
     */
    protected function mapPeriodStatus(?string $status): string
    {
        if (!$status) {
            return Period::STATUS_OPEN;
        }

        $statusMap = [
            'open' => Period::STATUS_OPEN,
            'available' => Period::STATUS_OPEN,
            'active' => Period::STATUS_OPEN,
            'closed' => Period::STATUS_CLOSED,
            'inactive' => Period::STATUS_CLOSED,
            'sold_out' => Period::STATUS_SOLD_OUT,
            'full' => Period::STATUS_SOLD_OUT,
            'cancelled' => Period::STATUS_CANCELLED,
            'canceled' => Period::STATUS_CANCELLED,
        ];

        return $statusMap[strtolower($status)] ?? Period::STATUS_OPEN;
    }
}
