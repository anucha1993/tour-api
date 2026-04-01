<?php

/**
 * Headcode Adapter: TTN Connect Japan (api_type = ttn)
 * บริษัท ทีทีเอ็น คอนเน็ค จำกัด (เส้น Japan)
 *
 * ────────────────────────────────────────────────────────────────
 * SETUP (wholesaler_api_configs record)
 * ────────────────────────────────────────────────────────────────
 *   integration_type : headcode
 *   headcode_file    : ttn_japan
 *   api_base_url     : headcode://custom
 *   auth_type        : custom
 *   auth_credentials : {}
 *
 * ────────────────────────────────────────────────────────────────
 * THREE-PHASE SYNC FLOW
 * ────────────────────────────────────────────────────────────────
 *   Phase 1  GET /get-programId         → [ { "P_ID": 123 }, ... ]
 *   Phase 2  GET /program/{P_ID}        → [ { tour detail + Itinerary[] } ]
 *   Phase 3  GET /program/period/{P_ID} → [ { period + Price[] + flight info } ]
 *
 * ────────────────────────────────────────────────────────────────
 * FULL API FIELD MAPPING (verified 2026-03-26)
 * ────────────────────────────────────────────────────────────────
 *
 *   Tour (Phase 2):
 *     P_ID             → external_id
 *     P_CODE           → wholesaler_tour_code
 *     P_NAME           → title
 *     P_HIGHLIGHT      → description, highlights
 *     P_TAG            → hashtags
 *     P_PRICE          → base price (string, tour-level)
 *     P_DAY            → duration_days
 *     P_NIGHT          → duration_nights
 *     P_AIRLINE        → transport_id (2-char IATA lookup)
 *     P_AIRLINE_NAME   → (used for TourTransport name)
 *     P_LOCATION       → city lookup
 *     P_HOTEL_STAR     → hotel_star
 *     P_MEAL           → (meal count, used for itinerary)
 *     P_DEPARTURE      → outbound flight number
 *     P_RETURN         → return flight number
 *     BANNER           → cover_image_url
 *     PDF              → pdf_url
 *     WORD             → docx_url
 *     Itinerary[]      → itineraries (D_DAY, D_ITIN)
 *
 *   Period (Phase 3):
 *     P_ID               → external_id (period-level)
 *     P_PROGRAM_ID       → links to tour
 *     P_CODEGROUP        → period_code
 *     P_DUE_START        → start_date
 *     P_DUE_END          → end_date
 *     flight_departure   → outbound flight no
 *     segment_departure  → route e.g. "DMK-OKA"
 *     flight_start_date  → outbound flight date
 *     flight_start_departure_time → outbound depart time
 *     flight_start_arrival_time   → outbound arrival time
 *     flight_return      → return flight no
 *     segment_return     → return route e.g. "OKA-DMK"
 *     flight_end_date    → return flight date
 *     flight_end_departure_time   → return depart time
 *     flight_end_arrival_time     → return arrival time
 *     Price[]:
 *       P_STATUS           → status ("Open" → open, "ChangePrice"/other → sold_out)
 *       P_VOLUME           → capacity
 *       P_AVAILABLE        → available seats (numeric)
 *       P_BOOKING          → booked seats
 *       P_ADULT_PRICE      → price_adult
 *       P_SINGLE_PRICE     → price_single
 *       P_INFANT_PRICE     → price_infant
 *       P_JOINLAND_PRICE   → price_joinland
 *       P_COMIMISSION      → commission_agent (API typo)
 *       P_MEAL_ON_BOARD    → meal on board flag
 */

class HeadcodeTtnJapanAdapter extends \App\Services\WholesalerAdapters\HeadcodeBaseAdapter
{
    /** TTN Connect Japan API base */
    private const API_BASE = 'https://online.ttnconnect.com/api/agency';

    /**
     * Fetch all tours with their departure periods (3-phase).
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

        // ── Phase 1: fetch program ID list ───────────────────────────────────
        $listResponse = $this->httpGetQuiet(self::API_BASE . '/get-programId');
        $programIds = is_array($listResponse) ? $listResponse : [];

        if (empty($programIds)) {
            \Illuminate\Support\Facades\Log::info('HeadcodeTtnJapanAdapter: No programs returned', [
                'wholesaler_id' => $this->wholesalerId,
            ]);
            return $this->buildSyncResult([]);
        }

        if ($pingMode) {
            \Illuminate\Support\Facades\Log::info('HeadcodeTtnJapanAdapter: Ping OK', [
                'wholesaler_id' => $this->wholesalerId,
                'program_count' => count($programIds),
            ]);
            $stubs = array_map(fn($p) => [
                'code'    => $p['P_ID'] ?? null,
                'name'    => null,
                'periods' => [],
            ], $programIds);
            return $this->buildSyncResult($stubs);
        }

        \Illuminate\Support\Facades\Log::info('HeadcodeTtnJapanAdapter: Fetched program list', [
            'wholesaler_id' => $this->wholesalerId,
            'count'         => count($programIds),
        ]);

        $transportCache = [];
        $cityCache      = [];
        $today      = date('Y-m-d');
        $normalized = [];
        $skipped    = 0;

        // Lookup Japan country_id once (cached for all tours)
        $japanCountryId = $this->lookupCountryId('JP');

        // ── Phase 2 + 3: fetch detail + periods per tour ─────────────────────
        foreach ($programIds as $prog) {
            $pId = $prog['P_ID'] ?? null;
            if (!$pId) {
                continue;
            }

            // Phase 2: tour detail
            try {
                $detailResponse = $this->httpGetQuiet(self::API_BASE . '/program/' . rawurlencode((string) $pId));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('HeadcodeTtnJapanAdapter: Phase 2 failed', [
                    'p_id' => $pId, 'error' => $e->getMessage(),
                ]);
                $skipped++;
                continue;
            }
            $tourItems = is_array($detailResponse) ? $detailResponse : [];
            if (empty($tourItems)) {
                $skipped++;
                continue;
            }
            $tour = $tourItems[0] ?? null;
            if (!$tour) {
                $skipped++;
                continue;
            }

            // Phase 3: periods
            try {
                $periodsResponse = $this->httpGetQuiet(self::API_BASE . '/program/period/' . rawurlencode((string) $pId));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('HeadcodeTtnJapanAdapter: Phase 3 failed', [
                    'p_id' => $pId, 'error' => $e->getMessage(),
                ]);
                $periodsResponse = [];
            }
            $rawPeriods = is_array($periodsResponse) ? $periodsResponse : [];

            // ── Filter future periods ────────────────────────────────────────
            $futurePeriods = [];
            foreach ($rawPeriods as $rp) {
                $startDate = $rp['P_DUE_START'] ?? null;
                if (!$startDate || $startDate < $today) {
                    continue;
                }
                $futurePeriods[] = $rp;
            }

            // ── Transport lookup (2-char IATA) ───────────────────────────────
            $airlineCode = null;
            $airlineName = $tour['P_AIRLINE_NAME'] ?? null;
            $airlineRaw = $tour['P_AIRLINE'] ?? null;
            if ($airlineRaw) {
                $clean = strtoupper(trim((string) $airlineRaw));
                if (strlen($clean) >= 2) {
                    $airlineCode = substr($clean, 0, 2);
                }
            }
            $transportId = null;
            $transportObj = null;
            if ($airlineCode) {
                if (!isset($transportCache[$airlineCode])) {
                    $transportCache[$airlineCode] = \App\Models\Transport::where('code', $airlineCode)
                        ->orWhere('code1', $airlineCode)
                        ->first();
                }
                $transportObj = $transportCache[$airlineCode];
                $transportId = $transportObj?->id;
            }

            // ── City lookup from P_LOCATION ──────────────────────────────
            $locationRaw = trim((string) ($tour['P_LOCATION'] ?? ''));
            $cityId = null;
            if ($locationRaw) {
                if (!isset($cityCache[$locationRaw])) {
                    $cityCache[$locationRaw] = \App\Models\City::where('name_en', 'LIKE', '%' . $locationRaw . '%')
                        ->first();
                }
                $cityId = $cityCache[$locationRaw]?->id;
            }

            // ── Build departures (from period Price entries) ─────────────────
            // Each period has a Price[] array. Each Price entry is a price group
            // (e.g. different price tiers). We take the cheapest "Open" one as
            // the primary, or the cheapest overall if none are Open.
            $departures     = [];
            $minPriceAdult  = null;
            $totalAvailable = 0;
            $nextDeparture  = null;

            foreach ($futurePeriods as $fp) {
                $periodId  = (string) ($fp['P_ID'] ?? '');
                $periodCode = $fp['P_CODEGROUP'] ?? null;
                $startDate = $fp['P_DUE_START'] ?? null;
                $endDate   = $fp['P_DUE_END']   ?? null;

                // Find the best price entry: prefer "Open" with cheapest adult price
                $bestPrice  = null;
                $bestStatus = 3; // default closed
                foreach ($fp['Price'] ?? [] as $priceEntry) {
                    // P_STATUS is the real status: "Open" or "ChangePrice"
                    $pStatus     = $priceEntry['P_STATUS'] ?? '';
                    $pAdultPrice = (float) ($priceEntry['P_ADULT_PRICE'] ?? 0);

                    if ($pAdultPrice <= 0) {
                        continue;
                    }

                    $entryStatus = ($pStatus === 'Open') ? 1 : 3;

                    // Prefer Open entries. Among same status, prefer cheapest.
                    if ($bestPrice === null
                        || ($entryStatus === 1 && $bestStatus !== 1)  // Open beats non-Open
                        || ($entryStatus === $bestStatus && $pAdultPrice < (float) ($bestPrice['P_ADULT_PRICE'] ?? 0))
                    ) {
                        $bestPrice  = $priceEntry;
                        $bestStatus = $entryStatus;
                    }
                }

                if (!$bestPrice) {
                    continue;
                }

                $priceAdult     = (float) ($bestPrice['P_ADULT_PRICE']    ?? 0);
                $priceSingle    = (float) ($bestPrice['P_SINGLE_PRICE']   ?? 0);
                $priceInfant    = (float) ($bestPrice['P_INFANT_PRICE']   ?? 0);
                $priceJoinland  = (float) ($bestPrice['P_JOINLAND_PRICE'] ?? 0);
                $commission     = (float) ($bestPrice['P_COMIMISSION']    ?? 0); // API typo
                $capacity       = (int) ($bestPrice['P_VOLUME']           ?? 0);
                $available      = (int) ($bestPrice['P_AVAILABLE']        ?? 0);
                $booked         = (int) ($bestPrice['P_BOOKING']          ?? 0);

                $isOpen = ($bestStatus === 1);
                if ($isOpen) {
                    $totalAvailable += $available;
                    if ($nextDeparture === null || $startDate < $nextDeparture) {
                        $nextDeparture = $startDate;
                    }
                }

                // min_price across ALL future periods (open or not)
                if ($priceAdult > 0 && ($minPriceAdult === null || $priceAdult < $minPriceAdult)) {
                    $minPriceAdult = $priceAdult;
                }

                $departures[] = [
                    'external_id'      => $periodId,
                    'period_code'      => $periodCode,
                    'start_date'       => $startDate,
                    'end_date'         => $endDate,
                    'capacity'         => $capacity,
                    'booked'           => $booked,
                    'available'        => $available,
                    'status'           => $bestStatus,
                    'price_adult'      => $priceAdult      ?: null,
                    'price_single'     => $priceSingle     ?: null,
                    'price_infant'     => $priceInfant     ?: null,
                    'price_joinland'   => $priceJoinland   ?: null,
                    'commission_agent' => $commission       ?: null,
                ];
            }

            // ── Skip tours without future departures ─────────────────────
            // เงื่อนไข: ต้องมี future period อย่างน้อย 1 รอบ ถึงจะ sync
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

            // ── Build itinerary from Phase 2 Itinerary[] ─────────────────────
            $itinerary = [];
            $totalMeals = isset($tour['P_MEAL']) ? (int) $tour['P_MEAL'] : null;
            foreach ($tour['Itinerary'] ?? [] as $itin) {
                $dayNum = (int) ($itin['D_DAY'] ?? 0);
                $desc   = trim((string) ($itin['D_ITIN'] ?? ''));
                if ($dayNum > 0 && $desc) {
                    $itinerary[] = [
                        'day_number'  => $dayNum,
                        'title'       => $desc,
                        'description' => $desc,
                        // 'places'      => $desc,
                        'sort_order'  => $dayNum,
                    ];
                }
            }

            // ── Description and highlights from P_HIGHLIGHT ──────────────
            $description = trim((string) ($tour['P_HIGHLIGHT'] ?? ''));

            // ── Hashtags from P_TAG ──────────────────────────────────────
            $rawTags = trim((string) ($tour['P_TAG'] ?? ''));
            $hashtags = null;
            if ($rawTags) {
                $tagArray = array_map('trim', explode(',', $rawTags));
                $tagArray = array_filter($tagArray);
                if (!empty($tagArray)) {
                    $hashtags = $tagArray;
                }
            }

            // ── Highlights from P_HIGHLIGHT (split by comma) ─────────────
            $highlights = null;
            if ($description) {
                $hlArray = array_map('trim', explode(',', $description));
                $hlArray = array_filter($hlArray);
                if (!empty($hlArray)) {
                    $highlights = $hlArray;
                }
            }

            // ── Departure airports from P_DEPARTURE segment ──────────────
            // Extract airport codes from first period's segment_departure (e.g. "DMK-OKA" → ["DMK"])
            $departureAirports = null;
            if (!empty($futurePeriods)) {
                $firstPeriod = $futurePeriods[0];
                $segDep = $firstPeriod['segment_departure'] ?? '';
                if ($segDep && str_contains($segDep, '-')) {
                    $parts = explode('-', $segDep);
                    $departureAirports = [trim($parts[0])];
                }
            }

            // ── Assemble pre-mapped structure ────────────────────────────────
            $normalized[] = [
                'tour' => [
                    'external_id'          => (string) $pId,
                    'wholesaler_tour_code' => (string) ($tour['P_CODE'] ?? $pId), 
                    'title'                => $tour['P_NAME']  ?? null, 
                    'description'          => $description ?: null,
                    'primary_country_id'   => $japanCountryId,
                    'transport_id'         => $transportId,
                    'cover_image_url'      => $tour['BANNER']  ?? null,
                    'pdf_url'              => $tour['PDF']      ?? null,
                    'docx_url'             => $tour['WORD']     ?? null,
                    'duration_days'        => isset($tour['P_DAY'])   ? (int) $tour['P_DAY']   : null,
                    'duration_nights'      => isset($tour['P_NIGHT']) ? (int) $tour['P_NIGHT'] : null,
                    'hotel_star'           => isset($tour['P_HOTEL_STAR']) ? (int) $tour['P_HOTEL_STAR'] : null,
                    'highlights'           => $highlights,
                    'hashtags'             => $hashtags,
                    'departure_airports'   => $departureAirports,
                    'region'               => 'ASIA',
                    'sub_region'           => 'EAST_ASIA',
                    'price_adult'          => $priceAdultOpen ?? $minPriceAdult,
                    'min_price'            => $minPriceAdult,
                    'display_price'        => $minPriceAdult,
                    'next_departure_date'  => $nextDeparture,
                    'total_departures'     => count($departures),
                    'available_seats'      => $totalAvailable,
                ],
                'departure' => $departures,
                'itinerary' => $itinerary,
                'content'   => [
                    'description' => $description ?: null,
                    'highlights'  => $highlights,
                ],
                'media'     => [
                    'cover_image_url' => $tour['BANNER'] ?? null,
                    'pdf_url'         => $tour['PDF']    ?? null,
                ],
                'seo'       => [
                    'meta_title'       => $tour['P_NAME'] ?? null,
                    'meta_description' => $description ? mb_substr($description, 0, 160) : null,
                    'keywords'         => $hashtags,
                    'hashtags'         => $hashtags,
                ],
                // Extra data for post-processing (transport detail, city, flight info)
                '_extra' => [
                    'airline_code'     => $airlineCode,
                    'airline_name'     => $airlineName ?? ($transportObj->name ?? null),
                    'city_id'          => $cityId,
                    'flight_departure' => $tour['P_DEPARTURE'] ?? null,
                    'flight_return'    => $tour['P_RETURN'] ?? null,
                    // First period flight details (for TourTransport)
                    'flight_info'      => !empty($futurePeriods) ? [
                        'segment_departure' => $futurePeriods[0]['segment_departure'] ?? null,
                        'segment_return'    => $futurePeriods[0]['segment_return'] ?? null,
                        'depart_time'       => $futurePeriods[0]['flight_start_departure_time'] ?? null,
                        'arrive_time'       => $futurePeriods[0]['flight_start_arrival_time'] ?? null,
                        'return_depart_time' => $futurePeriods[0]['flight_end_departure_time'] ?? null,
                        'return_arrive_time' => $futurePeriods[0]['flight_end_arrival_time'] ?? null,
                    ] : null,
                ],
            ];

            if ($sampleMode || ($limitN !== null && count($normalized) >= $limitN)) {
                break;
            }
        }

        \Illuminate\Support\Facades\Log::info('HeadcodeTtnJapanAdapter: Normalized tours ready', [
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
