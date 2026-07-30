<?php

/**
 * Headcode Adapter: Unique Inter Wholesale (api_type = uniqueinter)
 * บริษัท ยูนีค อินเตอร์ จำกัด (สำนักงานใหญ่)
 *
 * ────────────────────────────────────────────────────────────────
 * SETUP (wholesaler_api_configs record)
 * ────────────────────────────────────────────────────────────────
 *   integration_type : headcode
 *   headcode_file    : uniqueinter
 *   api_base_url     : headcode://custom
 *   auth_type        : custom
 *   auth_credentials : {}
 *
 * ────────────────────────────────────────────────────────────────
 * SINGLE-PHASE SYNC FLOW
 * ────────────────────────────────────────────────────────────────
 *   GET /apiwebsingle.php?user={email}
 *     → { success, message, count, data[] }
 *
 *   ⚠ ต่างจาก TTN ตรงที่ API นี้ส่ง "flat array" มาก้อนเดียว
 *     1 แถว = 1 พีเรียด (ไม่ใช่ 1 ทัวร์)
 *     ทัวร์เดียวกันจะมี mainid ซ้ำหลายแถว
 *     → adapter ต้อง group by mainid เอง
 *
 * ────────────────────────────────────────────────────────────────
 * FULL API FIELD MAPPING (verified 2026-07-28, 170 rows)
 * ────────────────────────────────────────────────────────────────
 *
 *   Tour level (ค่าซ้ำทุกแถวที่ mainid เดียวกัน):
 *     mainid        → external_id
 *     ProductCode   → wholesaler_tour_code
 *     title         → title    เช่น "UIEU_010_SCANDINAVIA SWEDEN NORWAYS DENMARK 10 DAYS"
 *     story         → description  เช่น "Oct-Dec 2026" (สั้นมาก ไม่ใช่รายละเอียดจริง)
 *     Country       → หมวดเส้นทาง เช่น "ทัวร์เส้นทางยุโรป" ⚠ ไม่ใช่ชื่อประเทศ lookup ไม่ได้
 *     CategoryID    → รหัสหมวด เช่น "59"
 *     Airline       → ชื่อสายการบินเต็ม เช่น "Emirates" ⚠ ไม่ใช่รหัส IATA
 *     jpg           → cover_image_url (URL เต็ม)
 *     pdf           → pdf_url   ⚠ relative path ต้องเติม domain
 *     word          → docx_url  ⚠ relative path ต้องเติม domain
 *     startingprice → ราคาเริ่มต้น
 *     Size          → capacity ของกรุ๊ป
 *
 *   Period level (ต่างกันในแต่ละแถว):
 *     pid      → external_id เช่น "UIEU-010/2026-1_081017102026"
 *     Date     → start_date
 *     ENDDate  → end_date
 *     Adult    → price_adult
 *     Chd+B    → ราคาเด็กมีเตียง  ⚠ ชื่อฟิลด์มีเครื่องหมาย +
 *     ChdNB    → ราคาเด็กไม่มีเตียง
 *     Single   → price_single
 *     Deposit  → มัดจำ
 *     com      → commission_agent
 *     complus  → commission เพิ่มเติม
 *     Pro      → ราคาโปรโมชั่น (0 = ไม่มี)
 *     AVBL     → available
 *     Booking  → 0=จองได้ 4=เต็ม/รอคิว 14=ตัดกรุ๊ป 15=CXL 16=ปิดกรุ๊ป
 *     visa     → ข้อมูลวีซ่า
 *     Link     → เอกสารเสริม
 *     Namelist → ไฟล์รายชื่อ
 *
 *   ไม่มีใน API: duration_days (คำนวณจาก Date/ENDDate), itinerary, hotel_star
 */

class HeadcodeUniqueinterAdapter extends \App\Services\WholesalerAdapters\HeadcodeBaseAdapter
{
    /** Unique Inter Wholesale API */
    private const API_BASE = 'https://uniqueinterwholesale.com/apiwebsingle.php';
    private const API_HOST = 'https://uniqueinterwholesale.com/';
    private const API_USER = 'nexttripholiday@gmail.com';

    /** สถานะ departure: 1 = เปิดขาย, 3 = ปิด/เต็ม */
    private const STATUS_OPEN   = 1;
    private const STATUS_CLOSED = 3;

    /**
     * ดึงทัวร์ทั้งหมดพร้อมรอบเดินทาง (single-phase)
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

        // ── ดึงข้อมูลก้อนเดียว ───────────────────────────────────────────────
        $url = self::API_BASE . '?user=' . rawurlencode(self::API_USER);
        $response = $this->httpGetQuiet($url);

        // API คืน { success, message, count, data[] }
        if (!is_array($response) || empty($response['success'])) {
            $msg = is_array($response) ? ($response['message'] ?? 'unknown') : 'invalid response';
            \Illuminate\Support\Facades\Log::warning('HeadcodeUniqueinterAdapter: API rejected request', [
                'wholesaler_id' => $this->wholesalerId,
                'message'       => $msg,
            ]);
            return $this->buildSyncResult([]);
        }

        $rows = is_array($response['data'] ?? null) ? $response['data'] : [];

        if (empty($rows)) {
            \Illuminate\Support\Facades\Log::info('HeadcodeUniqueinterAdapter: No rows returned', [
                'wholesaler_id' => $this->wholesalerId,
            ]);
            return $this->buildSyncResult([]);
        }

        \Illuminate\Support\Facades\Log::info('HeadcodeUniqueinterAdapter: Fetched flat rows', [
            'wholesaler_id' => $this->wholesalerId,
            'rows'          => count($rows),
            'api_count'     => $response['count'] ?? null,
        ]);

        // ── จัดกลุ่ม flat rows → tours[mainid] ───────────────────────────────
        $grouped = [];
        foreach ($rows as $r) {
            $mainId = trim((string) ($r['mainid'] ?? ''));
            if ($mainId === '') {
                continue;
            }
            if (!isset($grouped[$mainId])) {
                $grouped[$mainId] = ['head' => $r, 'rows' => []];
            }
            $grouped[$mainId]['rows'][] = $r;
        }

        // ── Ping mode: คืน stub เร็ว ๆ ───────────────────────────────────────
        if ($pingMode) {
            \Illuminate\Support\Facades\Log::info('HeadcodeUniqueinterAdapter: Ping OK', [
                'wholesaler_id' => $this->wholesalerId,
                'tour_count'    => count($grouped),
            ]);
            $stubs = [];
            foreach ($grouped as $mainId => $g) {
                $stubs[] = [
                    'code'    => $mainId,
                    'name'    => $g['head']['title'] ?? null,
                    'periods' => [],
                ];
            }
            return $this->buildSyncResult($stubs);
        }

        $today          = date('Y-m-d');
        $transportCache = [];
        $countryCache   = [];
        $normalized     = [];
        $skipped        = 0;

        foreach ($grouped as $mainId => $group) {
            $tour  = $group['head'];
            $title = trim((string) ($tour['title'] ?? ''));

            // ── Transport lookup (API ส่งชื่อเต็ม เช่น "Emirates") ───────────
            $airlineName = trim((string) ($tour['Airline'] ?? ''));
            $transportId = null;
            $airlineCode = null;
            if ($airlineName !== '') {
                $airlineCode = $this->airlineNameToCode($airlineName);
                if (!isset($transportCache[$airlineName])) {
                    $q = \App\Models\Transport::query();
                    if ($airlineCode) {
                        $q->where(function ($w) use ($airlineCode) {
                            $w->where('code', $airlineCode)->orWhere('code1', $airlineCode);
                        });
                    } else {
                        $q->where('name', 'LIKE', '%' . $airlineName . '%');
                    }
                    $transportCache[$airlineName] = $q->first();
                }
                $transportId = $transportCache[$airlineName]?->id;
            }

            // ── Country lookup: เดาจากชื่อทัวร์ (Country field ใช้ไม่ได้) ────
            $iso2List = $this->guessCountryIso2($title);
            $primaryCountryId = null;
            foreach ($iso2List as $iso2) {
                if (!array_key_exists($iso2, $countryCache)) {
                    $countryCache[$iso2] = $this->lookupCountryId($iso2);
                }
                if ($countryCache[$iso2]) {
                    $primaryCountryId = $countryCache[$iso2];
                    break; // ประเทศแรกที่เจอ = ประเทศหลัก
                }
            }

            [$region, $subRegion] = $this->guessRegion(
                (string) ($tour['Country'] ?? ''),
                $iso2List
            );

            // ── สร้าง departures จากทุกแถวของ mainid นี้ ─────────────────────
            $departures     = [];
            $minPriceAdult  = null;
            $priceAdultOpen = null;
            $totalAvailable = 0;
            $nextDeparture  = null;
            $durationDays   = null;

            foreach ($group['rows'] as $r) {
                $startDate = trim((string) ($r['Date'] ?? ''));
                $endDate   = trim((string) ($r['ENDDate'] ?? ''));

                // ข้ามพีเรียดที่ผ่านไปแล้ว
                if ($startDate === '' || $startDate < $today) {
                    continue;
                }

                $bookingCode = trim((string) ($r['Booking'] ?? ''));

                // 15 = CXL (ยกเลิกพีเรียด) → ไม่ sync เข้าระบบเลย
                if ($bookingCode === '15') {
                    continue;
                }

                // 0 = จองได้, 14 = ตัดกรุ๊ป (ยืนยันเดินทาง) → เปิดขาย
                // 4 = เต็ม/รอคิว, 16 = ปิดกรุ๊ป → ปิด
                $status = in_array($bookingCode, ['0', '14'], true)
                    ? self::STATUS_OPEN
                    : self::STATUS_CLOSED;

                $priceAdult     = $this->num($r['Adult']   ?? null);
                $priceChdBed    = $this->num($r['Chd+B']   ?? null);
                $priceChdNoBed  = $this->num($r['ChdNB']   ?? null);
                $priceSingle    = $this->num($r['Single']  ?? null);
                $deposit        = $this->num($r['Deposit'] ?? null);
                $commission     = $this->num($r['com']     ?? null);
                $commissionPlus = $this->num($r['complus'] ?? null);
                $promoPrice     = $this->num($r['Pro']     ?? null);
                $available      = (int) $this->num($r['AVBL'] ?? null);
                $capacity       = (int) $this->num($r['Size'] ?? null);

                if ($priceAdult <= 0) {
                    continue; // ไม่มีราคา = ไม่ขาย
                }

                if ($status === self::STATUS_OPEN) {
                    $totalAvailable += max(0, $available);
                    if ($nextDeparture === null || $startDate < $nextDeparture) {
                        $nextDeparture = $startDate;
                    }
                    if ($priceAdultOpen === null || $priceAdult < $priceAdultOpen) {
                        $priceAdultOpen = $priceAdult;
                    }
                }

                if ($minPriceAdult === null || $priceAdult < $minPriceAdult) {
                    $minPriceAdult = $priceAdult;
                }

                if ($durationDays === null) {
                    $d = $this->countDays($startDate, $endDate);
                    if ($d > 0) {
                        $durationDays = $d;
                    }
                }

                $departures[] = [
                    'external_id'       => (string) ($r['pid'] ?? ''),
                    'period_code'       => (string) ($r['pid'] ?? ''),
                    'start_date'        => $startDate,
                    'end_date'          => $endDate ?: null,
                    'capacity'          => $capacity ?: null,
                    'booked'            => ($capacity > 0)
                                            ? max(0, $capacity - $available)
                                            : null,
                    'available'         => $available,
                    'status'            => $status,
                    'price_adult'       => $priceAdult     ?: null,
                    'price_child'       => $priceChdBed    ?: null,
                    'price_child_nobed' => $priceChdNoBed  ?: null,
                    'price_single'      => $priceSingle    ?: null,
                    'deposit'           => $deposit        ?: null,
                    'commission_agent'  => $commission     ?: null,
                    'commission_plus'   => $commissionPlus ?: null,
                    'promo_price'       => $promoPrice     ?: null,
                ];
            }

            // ── ข้ามทัวร์ที่ไม่มีรอบเดินทางในอนาคต ───────────────────────────
            if (empty($departures)) {
                $skipped++;
                continue;
            }

            usort($departures, fn($a, $b) => strcmp($a['start_date'], $b['start_date']));

            $description   = trim((string) ($tour['story'] ?? ''));
            $startingPrice = $this->num($tour['startingprice'] ?? null);

            // ── ประกอบโครงสร้างมาตรฐาน ──────────────────────────────────────
            $normalized[] = [
                'tour' => [
                    'external_id'          => (string) $mainId,
                    'wholesaler_tour_code' => (string) ($tour['ProductCode'] ?? $mainId),
                    'title'                => $title ?: null,
                    'description'          => $description ?: null,
                    'primary_country_id'   => $primaryCountryId,
                    'transport_id'         => $transportId,
                    'cover_image_url'      => $this->absUrl($tour['jpg']  ?? null),
                    'pdf_url'              => $this->absUrl($tour['pdf']  ?? null),
                    'docx_url'             => $this->absUrl($tour['word'] ?? null),
                    'duration_days'        => $durationDays,
                    'duration_nights'      => $durationDays ? max(0, $durationDays - 1) : null,
                    'hotel_star'           => null,
                    'highlights'           => null,
                    'hashtags'             => null,
                    'departure_airports'   => null,
                    'region'               => $region,
                    'sub_region'           => $subRegion,
                    'price_adult'          => $priceAdultOpen ?? $minPriceAdult,
                    'min_price'            => $minPriceAdult ?: ($startingPrice ?: null),
                    'display_price'        => $minPriceAdult ?: ($startingPrice ?: null),
                    'next_departure_date'  => $nextDeparture,
                    'total_departures'     => count($departures),
                    'available_seats'      => $totalAvailable,
                ],
                'departure' => $departures,
                'itinerary' => [],   // API ไม่ส่งรายการรายวันมา
                'content'   => [
                    'description' => $description ?: null,
                    'highlights'  => null,
                ],
                'media'     => [
                    'cover_image_url' => $this->absUrl($tour['jpg'] ?? null),
                    'pdf_url'         => $this->absUrl($tour['pdf'] ?? null),
                ],
                'seo'       => [
                    'meta_title'       => $title ?: null,
                    'meta_description' => $description ? mb_substr($description, 0, 160) : null,
                    'keywords'         => null,
                    'hashtags'         => null,
                ],
                '_extra' => [
                    'airline_code'   => $airlineCode,
                    'airline_name'   => $airlineName ?: null,
                    'category_id'    => (string) ($tour['CategoryID'] ?? ''),
                    'category_name'  => (string) ($tour['Country'] ?? ''),
                    'countries_iso2' => $iso2List,
                    'visa'           => (string) ($tour['visa'] ?? ''),
                    'link'           => $this->absUrl($tour['Link'] ?? null),
                    'namelist'       => $this->absUrl($tour['Namelist'] ?? null),
                ],
            ];

            if ($sampleMode || ($limitN !== null && count($normalized) >= $limitN)) {
                break;
            }
        }

        \Illuminate\Support\Facades\Log::info('HeadcodeUniqueinterAdapter: Normalized tours ready', [
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

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS
    // ════════════════════════════════════════════════════════════════════

    /**
     * เติม domain ให้ relative path
     * API ส่ง pdf/word มาเป็น "catalog/PdfUIEU010_xxx.pdf"
     */
    private function absUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return self::API_HOST . ltrim($path, '/');
    }

    /** แปลงราคาที่ API ส่งมาเป็น string ให้เป็นตัวเลข */
    private function num($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    /** นับจำนวนวัน (รวมวันแรก) เช่น 2026-10-08 → 2026-10-17 = 10 วัน */
    private function countDays(?string $start, ?string $end): int
    {
        if (!$start || !$end) {
            return 0;
        }
        try {
            $days = (new \DateTime($start))->diff(new \DateTime($end))->days;
            return (int) $days + 1;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * แปลงชื่อสายการบินเต็ม → รหัส IATA 2 ตัว
     * API ส่งมาเป็น "Emirates" ไม่ใช่ "EK"
     */
    private function airlineNameToCode(string $name): ?string
    {
        static $map = [
            'EMIRATES' => 'EK', 'QATAR' => 'QR', 'ETIHAD' => 'EY',
            'THAI AIRWAYS' => 'TG', 'THAI AIRASIA' => 'FD', 'AIRASIA' => 'FD',
            'THAI VIETJET' => 'VZ', 'VIETJET' => 'VJ', 'THAI LION' => 'SL',
            'BANGKOK AIRWAYS' => 'PG', 'NOK AIR' => 'DD',
            'SINGAPORE AIRLINES' => 'SQ', 'SCOOT' => 'TR',
            'MALAYSIA AIRLINES' => 'MH', 'CATHAY' => 'CX',
            'EVA AIR' => 'BR', 'CHINA AIRLINES' => 'CI',
            'JAPAN AIRLINES' => 'JL', 'ALL NIPPON' => 'NH', 'ANA' => 'NH',
            'KOREAN AIR' => 'KE', 'ASIANA' => 'OZ',
            'AIR CHINA' => 'CA', 'CHINA EASTERN' => 'MU', 'CHINA SOUTHERN' => 'CZ',
            'VIETNAM AIRLINES' => 'VN', 'PHILIPPINE' => 'PR',
            'TURKISH' => 'TK', 'LUFTHANSA' => 'LH', 'SWISS' => 'LX',
            'AIR FRANCE' => 'AF', 'KLM' => 'KL', 'BRITISH AIRWAYS' => 'BA',
            'FINNAIR' => 'AY', 'AUSTRIAN' => 'OS', 'ITA AIRWAYS' => 'AZ',
            'AEROFLOT' => 'SU', 'OMAN AIR' => 'WY', 'GULF AIR' => 'GF',
            'SAUDIA' => 'SV', 'AIR INDIA' => 'AI', 'SRILANKAN' => 'UL',
            'QANTAS' => 'QF', 'AIR NEW ZEALAND' => 'NZ',
        ];

        $upper = mb_strtoupper(trim($name), 'UTF-8');

        // ถ้าส่งมาเป็นรหัส 2 ตัวอยู่แล้ว
        if (preg_match('/^[A-Z0-9]{2}$/', $upper)) {
            return $upper;
        }

        foreach ($map as $needle => $code) {
            if (str_contains($upper, $needle)) {
                return $code;
            }
        }
        return null;
    }

    /**
     * เดาประเทศจากชื่อทัวร์ → คืนรายการ ISO2
     * จำเป็นเพราะฟิลด์ Country ส่งมาเป็น "ทัวร์เส้นทางยุโรป" ซึ่ง lookup ไม่ได้
     *
     * เช่น "UIEU_010_SCANDINAVIA SWEDEN NORWAYS DENMARK 10 DAYS"
     *      → ['SE', 'NO', 'DK']
     */
    private function guessCountryIso2(string $title): array
    {
        static $map = [
            // คำยาวมาก่อนคำสั้น เพื่อไม่ให้จับผิด
            'NEW ZEALAND' => 'NZ', 'SOUTH KOREA' => 'KR', 'HONG KONG' => 'HK',
            'CZECH REPUBLIC' => 'CZ', 'UNITED KINGDOM' => 'GB',
            'SAUDI ARABIA' => 'SA', 'SOUTH AFRICA' => 'ZA',
            'JAPAN' => 'JP', 'KOREA' => 'KR', 'TAIWAN' => 'TW', 'CHINA' => 'CN',
            'HONGKONG' => 'HK', 'MACAU' => 'MO', 'VIETNAM' => 'VN',
            'SINGAPORE' => 'SG', 'MALAYSIA' => 'MY', 'INDONESIA' => 'ID',
            'PHILIPPINES' => 'PH', 'CAMBODIA' => 'KH', 'LAOS' => 'LA',
            'MYANMAR' => 'MM', 'INDIA' => 'IN', 'NEPAL' => 'NP', 'BHUTAN' => 'BT',
            'SRI LANKA' => 'LK', 'MALDIVES' => 'MV',
            'DUBAI' => 'AE', 'ABU DHABI' => 'AE', 'QATAR' => 'QA', 'OMAN' => 'OM',
            'JORDAN' => 'JO', 'ISRAEL' => 'IL', 'TURKEY' => 'TR', 'EGYPT' => 'EG',
            'MOROCCO' => 'MA', 'TUNISIA' => 'TN', 'KENYA' => 'KE',
            'GEORGIA' => 'GE', 'ARMENIA' => 'AM', 'AZERBAIJAN' => 'AZ',
            'UZBEKISTAN' => 'UZ', 'KAZAKHSTAN' => 'KZ',
            'FRANCE' => 'FR', 'ITALY' => 'IT', 'SWITZERLAND' => 'CH',
            'GERMANY' => 'DE', 'AUSTRIA' => 'AT', 'SPAIN' => 'ES',
            'PORTUGAL' => 'PT', 'NETHERLANDS' => 'NL', 'BELGIUM' => 'BE',
            'LUXEMBOURG' => 'LU', 'CZECH' => 'CZ', 'HUNGARY' => 'HU',
            'SLOVAKIA' => 'SK', 'SLOVENIA' => 'SI', 'POLAND' => 'PL',
            'CROATIA' => 'HR', 'GREECE' => 'GR', 'MONACO' => 'MC',
            'VATICAN' => 'VA', 'DOLOMITES' => 'IT',
            'SWEDEN' => 'SE', 'NORWAYS' => 'NO', 'NORWAY' => 'NO',
            'DENMARK' => 'DK', 'FINLAND' => 'FI', 'ICELAND' => 'IS',
            'ESTONIA' => 'EE', 'LATVIA' => 'LV', 'LITHUANIA' => 'LT',
            'ENGLAND' => 'GB', 'SCOTLAND' => 'GB', 'WALES' => 'GB',
            'IRELAND' => 'IE', 'RUSSIA' => 'RU',
            'AUSTRALIA' => 'AU', 'CANADA' => 'CA', 'USA' => 'US',
            'AMERICA' => 'US', 'MEXICO' => 'MX', 'BRAZIL' => 'BR', 'PERU' => 'PE',
        ];

        $upper = mb_strtoupper($title, 'UTF-8');
        $found = [];
        foreach ($map as $needle => $iso2) {
            if (str_contains($upper, $needle)) {
                $found[$iso2] = true;
            }
        }
        return array_keys($found);
    }

    /**
     * เดา region / sub_region จากหมวดเส้นทาง (ฟิลด์ Country) และ ISO2 ที่เจอ
     */
    private function guessRegion(string $categoryName, array $iso2List): array
    {
        // ลองจากชื่อหมวดภาษาไทยก่อน เช่น "ทัวร์เส้นทางยุโรป"
        if (str_contains($categoryName, 'ยุโรป'))      return ['EUROPE', null];
        if (str_contains($categoryName, 'อเมริกา'))    return ['AMERICAS', null];
        if (str_contains($categoryName, 'ออสเตรเลีย')) return ['OCEANIA', null];
        if (str_contains($categoryName, 'แอฟริกา'))    return ['AFRICA', null];

        static $regionMap = [
            'JP' => ['ASIA', 'EAST_ASIA'],  'KR' => ['ASIA', 'EAST_ASIA'],
            'TW' => ['ASIA', 'EAST_ASIA'],  'CN' => ['ASIA', 'EAST_ASIA'],
            'HK' => ['ASIA', 'EAST_ASIA'],  'MO' => ['ASIA', 'EAST_ASIA'],
            'VN' => ['ASIA', 'SOUTHEAST_ASIA'], 'SG' => ['ASIA', 'SOUTHEAST_ASIA'],
            'MY' => ['ASIA', 'SOUTHEAST_ASIA'], 'ID' => ['ASIA', 'SOUTHEAST_ASIA'],
            'PH' => ['ASIA', 'SOUTHEAST_ASIA'], 'KH' => ['ASIA', 'SOUTHEAST_ASIA'],
            'LA' => ['ASIA', 'SOUTHEAST_ASIA'], 'MM' => ['ASIA', 'SOUTHEAST_ASIA'],
            'IN' => ['ASIA', 'SOUTH_ASIA'], 'NP' => ['ASIA', 'SOUTH_ASIA'],
            'BT' => ['ASIA', 'SOUTH_ASIA'], 'LK' => ['ASIA', 'SOUTH_ASIA'],
            'MV' => ['ASIA', 'SOUTH_ASIA'],
            'AE' => ['MIDDLE_EAST', null], 'QA' => ['MIDDLE_EAST', null],
            'OM' => ['MIDDLE_EAST', null], 'JO' => ['MIDDLE_EAST', null],
            'IL' => ['MIDDLE_EAST', null], 'TR' => ['MIDDLE_EAST', null],
            'EG' => ['AFRICA', null], 'MA' => ['AFRICA', null],
            'TN' => ['AFRICA', null], 'KE' => ['AFRICA', null], 'ZA' => ['AFRICA', null],
            'AU' => ['OCEANIA', null], 'NZ' => ['OCEANIA', null],
            'US' => ['AMERICAS', null], 'CA' => ['AMERICAS', null],
            'MX' => ['AMERICAS', null], 'BR' => ['AMERICAS', null], 'PE' => ['AMERICAS', null],
        ];

        foreach ($iso2List as $iso2) {
            if (isset($regionMap[$iso2])) {
                return $regionMap[$iso2];
            }
        }

        // ที่เหลือในตารางคือประเทศยุโรป
        if (!empty($iso2List)) {
            return ['EUROPE', null];
        }

        return [null, null];
    }
}
