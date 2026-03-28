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
 *   auth_credentials : { "tour_ids": [21,29,28,23,25,24,18,2,3,17,1,19] }
 *
 * ────────────────────────────────────────────────────────────────
 * MULTI-CALL FLAT API (เหมือน ApiController เดิม)
 * ────────────────────────────────────────────────────────────────
 *   GET https://superbholidayz.com/superb/apiweb.php?id={tourId}
 *   → flat array where each item = 1 tour + 1 period
 *   Multiple items share the same `mainid` → different departure periods
 *   Must GROUP BY mainid to build tour + periods structure
 *   Loop through hardcoded tour IDs (same as old superbholiday_api)
 *
 * ────────────────────────────────────────────────────────────────
 * FULL API FIELD MAPPING
 * ────────────────────────────────────────────────────────────────
 *
 *   Tour-level (first item per mainid):
 *     mainid          → external_id
 *     maincode        → wholesaler_tour_code (fallback to mainid)
 *     title           → title
 *     Country         → country name (Thai) → lookup country_name_th / name_th
 *     aey             → airline string e.g. "Thai Airways (TG)" → extract code
 *     banner          → cover_image_url
 *     pdf             → pdf_url
 *     day             → duration_days
 *     night           → duration_nights
 *     story           → season/description text
 *     itinerary       → itinerary text (HTML-encoded)
 *
 *   Period-level (each item):
 *     pid             → period_code
 *     mainid + pid    → external_id (period-level)
 *     Date            → start_date
 *     ENDDate         → end_date
 *     Adult           → price_adult (ผู้ใหญ่พักคู่)
 *     Chd+B           → price_child_with_bed (เด็กมีเตียง)
 *     ChdNB           → price_child_no_bed (เด็กไม่มีเตียง)
 *     Single          → price_single (ผู้ใหญ่พักเดี่ยว)
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
    /** Default tour IDs to fetch — same as old superbholiday_api() */
    private const DEFAULT_TOUR_IDS = [21, 29, 28, 23, 25, 24, 18, 2, 3, 17, 1, 19];

    /** API base URL */
    private const API_BASE = 'https://superbholidayz.com/superb/apiweb.php';

    /**
     * Get the list of tour IDs to fetch.
     * Can be overridden via auth_credentials.tour_ids
     */
    private function getTourIds(): array
    {
        $creds = $this->config->auth_credentials ?? [];
        if (!empty($creds['tour_ids']) && is_array($creds['tour_ids'])) {
            return $creds['tour_ids'];
        }
        return self::DEFAULT_TOUR_IDS;
    }

    /**
     * Get the API base URL. Can be overridden via auth_credentials.api_url
     */
    private function getApiBaseUrl(): string
    {
        $creds = $this->config->auth_credentials ?? [];
        return $creds['api_url'] ?? $creds['api_base_url'] ?? self::API_BASE;
    }

    /**
     * Lookup country ID by Thai name (LIKE match on name_th).
     * Handles multi-part names like "จีน-ซินเจียง" → try full, then first part "จีน"
     * Also tries country_code field (ISO2) as fallback.
     */
    private function lookupCountryByThaiName(string $thaiName, ?string $countryCode = null): ?int
    {
        // Try ISO2 code first (most reliable)
        if ($countryCode) {
            $id = $this->lookupCountryId(strtoupper(trim($countryCode)));
            if ($id) return $id;
        }

        // Try full name LIKE match
        $id = \Illuminate\Support\Facades\DB::table('countries')
            ->where('name_th', 'like', '%' . $thaiName . '%')
            ->where('is_active', true)
            ->value('id');
        if ($id !== null) return (int) $id;

        // Try first part before dash/slash e.g. "จีน-ซินเจียง" → "จีน"
        $parts = preg_split('/[-\/]/', $thaiName);
        if (count($parts) > 1) {
            $firstPart = trim($parts[0]);
            // Remove suffixes like "SL", "TR", "SQ" etc (airline codes mixed in)
            $firstPart = preg_replace('/\s+[A-Z]{2}$/u', '', $firstPart);
            if ($firstPart && $firstPart !== $thaiName) {
                $id = \Illuminate\Support\Facades\DB::table('countries')
                    ->where('name_th', 'like', '%' . $firstPart . '%')
                    ->where('is_active', true)
                    ->value('id');
                if ($id !== null) return (int) $id;
            }
        }

        // Try name with trailing airline code removed e.g. "สิงคโปร์ SL" → "สิงคโปร์"
        $cleanedName = preg_replace('/\s+[A-Z]{2,3}$/u', '', $thaiName);
        if ($cleanedName && $cleanedName !== $thaiName) {
            $id = \Illuminate\Support\Facades\DB::table('countries')
                ->where('name_th', 'like', '%' . $cleanedName . '%')
                ->where('is_active', true)
                ->value('id');
            if ($id !== null) return (int) $id;
        }

        return null;
    }

    /**
     * Lookup transport ID by airline code extracted from "aey" field.
     * Case-insensitive search on both code and code1 fields.
     */
    private function lookupTransportByAirlineCode(string $code): ?int
    {
        $code = strtoupper(trim($code));
        $transport = \App\Models\Transport::where('status', 'on')
            ->where(function ($q) use ($code) {
                $q->whereRaw('UPPER(code) = ?', [$code])
                  ->orWhereRaw('UPPER(code1) = ?', [$code]);
            })
            ->first();
        return $transport?->id;
    }

    /**
     * Lookup transport ID by airline name (fuzzy match).
     * Used as fallback when no code is available.
     */
    private function lookupTransportByName(string $airlineName): ?int
    {
        // Clean airline name: remove parenthetical codes, extra whitespace
        $cleanName = preg_replace('/\s*\(.*?\)\s*/', '', $airlineName);
        $cleanName = trim($cleanName);
        if (!$cleanName) return null;

        // Exact match (case-insensitive)
        $transport = \App\Models\Transport::where('status', 'on')
            ->whereRaw('UPPER(name) = ?', [strtoupper($cleanName)])
            ->first();
        if ($transport) return $transport->id;

        // Partial match (name LIKE)
        $transport = \App\Models\Transport::where('status', 'on')
            ->where('name', 'like', '%' . $cleanName . '%')
            ->first();
        if ($transport) return $transport->id;

        // Fuzzy match: try first N-1 characters (handles typos like "THAI LION ARI" → "THAI LION AR")
        if (mb_strlen($cleanName) > 5) {
            $partial = mb_substr($cleanName, 0, mb_strlen($cleanName) - 1);
            $transport = \App\Models\Transport::where('status', 'on')
                ->where('name', 'like', '%' . $partial . '%')
                ->first();
            if ($transport) return $transport->id;
        }

        // Try without last word (handles typos like "THAI LION ARI" → "THAI LION")
        $words = preg_split('/\s+/', $cleanName);
        if (count($words) >= 2) {
            $withoutLast = implode(' ', array_slice($words, 0, -1));
            $transport = \App\Models\Transport::where('status', 'on')
                ->where('name', 'like', '%' . $withoutLast . '%')
                ->first();
            if ($transport) return $transport->id;
        }

        return null;
    }

    /**
     * Extract airline code from "aey" field. e.g. "Thai Airways (TG)" → "TG"
     * Also handles combined codes like "MALAYSIA+SCOOT (MH+TR)" → "MH"
     */
    private function extractAirlineCode(string $aey): ?string
    {
        $parts = explode('(', $aey);
        if (isset($parts[1])) {
            $code = trim($parts[1], ') ');
            // Handle combined codes: "MH+TR" → take first one
            if (str_contains($code, '+')) {
                $code = explode('+', $code)[0];
            }
            return $code ?: null;
        }
        return null;
    }

    /**
     * Override httpGet to use direct HTTP call (bypass BaseAdapter's request pipeline).
     * Superb Holidayz API ไม่ต้องการ auth — ใช้ Http::get() ตรงแทน
     */
    protected function httpGet(string $url, array $params = []): array
    {
        if (!empty($params)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($params);
        }

        $response = \Illuminate\Support\Facades\Http
            ::timeout($this->config->request_timeout_seconds ?? 30)
            ->connectTimeout($this->config->connect_timeout_seconds ?? 10)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception(
                "API Error {$response->status()}: " . substr($response->body(), 0, 500),
                $response->status()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Fetch all tours with their departure periods.
     * Loops through tour IDs and calls API per ID (same as old superbholiday_api).
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

        $tourIds = $this->getTourIds();
        $apiBase = $this->getApiBaseUrl();

        // ── Fetch all data by looping tour IDs (เหมือน ApiController เดิม) ──
        $rawData = [];
        foreach ($tourIds as $id) {
            $url = $apiBase . '?id=' . (int) $id;
            try {
                $response = $this->httpGet($url);
                if (is_array($response) && !empty($response)) {
                    foreach ($response as $item) {
                        $rawData[] = $item;
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('HeadcodeSuperbholidayz: Failed to fetch tour ID ' . $id, [
                    'wholesaler_id' => $this->wholesalerId,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                ]);
                continue;
            }
        }

        if (empty($rawData)) {
            \Illuminate\Support\Facades\Log::info('HeadcodeSuperbholidayz: No data returned', [
                'wholesaler_id' => $this->wholesalerId,
                'tour_ids' => $tourIds,
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

        $transportCache = [];
        $countryCache = [];
        $normalized = [];
        $skipped = 0;

        foreach ($grouped as $mainId => $items) {
            // ── Use first item for tour-level data ───────────────────────
            $tour = $items[0];

            $title = trim((string) ($tour['title'] ?? ''));
            if (!$title) {
                $skipped++;
                continue;
            }

            // ── Country lookup from Country field (Thai name LIKE match) ─
            // Also use country_code (ISO2) as fallback
            $countryName = trim((string) ($tour['Country'] ?? ''));
            $countryCode = trim((string) ($tour['country_code'] ?? ''));
            $countryId = null;
            $countryCacheKey = $countryName . '|' . $countryCode;
            if ($countryName || $countryCode) {
                if (!isset($countryCache[$countryCacheKey])) {
                    $countryCache[$countryCacheKey] = $this->lookupCountryByThaiName(
                        $countryName, $countryCode ?: null
                    );
                }
                $countryId = $countryCache[$countryCacheKey];
            }

            // ── Transport lookup ─────────────────────────────────────────
            // Priority: aeycode → extract from aey → airline_code1
            $aey = trim((string) ($tour['aey'] ?? ''));
            $aeycode = trim((string) ($tour['aeycode'] ?? ''));
            $airlineCode1 = trim((string) ($tour['airline_code1'] ?? ''));
            $airlineCode = null;
            $transportId = null;

            // 1. Try aeycode field directly
            if ($aeycode) {
                $airlineCode = str_contains($aeycode, '+') ? explode('+', $aeycode)[0] : $aeycode;
            }
            // 2. Try extract from aey e.g. "Thai Lion Air (SL)" → "SL"
            if (!$airlineCode && $aey) {
                $airlineCode = $this->extractAirlineCode($aey);
            }
            // 3. Try airline_code1
            if (!$airlineCode && $airlineCode1) {
                $airlineCode = $airlineCode1;
            }

            if ($airlineCode) {
                if (!isset($transportCache[$airlineCode])) {
                    $transportCache[$airlineCode] = $this->lookupTransportByAirlineCode($airlineCode);
                }
                $transportId = $transportCache[$airlineCode];
            }

            // 4. Fallback: name-based lookup from aey field
            if (!$transportId && $aey) {
                $nameCacheKey = 'name:' . $aey;
                if (!isset($transportCache[$nameCacheKey])) {
                    $transportCache[$nameCacheKey] = $this->lookupTransportByName($aey);
                }
                $transportId = $transportCache[$nameCacheKey];
            }

            // ── Duration ─────────────────────────────────────────────────
            $durationDays = !empty($tour['day']) ? (int) $tour['day'] : null;
            $durationNights = !empty($tour['night']) ? (int) $tour['night'] : null;

            // ── Build departures from all items of this mainid ───────────
            // (ไม่ skip period ที่ผ่านไปแล้ว — เหมือน ApiController เดิม)
            $departures = [];
            $minPriceAdult = null;
            $totalAvailable = 0;
            $nextDeparture = null;

            foreach ($items as $item) {
                $startDate = $item['Date'] ?? null;
                $endDate = $item['ENDDate'] ?? null;

                $priceAdult = (float) ($item['Adult'] ?? 0);
                $priceSingle = (float) ($item['Single'] ?? 0);
                $priceChildBed = (float) ($item['Chd+B'] ?? 0);
                $priceChildNoBed = (float) ($item['ChdNB'] ?? 0);
                $commission = (float) ($item['com'] ?? 0);
                $commissionPlus = (float) ($item['complus'] ?? 0);
                $deposit = (float) ($item['Deposit'] ?? 0);
                $booking = (int) ($item['Booking'] ?? 0);
                $available = (int) ($item['AVBL'] ?? 0);
                $capacity = (int) ($item['Size'] ?? 0);
                $periodCode = trim((string) ($item['pid'] ?? ''));

                // ── Status: AVBL > 0 → open (เหมือน ApiController เดิม) ─
                $status = ($available > 0) ? 1 : 3; // 1 = open, 3 = sold_out

                if ($available > 0) {
                    $totalAvailable += $available;
                    // Only use valid future dates for next_departure
                    if ($startDate && preg_match('/^\d{4}-\d{2}-\d{2}/', $startDate) && $startDate >= '2000-01-01') {
                        if ($nextDeparture === null || $startDate < $nextDeparture) {
                            $nextDeparture = $startDate;
                        }
                    }
                }

                // min_price across ALL periods
                if ($priceAdult > 0 && ($minPriceAdult === null || $priceAdult < $minPriceAdult)) {
                    $minPriceAdult = $priceAdult;
                }

                $departures[] = [
                    'external_id'        => $mainId . '_' . $periodCode,
                    'period_code'        => $periodCode,
                    'start_date'         => $startDate,
                    'end_date'           => $endDate,
                    'capacity'           => $capacity,
                    'booked'             => $booking,
                    'available'          => $available,
                    'status'             => $status,
                    'price_adult'        => $priceAdult ?: null,
                    'price_single'       => $priceSingle ?: null,
                    'price_child_bed'    => $priceChildBed ?: null,
                    'price_child_no_bed' => $priceChildNoBed ?: null,
                    'commission_agent'   => $commission ?: null,
                    'commission_plus'    => $commissionPlus ?: null,
                    'deposit'            => $deposit ?: null,
                    'duration_days'      => $durationDays,
                    'duration_nights'    => $durationNights,
                ];
            }

            // ── Skip tours without any departures ────────────────────────
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

            // ── Itinerary parsing (HTML-encoded text) ────────────────────
            $itinerary = [];
            $rawItinerary = trim((string) ($tour['itinerary'] ?? ''));
            if ($rawItinerary) {
                $decoded = html_entity_decode($rawItinerary, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
            $description = $countryName;
            if ($story) {
                $description = $description ? "{$description} | {$story}" : $story;
            }

            // ── Wholesaler tour code ─────────────────────────────────────
            $mainCode = trim((string) ($tour['maincode'] ?? ''));
            $tourCode = $mainCode ?: (string) $mainId;

            // ── Cover image: use banner field (เหมือน ApiController เดิม) ─
            $coverImage = trim((string) ($tour['banner'] ?? ''));

            // ── Hashtags from Country field ──────────────────────────────
            $hashtags = null;
            if ($countryName) {
                $tagArray = array_map('trim', preg_split('/[-,]/', $countryName));
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
                    'title'                => $title,
                    'description'          => $description ?: null,
                    'primary_country_id'   => $countryId,
                    'transport_id'         => $transportId,
                    'cover_image_url'      => $coverImage ?: null,
                    'pdf_url'              => trim((string) ($tour['pdf'] ?? '')) ?: null,
                    'duration_days'        => $durationDays,
                    'duration_nights'      => $durationNights,
                    'hashtags'             => $hashtags,
                    'highlights'           => $hashtags,
                    'price_adult'          => $priceAdultOpen ?? $minPriceAdult,
                    'min_price'            => $minPriceAdult,
                    'display_price'        => $minPriceAdult,
                    'next_departure_date'  => $nextDeparture,
                    'total_departures'     => count($departures),
                    'available_seats'      => $totalAvailable,
                ],
                'departure' => $departures,
                // 'itinerary' => $itinerary,
                'content'   => [
                    'description' => $description ?: null,
                ],
                'media'     => [
                    'cover_image_url' => $coverImage ?: null,
                    'pdf_url'         => trim((string) ($tour['pdf'] ?? '')) ?: null,
                ],
                'seo'       => [
                    'meta_title'       => $title,
                    'meta_description' => $description ? mb_substr($description, 0, 160) : null,
                    'keywords'         => $hashtags,
                    'hashtags'         => $hashtags,
                ],
                '_extra' => [
                    'airline_code'  => $airlineCode,
                    'airline_raw'   => $aey ?: null,
                    'country_raw'   => $countryName ?: null,
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
