<?php

/**
 * Headcode Adapter: Superb Holidayz
 * บริษัท ซุปเปอร์บ ฮอลิเดย์ จำกัด
 *
 * ────────────────────────────────────────────────────────────────
 * SETUP (wholesaler_api_configs record)
 * ────────────────────────────────────────────────────────────────
 *   integration_type : headcode
 *   headcode_file    : superbholidayz
 *   api_base_url     : headcode://custom
 *   auth_type        : custom
 *   auth_credentials : {} (or API key if required)
 *
 * ────────────────────────────────────────────────────────────────
 * SINGLE-CALL FLAT API
 * ────────────────────────────────────────────────────────────────
 *   GET {API_BASE}  → flat array where each item = 1 tour + 1 period
 *   Multiple items share the same `mainid` → different departure periods
 *   Must GROUP BY mainid to build tour + periods structure
 *
 * ────────────────────────────────────────────────────────────────
 * FULL API FIELD MAPPING
 * ────────────────────────────────────────────────────────────────
 *
 *   Tour-level (first item per mainid):
 *     mainid          → external_id
 *     maincode        → wholesaler_tour_code (fallback to mainid)
 *     title           → title (English)
 *     titleTH         → (Thai title, used if title empty)
 *     Country         → region description
 *     country_code    → primary_country_id (ISO2 lookup)
 *     Airline         → transport hint
 *     airline_code1   → transport_id (IATA lookup)
 *     airline_code2   → secondary airline
 *     banner          → cover_image_url (thumbnail)
 *     bannerFull      → cover_image_url (full, preferred)
 *     startingprice   → base starting price
 *     word            → docx_url
 *     pdf             → pdf_url
 *     day             → duration_days
 *     night           → duration_nights
 *     story           → season/description text
 *     itinerary       → itinerary text (HTML-encoded)
 *     option1-4 name  → tour options
 *     option1-4 price → option prices
 *
 *   Period-level (each item):
 *     pid             → period_code
 *     mainid + pid    → external_id (period-level)
 *     Date            → start_date
 *     ENDDate         → end_date
 *     Adult           → price_adult
 *     Chd+B           → price_child_with_bed
 *     ChdNB           → price_child_no_bed
 *     Single          → price_single
 *     Booking         → booked seats
 *     AVBL            → available seats
 *     com             → commission
 *     complus         → commission plus
 *     Deposit         → deposit amount
 *     Size            → capacity
 *     Procode         → promo code
 *     DatePromotion   → promotion start
 *     EndDatePromotion→ promotion end
 *     PricePromotion  → promotion price
 */

class HeadcodeSuperbholidayzAdapter extends \App\Services\WholesalerAdapters\HeadcodeBaseAdapter
{
    /** Superb Holidayz API endpoint — override via auth_credentials.api_url if needed */
    private function getApiUrl(): string
    {
        $creds = $this->config->auth_credentials ?? [];
        return $creds['api_url']
            ?? $creds['api_base_url']
            ?? 'https://superbholidayz.com/api/tours';
    }

    /**
     * Fetch all tours with their departure periods (single-call flat API).
     *
     * @param string|null $cursor  Special cursors: __ping__, __sample__, __limit:N__
     * @return \App\Services\WholesalerAdapters\Contracts\DTOs\SyncResult
     */
    public function fetchTours(?string $cursor = null): \App\Services\WholesalerAdapters\Contracts\DTOs\SyncResult
    {
        $pingMode   = ($cursor === '__ping__');
        $sampleMode = ($cursor === '__sample__');
        $limitN     = null;
        if ($cursor && preg_match('/^__limit:(\d+)__$/', $cursor, $m)) {
            $limitN = (int) $m[1];
        }

        // ── Fetch all data (single API call) ────────────────────────────────
        $apiUrl = $this->getApiUrl();
        $rawData = $this->httpGet($apiUrl);

        if (!is_array($rawData) || empty($rawData)) {
            \Illuminate\Support\Facades\Log::info('HeadcodeSuperbholidayz: No data returned', [
                'wholesaler_id' => $this->wholesalerId,
                'api_url' => $apiUrl,
            ]);
            return $this->buildSyncResult([]);
        }

        \Illuminate\Support\Facades\Log::info('HeadcodeSuperbholidayz: Fetched raw items', [
            'wholesaler_id' => $this->wholesalerId,
            'count' => count($rawData),
        ]);

        if ($pingMode) {
            $stubs = [];
            $seen = [];
            foreach ($rawData as $item) {
                $mid = $item['mainid'] ?? null;
                if ($mid && !isset($seen[$mid])) {
                    $seen[$mid] = true;
                    $stubs[] = ['code' => $mid, 'name' => $item['title'] ?? null, 'periods' => []];
                }
            }
            return $this->buildSyncResult($stubs);
        }

        // ── Group items by mainid (tour) ────────────────────────────────────
        $grouped = [];
        foreach ($rawData as $item) {
            $mainId = $item['mainid'] ?? null;
            if (!$mainId) continue;
            $grouped[$mainId][] = $item;
        }

        \Illuminate\Support\Facades\Log::info('HeadcodeSuperbholidayz: Grouped tours', [
            'wholesaler_id' => $this->wholesalerId,
            'unique_tours' => count($grouped),
            'total_items' => count($rawData),
        ]);

        $today = date('Y-m-d');
        $transportCache = [];
        $countryCache = [];
        $normalized = [];
        $skipped = 0;

        foreach ($grouped as $mainId => $items) {
            // ── Use first item for tour-level data ───────────────────────
            $tour = $items[0];

            $title = trim((string) ($tour['title'] ?? ''));
            $titleTH = trim((string) ($tour['titleTH'] ?? ''));
            // Use whichever title is available
            $displayTitle = $title ?: $titleTH;
            if (!$displayTitle) {
                $skipped++;
                continue;
            }

            // ── Country lookup from country_code (ISO2) ──────────────────
            $countryCode = strtoupper(trim((string) ($tour['country_code'] ?? '')));
            $countryId = null;
            if ($countryCode) {
                if (!isset($countryCache[$countryCode])) {
                    $countryCache[$countryCode] = $this->lookupCountryId($countryCode);
                }
                $countryId = $countryCache[$countryCode];
            }

            // ── Transport lookup from airline_code1 or Airline ───────────
            $airlineCode = null;
            $airlineName = trim((string) ($tour['Airline'] ?? ''));
            $code1 = strtoupper(trim((string) ($tour['airline_code1'] ?? '')));
            $code2 = strtoupper(trim((string) ($tour['airline_code2'] ?? '')));

            if ($code1 && strlen($code1) >= 2) {
                $airlineCode = substr($code1, 0, 2);
            }

            $transportId = null;
            if ($airlineCode) {
                if (!isset($transportCache[$airlineCode])) {
                    $transportCache[$airlineCode] = \App\Models\Transport::where('code', $airlineCode)
                        ->orWhere('code1', $airlineCode)
                        ->first();
                }
                $transportId = $transportCache[$airlineCode]?->id;
            }

            // ── Duration ─────────────────────────────────────────────────
            $durationDays = !empty($tour['day']) ? (int) $tour['day'] : null;
            $durationNights = !empty($tour['night']) ? (int) $tour['night'] : null;

            // ── Build departures from all items of this mainid ───────────
            $departures = [];
            $minPriceAdult = null;
            $totalAvailable = 0;
            $nextDeparture = null;

            foreach ($items as $item) {
                $startDate = $item['Date'] ?? null;
                $endDate = $item['ENDDate'] ?? null;

                // Skip past periods
                if ($startDate && $startDate < $today) continue;

                $priceAdult = (float) ($item['Adult'] ?? 0);
                $priceSingle = (float) ($item['Single'] ?? 0);
                $priceChildBed = (float) ($item['Chd+B'] ?? 0);
                $priceChildNoBed = (float) ($item['ChdNB'] ?? 0);
                $commission = (float) ($item['com'] ?? 0);
                $commissionPlus = (float) ($item['complus'] ?? 0);
                $booking = (int) ($item['Booking'] ?? 0);
                $available = (int) ($item['AVBL'] ?? 0);
                $capacity = (int) ($item['Size'] ?? 0);
                $periodCode = trim((string) ($item['pid'] ?? ''));

                // Skip entries with no valid price
                if ($priceAdult <= 0) continue;

                // Determine status: if available > 0 → open, else check capacity
                $isOpen = ($available > 0) || ($capacity > 0 && $booking < $capacity);
                $status = $isOpen ? 1 : 3; // 1 = open, 3 = sold_out

                if ($isOpen) {
                    $effectiveAvailable = $available > 0 ? $available : ($capacity - $booking);
                    $totalAvailable += max(0, $effectiveAvailable);
                    if ($nextDeparture === null || $startDate < $nextDeparture) {
                        $nextDeparture = $startDate;
                    }
                }

                // min_price across ALL future periods
                if ($minPriceAdult === null || $priceAdult < $minPriceAdult) {
                    $minPriceAdult = $priceAdult;
                }

                $departures[] = [
                    'external_id'      => $mainId . '_' . $periodCode,
                    'period_code'      => $periodCode,
                    'start_date'       => $startDate,
                    'end_date'         => $endDate,
                    'capacity'         => $capacity,
                    'booked'           => $booking,
                    'available'        => $isOpen ? max(0, $available ?: ($capacity - $booking)) : 0,
                    'status'           => $status,
                    'price_adult'      => $priceAdult ?: null,
                    'price_single'     => $priceSingle ?: null,
                    'price_child_bed'  => $priceChildBed ?: null,
                    'price_child_no_bed' => $priceChildNoBed ?: null,
                    'commission_agent' => $commission ?: null,
                ];
            }

            // ── Skip tours without future departures ─────────────────────
            if (empty($departures)) {
                $skipped++;
                continue;
            }

            // price_adult for tour: cheapest from OPEN periods only
            $priceAdultOpen = null;
            foreach ($departures as $dep) {
                if ($dep['status'] === 1 && $dep['price_adult'] && $dep['price_adult'] > 0) {
                    if ($priceAdultOpen === null || $dep['price_adult'] < $priceAdultOpen) {
                        $priceAdultOpen = $dep['price_adult'];
                    }
                }
            }

            // ── Starting price from API (may differ from period prices) ──
            $apiStartingPrice = (float) ($tour['startingprice'] ?? 0);

            // ── Itinerary parsing (HTML-encoded text) ────────────────────
            $itinerary = [];
            $rawItinerary = trim((string) ($tour['itinerary'] ?? ''));
            if ($rawItinerary) {
                // Decode HTML entities
                $decoded = html_entity_decode($rawItinerary, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Clean up: remove &nbsp; and normalize line breaks
                $decoded = str_replace(['&nbsp;', "\r\n", "\r"], ['', "\n", "\n"], $decoded);
                $decoded = trim($decoded);

                if ($decoded) {
                    $itinerary[] = [
                        'day_number'  => 1,
                        'title'       => 'รายละเอียดโปรแกรม',
                        'description' => $decoded,
                        'places'      => $decoded,
                        'sort_order'  => 1,
                    ];
                }
            }

            // ── Description from story + Country ─────────────────────────
            $story = trim((string) ($tour['story'] ?? ''));
            $country = trim((string) ($tour['Country'] ?? ''));
            $description = $country;
            if ($story) {
                $description = $description ? "{$description} | {$story}" : $story;
            }

            // ── Wholesaler tour code ─────────────────────────────────────
            $mainCode = trim((string) ($tour['maincode'] ?? ''));
            $tourCode = $mainCode ?: (string) $mainId;

            // ── Cover image: prefer bannerFull, fallback to banner ───────
            $coverImage = trim((string) ($tour['bannerFull'] ?? ''));
            if (!$coverImage) {
                $coverImage = trim((string) ($tour['banner'] ?? ''));
            }

            // ── Hashtags from Country field ──────────────────────────────
            $hashtags = null;
            if ($country) {
                $tagArray = array_map('trim', preg_split('/[-,]/', $country));
                $tagArray = array_filter($tagArray);
                if (!empty($tagArray)) {
                    $hashtags = array_values($tagArray);
                }
            }

            // ── Assemble pre-mapped structure ────────────────────────────
            $normalized[] = [
                'tour' => [
                    'external_id'          => (string) $mainId,
                    'wholesaler_tour_code' => $tourCode,
                    'title'                => $displayTitle,
                    'description'          => $description ?: null,
                    'primary_country_id'   => $countryId,
                    'transport_id'         => $transportId,
                    'cover_image_url'      => $coverImage ?: null,
                    'pdf_url'              => trim((string) ($tour['pdf'] ?? '')) ?: null,
                    'docx_url'             => trim((string) ($tour['word'] ?? '')) ?: null,
                    'duration_days'        => $durationDays,
                    'duration_nights'      => $durationNights,
                    'hashtags'             => $hashtags,
                    'highlights'           => $hashtags,
                    'price_adult'          => $priceAdultOpen ?? $minPriceAdult,
                    'min_price'            => $minPriceAdult,
                    'display_price'        => $apiStartingPrice > 0 ? $apiStartingPrice : $minPriceAdult,
                    'next_departure_date'  => $nextDeparture,
                    'total_departures'     => count($departures),
                    'available_seats'      => $totalAvailable,
                ],
                'departure' => $departures,
                'itinerary' => $itinerary,
                'content'   => [
                    'description' => $description ?: null,
                ],
                'media'     => [
                    'cover_image_url' => $coverImage ?: null,
                    'pdf_url'         => trim((string) ($tour['pdf'] ?? '')) ?: null,
                ],
                'seo'       => [
                    'meta_title'       => $displayTitle,
                    'meta_description' => $description ? mb_substr($description, 0, 160) : null,
                    'keywords'         => $hashtags,
                    'hashtags'         => $hashtags,
                ],
                '_extra' => [
                    'airline_code'  => $airlineCode,
                    'airline_code2' => $code2 ?: null,
                    'airline_name'  => $airlineName ?: null,
                    'title_th'      => $titleTH ?: null,
                    'story'         => $story ?: null,
                ],
            ];

            if ($sampleMode || ($limitN !== null && count($normalized) >= $limitN)) {
                break;
            }
        }

        \Illuminate\Support\Facades\Log::info('HeadcodeSuperbholidayz: Normalized tours ready', [
            'wholesaler_id' => $this->wholesalerId,
            'synced'        => count($normalized),
            'skipped'       => $skipped,
        ]);

        return $this->buildSyncResult($normalized);
    }

    /**
     * Not used — all data is fetched inline in fetchTours().
     */
    public function fetchTourDetail(string $code): ?array
    {
        return null;
    }
}
