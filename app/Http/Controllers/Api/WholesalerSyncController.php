<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Offer;
use App\Models\Period;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\Transport;
use App\Models\Wholesaler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WholesalerSyncController extends Controller
{
    /**
     * Sync a single tour from frontend (pre-mapped data)
     * 
     * Frontend sends data that's already mapped via the mapping UI.
     * Backend only needs to:
     * 1. Resolve lookups (country code → ID, transport code → ID)
     * 2. Save to database
     */
    public function syncTour(Request $request, Wholesaler $wholesaler): JsonResponse
    {
        $request->validate([
            'tour' => 'required|array',
            'tour.external_id' => 'required',
            'departure' => 'nullable|array',
            'itinerary' => 'nullable|array',
            'content' => 'nullable|array',
            'media' => 'nullable|array',
            'seo' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $tourData = $request->input('tour', []);
            $departureData = $request->input('departure', []);
            $itineraryData = $request->input('itinerary', []);
            $contentData = $request->input('content', []);
            $mediaData = $request->input('media', []);
            $seoData = $request->input('seo', []);

            // Merge all sections for tour
            $mergedTourData = array_merge($tourData, $contentData, $mediaData, $seoData);

            // Resolve lookups
            $mergedTourData = $this->resolveLookups($mergedTourData);

            // Find or create tour
            $tour = Tour::where('wholesaler_id', $wholesaler->id)
                ->where('external_id', $tourData['external_id'])
                ->first();

            $isNew = !$tour;

            if ($isNew) {
                $tour = new Tour();
                $tour->wholesaler_id = $wholesaler->id;
                $tour->external_id = $tourData['external_id'];
                $tour->tour_code = $this->generateTourCode();
            }

            // Fill tour data
            $tour->fill($this->filterFillable($tour, $mergedTourData));
            $tour->save();

            // Process departures/periods
            $periodsResult = $this->processDepartures($tour, $departureData);

            // Process itineraries
            $itinerariesResult = $this->processItineraries($tour, $itineraryData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isNew ? 'สร้างทัวร์สำเร็จ' : 'อัปเดตทัวร์สำเร็จ',
                'data' => [
                    'tour_id' => $tour->id,
                    'tour_code' => $tour->tour_code,
                    'external_id' => $tour->external_id,
                    'is_new' => $isNew,
                    'periods' => $periodsResult,
                    'itineraries' => $itinerariesResult,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sync tour failed', [
                'wholesaler_id' => $wholesaler->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync multiple tours from frontend (batch)
     */
    public function syncTours(Request $request, Wholesaler $wholesaler): JsonResponse
    {
        $request->validate([
            'tours' => 'required|array|min:1',
            'tours.*.tour' => 'required|array',
            'tours.*.tour.external_id' => 'required',
        ]);

        $results = [
            'total' => count($request->tours),
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($request->tours as $index => $tourPayload) {
            try {
                DB::beginTransaction();

                $tourData = $tourPayload['tour'] ?? [];
                $departureData = $tourPayload['departure'] ?? [];
                $itineraryData = $tourPayload['itinerary'] ?? [];
                $contentData = $tourPayload['content'] ?? [];
                $mediaData = $tourPayload['media'] ?? [];
                $seoData = $tourPayload['seo'] ?? [];

                // Merge all sections for tour
                $mergedTourData = array_merge($tourData, $contentData, $mediaData, $seoData);

                // Resolve lookups
                $mergedTourData = $this->resolveLookups($mergedTourData);

                // Find or create tour
                $tour = Tour::where('wholesaler_id', $wholesaler->id)
                    ->where('external_id', $tourData['external_id'])
                    ->first();

                $isNew = !$tour;

                if ($isNew) {
                    $tour = new Tour();
                    $tour->wholesaler_id = $wholesaler->id;
                    $tour->external_id = $tourData['external_id'];
                    $tour->tour_code = $this->generateTourCode();
                }

                // Fill tour data
                $tour->fill($this->filterFillable($tour, $mergedTourData));
                $tour->save();

                // Process departures/periods
                $this->processDepartures($tour, $departureData);

                // Process itineraries
                $this->processItineraries($tour, $itineraryData);

                DB::commit();

                $results['success']++;
                $results['details'][] = [
                    'index' => $index,
                    'external_id' => $tourData['external_id'],
                    'tour_id' => $tour->id,
                    'status' => 'success',
                    'is_new' => $isNew,
                ];

            } catch (\Exception $e) {
                DB::rollBack();

                $results['failed']++;
                $results['details'][] = [
                    'index' => $index,
                    'external_id' => $tourPayload['tour']['external_id'] ?? 'unknown',
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => $results['failed'] === 0,
            'message' => "Synced {$results['success']}/{$results['total']} tours",
            'data' => $results,
        ]);
    }

    /**
     * Resolve lookup fields (country code → ID, transport code → ID)
     */
    protected function resolveLookups(array $data): array
    {
        // Resolve country
        if (isset($data['primary_country_id']) && !is_numeric($data['primary_country_id'])) {
            $country = Country::where('iso2', $data['primary_country_id'])
                ->orWhere('iso3', $data['primary_country_id'])
                ->orWhere('name', $data['primary_country_id'])
                ->first();
            $data['primary_country_id'] = $country?->id;
        }

        // Resolve transport
        if (isset($data['transport_id']) && !is_numeric($data['transport_id'])) {
            $transport = Transport::where('code', $data['transport_id'])
                ->orWhere('name', $data['transport_id'])
                ->first();
            $data['transport_id'] = $transport?->id;
        }

        return $data;
    }

    /**
     * Filter data to only include fillable fields
     */
    protected function filterFillable($model, array $data): array
    {
        $fillable = $model->getFillable();
        $filtered = [];

        foreach ($data as $key => $value) {
            // Skip null values
            if ($value === null) continue;
            
            // Convert empty strings to null for numeric fields
            if ($value === '' && in_array($key, ['hotel_star', 'duration_days', 'duration_nights', 'primary_country_id', 'transport_id'])) {
                continue;
            }

            if (in_array($key, $fillable) || empty($fillable)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Process departures/periods
     */
    protected function processDepartures(Tour $tour, array $departures): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped_past' => 0];
        
        // Get sync settings
        $syncSettings = Setting::get('sync_settings', [
            'skip_past_periods' => true,
            'past_period_threshold_days' => 0,
        ]);
        
        $skipPastPeriods = $syncSettings['skip_past_periods'] ?? true;
        $thresholdDays = $syncSettings['past_period_threshold_days'] ?? 0;
        $thresholdDate = now()->subDays($thresholdDays)->toDateString();

        foreach ($departures as $dep) {
            $departureDate = $dep['departure_date'] ?? $dep['start_date'] ?? null;
            if (!$departureDate) continue;
            
            // Skip past periods if enabled
            if ($skipPastPeriods && $departureDate < $thresholdDate) {
                $result['skipped_past']++;
                continue;
            }

            $externalId = $dep['external_id'] ?? null;

            // Find existing period
            $period = Period::where('tour_id', $tour->id)
                ->where(function ($q) use ($externalId, $departureDate) {
                    if ($externalId) {
                        $q->where('external_id', $externalId)
                          ->where('start_date', $departureDate);
                    } else {
                        $q->where('start_date', $departureDate);
                    }
                })
                ->first();

            $isNew = !$period;

            if ($isNew) {
                $period = new Period();
                $period->tour_id = $tour->id;
                $period->period_code = 'P' . date('ymd', strtotime($departureDate));
            }

            // Map departure fields to period fields
            $periodData = [
                'external_id' => $externalId,
                'start_date' => $departureDate,
                'end_date' => $dep['return_date'] ?? $dep['end_date'] ?? null,
                'capacity' => max(0, intval($dep['capacity'] ?? 0)),
                'available' => max(0, intval($dep['available'] ?? $dep['capacity'] ?? 0)),
                'status' => $this->mapPeriodStatus($dep['status'] ?? 'open'),
            ];

            $period->fill($this->filterFillable($period, $periodData));
            $period->save();

            // Create/update offer for pricing
            if (isset($dep['price_adult'])) {
                $this->processOffer($period, $dep);
            }

            $isNew ? $result['created']++ : $result['updated']++;
        }

        return $result;
    }

    /**
     * Process offer/pricing for a period
     */
    protected function processOffer(Period $period, array $depData): void
    {
        $offer = Offer::firstOrNew(['period_id' => $period->id]);

        $offerData = [
            'price_adult' => $depData['price_adult'] ?? null,
            'price_child' => $depData['price_child'] ?? null,
            'price_child_no_bed' => $depData['price_child_nobed'] ?? $depData['price_child_no_bed'] ?? null,
            'price_infant' => $depData['price_infant'] ?? null,
            'price_single_supplement' => $depData['price_single_surcharge'] ?? $depData['price_single_supplement'] ?? null,
            'currency' => $depData['currency'] ?? 'THB',
        ];

        $offer->fill($this->filterFillable($offer, $offerData));
        $offer->save();
    }

    /**
     * Process itineraries
     */
    protected function processItineraries(Tour $tour, array $itineraries): array
    {
        $result = ['created' => 0, 'updated' => 0];

        foreach ($itineraries as $itin) {
            $dayNumber = $itin['day_number'] ?? null;
            if (!$dayNumber) continue;

            $externalId = $itin['external_id'] ?? null;

            // Find existing itinerary
            $itinerary = TourItinerary::where('tour_id', $tour->id)
                ->where(function ($q) use ($externalId, $dayNumber) {
                    if ($externalId) {
                        $q->where('external_id', $externalId);
                    } else {
                        $q->where('day_number', $dayNumber);
                    }
                })
                ->first();

            $isNew = !$itinerary;

            if ($isNew) {
                $itinerary = new TourItinerary();
                $itinerary->tour_id = $tour->id;
            }

            // Map fields - handle boolean conversion
            $itinData = [
                'external_id' => $externalId,
                'day_number' => $dayNumber,
                'title' => $itin['title'] ?? null,
                'description' => $itin['description'] ?? null,
                'places' => $itin['places'] ?? null,
                'accommodation' => $itin['accommodation'] ?? null,
                'hotel_star' => $itin['hotel_star'] ?? null,
                'has_breakfast' => $this->toBoolean($itin['has_breakfast'] ?? null),
                'has_lunch' => $this->toBoolean($itin['has_lunch'] ?? null),
                'has_dinner' => $this->toBoolean($itin['has_dinner'] ?? null),
            ];

            $itinerary->fill($this->filterFillable($itinerary, $itinData));
            $itinerary->save();

            $isNew ? $result['created']++ : $result['updated']++;
        }

        return $result;
    }

    /**
     * Convert various boolean representations to 1/0
     */
    protected function toBoolean($value): ?int
    {
        if ($value === null) return null;
        if (is_bool($value)) return $value ? 1 : 0;
        if (is_numeric($value)) return intval($value) ? 1 : 0;
        if (is_string($value)) {
            $v = strtoupper(trim($value));
            if (in_array($v, ['Y', 'YES', 'TRUE', '1', 'P'])) return 1;
            if (in_array($v, ['N', 'NO', 'FALSE', '0', ''])) return 0;
        }
        return null;
    }

    /**
     * Map period status
     */
    protected function mapPeriodStatus(string $status): string
    {
        $statusMap = [
            'open' => 'available',
            'available' => 'available',
            'closed' => 'closed',
            'full' => 'sold_out',
            'sold_out' => 'sold_out',
            'guaranteed' => 'guaranteed',
        ];

        return $statusMap[strtolower($status)] ?? 'available';
    }

    /**
     * Generate unique tour code
     */
    protected function generateTourCode(): string
    {
        $prefix = 'T';
        $date = now()->format('ymd');
        $lastTour = Tour::where('tour_code', 'like', "{$prefix}{$date}%")
            ->orderBy('tour_code', 'desc')
            ->first();

        if ($lastTour && preg_match('/(\d+)$/', $lastTour->tour_code, $matches)) {
            $seq = intval($matches[1]) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . $date . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
