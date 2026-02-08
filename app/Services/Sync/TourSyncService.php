<?php

namespace App\Services\Sync;

use App\Models\Tour;
use App\Models\WholesalerApiConfig;
use App\Services\CloudflareImagesService;
use App\Services\PdfBrandingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * TourSyncService - Core tour sync logic
 * 
 * Handles:
 * - Tour creation and update
 * - Field mapping and transformation
 * - Media processing (images, PDFs)
 */
class TourSyncService
{
    protected WholesalerApiConfig $config;
    protected SyncErrorHandler $errorHandler;
    protected ?CloudflareImagesService $cloudflare = null;
    protected ?PdfBrandingService $pdfBranding = null;

    public function __construct(
        WholesalerApiConfig $config,
        SyncErrorHandler $errorHandler
    ) {
        $this->config = $config;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Set Cloudflare service for image uploads
     */
    public function setCloudflareService(CloudflareImagesService $service): self
    {
        $this->cloudflare = $service;
        return $this;
    }

    /**
     * Set PDF branding service
     */
    public function setPdfBrandingService(PdfBrandingService $service): self
    {
        $this->pdfBranding = $service;
        return $this;
    }

    /**
     * Process a single tour
     * 
     * @return array ['action' => 'created'|'updated'|'skipped', 'tour_id' => int|null]
     */
    public function processTour(array $tourData): array
    {
        $result = [
            'action' => 'skipped',
            'tour_id' => null,
            'tour_code' => null,
            'periods_created' => 0,
            'periods_updated' => 0,
        ];

        try {
            // Extract sections
            $tourSection = $tourData['tour'] ?? [];
            
            if (empty($tourSection['title']) && empty($tourSection['external_id'])) {
                Log::warning('TourSyncService: Empty tour data, skipping');
                return $result;
            }

            // Find or create tour
            $tourCode = $tourSection['tour_code'] 
                ?? $tourSection['wholesaler_tour_code'] 
                ?? $tourSection['external_id'] 
                ?? null;

            $tour = Tour::where('wholesaler_id', $this->config->wholesaler_id)
                ->where(function ($q) use ($tourCode, $tourSection) {
                    $q->where('wholesaler_tour_code', $tourCode)
                      ->orWhere('external_id', $tourSection['external_id'] ?? null);
                })
                ->first();

            $isNew = !$tour;

            if ($isNew) {
                $tour = new Tour();
                $tour->wholesaler_id = $this->config->wholesaler_id;
                $tour->wholesaler_tour_code = $tourCode;
                $tour->data_source = 'api';
                $tour->status = 'draft';
                $tour->tour_code = $this->generateTourCode();
                $result['action'] = 'created';
            } else {
                // Skip manual tours
                if ($tour->data_source === 'manual') {
                    $result['action'] = 'skipped';
                    return $result;
                }

                // Skip locked tours
                if ($tour->sync_locked) {
                    $result['action'] = 'skipped';
                    return $result;
                }

                $result['action'] = 'updated';
            }

            // Process media (before fill to get URLs)
            $this->processMedia($tourSection, $tour);

            // Fill tour fields
            $tourFields = $this->prepareTourFields($tourSection, $isNew);
            
            // Ensure tour_code is set
            if (empty($tour->tour_code) && empty($tourFields['tour_code'])) {
                $tourFields['tour_code'] = $this->generateTourCode();
            }

            $tour->fill($tourFields);
            $tour->save();

            $result['tour_id'] = $tour->id;
            $result['tour_code'] = $tour->tour_code;

            // Process related data
            if (!empty($tourData['departure'])) {
                $periodResult = $this->processPeriods($tour, $tourData['departure']);
                $result['periods_created'] = $periodResult['created'];
                $result['periods_updated'] = $periodResult['updated'];
            }

            if (!empty($tourData['itinerary'])) {
                $this->processItineraries($tour, $tourData['itinerary']);
            }

            return $result;

        } catch (\Exception $e) {
            $this->errorHandler->handle(
                $e,
                'tour',
                $tourData['tour']['external_id'] ?? 'unknown',
                $tourData
            );
            throw $e;
        }
    }

    /**
     * Prepare tour fields for fill
     */
    protected function prepareTourFields(array $tourSection, bool $isNew): array
    {
        $tour = new Tour();
        $fillableFields = $tour->getFillable();
        $tourFields = [];

        $numericFields = ['hotel_star', 'duration_days', 'duration_nights', 'primary_country_id', 'transport_id'];
        $autoGeneratedFields = ['tour_code'];

        foreach ($tourSection as $field => $value) {
            // Skip null values
            if ($value === null) continue;

            // Skip empty auto-generated fields
            if (empty($value) && in_array($field, $autoGeneratedFields)) {
                continue;
            }

            // Skip empty numeric fields
            if ($value === '' && in_array($field, $numericFields)) {
                continue;
            }

            // Only fill if it's a fillable field
            if (in_array($field, $fillableFields) || empty($fillableFields)) {
                $tourFields[$field] = $value;
            }
        }

        // Auto-calculate duration_nights
        if (empty($tourFields['duration_nights']) && !empty($tourFields['duration_days'])) {
            $tourFields['duration_nights'] = max(0, (int)$tourFields['duration_days'] - 1);
        }

        // Set default duration for new tours
        if ($isNew) {
            if (empty($tourFields['duration_days'])) {
                $tourFields['duration_days'] = !empty($tourFields['duration_nights']) 
                    ? (int)$tourFields['duration_nights'] + 1 
                    : 0;
            }
            if (!isset($tourFields['duration_nights'])) {
                $tourFields['duration_nights'] = 0;
            }
        }

        // Convert array fields to JSON
        $jsonFields = ['highlights', 'hashtags', 'themes', 'suitable_for', 'departure_airports'];
        foreach ($jsonFields as $jsonField) {
            if (isset($tourFields[$jsonField]) && is_array($tourFields[$jsonField])) {
                $tourFields[$jsonField] = json_encode($tourFields[$jsonField], JSON_UNESCAPED_UNICODE);
            }
        }

        // Set sync metadata
        $tourFields['sync_status'] = 'active';
        $tourFields['last_synced_at'] = now();

        // Truncate title
        if (!empty($tourFields['title']) && mb_strlen($tourFields['title']) > 250) {
            $tourFields['title'] = mb_substr($tourFields['title'], 0, 247) . '...';
        }

        return $tourFields;
    }

    /**
     * Process media (images, PDFs)
     */
    protected function processMedia(array &$tourSection, Tour $tour): void
    {
        // Upload cover image to R2
        if (!empty($tourSection['cover_image_url']) && $this->cloudflare) {
            try {
                $result = $this->cloudflare->uploadFromUrl(
                    $tourSection['cover_image_url'],
                    'tours/' . ($tour->tour_code ?? 'temp')
                );
                if ($result && isset($result['url'])) {
                    $tourSection['cover_image_url'] = $result['url'];
                }
            } catch (\Exception $e) {
                Log::warning('TourSyncService: Failed to upload cover image', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Process PDF with branding
        if (!empty($tourSection['pdf_url']) && $this->pdfBranding) {
            try {
                $brandedPdf = $this->pdfBranding->addBranding($tourSection['pdf_url']);
                if ($brandedPdf) {
                    // Upload to R2
                    $disk = Storage::disk('r2');
                    $path = 'pdfs/' . ($tour->tour_code ?? 'temp') . '.pdf';
                    $disk->put($path, file_get_contents($brandedPdf));
                    $tourSection['pdf_url'] = $disk->url($path);
                }
            } catch (\Exception $e) {
                Log::warning('TourSyncService: Failed to process PDF', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process periods/departures
     */
    protected function processPeriods(Tour $tour, array $departures): array
    {
        $result = ['created' => 0, 'updated' => 0];

        foreach ($departures as $depData) {
            // Implementation similar to original
            // ... (simplified for this example)
            $result['created']++;
        }

        return $result;
    }

    /**
     * Process itineraries
     */
    protected function processItineraries(Tour $tour, array $itineraries): void
    {
        // Implementation similar to original
        // ... (simplified for this example)
    }

    /**
     * Generate unique tour code
     */
    protected function generateTourCode(): string
    {
        $prefix = 'NT';
        $yearMonth = now()->format('Ym');

        $lastTour = Tour::where('tour_code', 'like', "{$prefix}{$yearMonth}%")
            ->orderBy('tour_code', 'desc')
            ->first();

        if ($lastTour && preg_match('/NT\d{6}(\d{3})$/', $lastTour->tour_code, $matches)) {
            $seq = intval($matches[1]) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . $yearMonth . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
