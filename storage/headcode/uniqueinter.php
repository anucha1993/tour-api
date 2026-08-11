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
 *     Size          → capacity ของกรุ๊ป ⚠ มาเป็นข้อความ "25+1" (ลูกค้า+หัวหน้าทัวร์)
 *                     → parse เอาเลขหน้า (25) เป็น capacity, เก็บดิบใน capacity_raw
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
 *     AVBL     → available ⚠ บางแถวเป็นข้อความสถานะ ("ตัดกรุ๊ป"/"ปิดกรุ๊ป"/"CXL") ไม่ใช่เลข
 *     Booking  → 0=จองได้ 4=เต็ม/รอคิว 14=ตัดกรุ๊ป 15=CXL 16=ปิดกรุ๊ป (มาเป็นข้อความไทยได้)
 *                ⚠ กติกา 2026-08-10: 4/14/16 → บังคับที่นั่งเหลือ 0 เสมอ, 15 → ไม่ sync
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
     * API ส่งช่อง Booking (และบางครั้ง AVBL) มาเป็นข้อความไทย/อังกฤษ
     * แทนที่จะเป็นรหัสตัวเลข → normalize เป็นรหัสก่อนตีความ
     * key เป็นตัวพิมพ์เล็กทั้งหมด (เทียบด้วย mb_strtolower)
     */
    private const BOOKING_TEXT_MAP = [
        'จองได้'    => '0',
        'เต็ม'      => '4',
        'รอคิว'     => '4',
        'full'      => '4',
        'ตัดกรุ๊ป'   => '14',
        'ตัดกรุ้ป'   => '14',   // เผื่อสะกดต่าง
        'cxl'       => '15',
        'ยกเลิก'    => '15',
        'cancel'    => '15',
        'ปิดกรุ๊ป'   => '16',
        'ปิดกรุ้ป'   => '16',   // เผื่อสะกดต่าง
        'close'     => '16',
        'closed'    => '16',
    ];

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

                // ── Booking มาได้ทั้งรหัสตัวเลขและข้อความไทย/อังกฤษ ──────────
                //   '0'  / จองได้     → เปิดขาย
                //   '4'  / เต็ม/รอคิว  → ปิด ที่นั่ง 0
                //   '14' / ตัดกรุ๊ป    → ปิด ที่นั่ง 0  (กติกา 2026-08-10: ตัดกรุ๊ปห้ามโชว์ที่นั่งเหลือ)
                //   '16' / ปิดกรุ๊ป    → ปิด ที่นั่ง 0
                //   '15' / CXL/ยกเลิก → ไม่ sync เข้าระบบเลย
                $bookingRaw  = trim((string) ($r['Booking'] ?? ''));
                $bookingCode = self::BOOKING_TEXT_MAP[mb_strtolower($bookingRaw, 'UTF-8')]
                    ?? $bookingRaw;

                // CXL (ยกเลิกพีเรียด) → ข้าม
                if ($bookingCode === '15') {
                    continue;
                }

                // เปิดขายเฉพาะสถานะจองได้เท่านั้น
                $status = ($bookingCode === '0')
                    ? self::STATUS_OPEN
                    : self::STATUS_CLOSED;

                // สถานะที่ต้องบังคับที่นั่งเหลือ = 0 เสมอ ไม่สนเลขใน AVBL
                // (API มักส่งเลข allotment เดิมค้างมา เช่น 20/30 ทั้งที่กรุ๊ปถูกตัด/ปิดแล้ว)
                $forceZeroSeat = in_array($bookingCode, ['4', '14', '16'], true);

                $priceAdult     = $this->num($r['Adult']   ?? null);
                $priceChdBed    = $this->num($r['Chd+B']   ?? null);
                $priceChdNoBed  = $this->num($r['ChdNB']   ?? null);
                $priceSingle    = $this->num($r['Single']  ?? null);
                $deposit        = $this->num($r['Deposit'] ?? null);
                $commission     = $this->num($r['com']     ?? null);
                $commissionPlus = $this->num($r['complus'] ?? null);
                $promoPrice     = $this->num($r['Pro']     ?? null);
                // ── AVBL: ตัวเลขปกติ หรือข้อความสถานะ (เช่น "ตัดกรุ๊ป", "ปิดกรุ๊ป", "CXL")
                $avblRaw = trim((string) ($r['AVBL'] ?? ''));
                if ($avblRaw !== '' && preg_match('/^\d+$/u', $avblRaw)) {
                    $available = (int) $avblRaw;                 // ตัวเลขปกติ
                } elseif ($avblRaw !== '') {
                    $avblCode = self::BOOKING_TEXT_MAP[mb_strtolower($avblRaw, 'UTF-8')] ?? null;
                    if ($avblCode === '15') {
                        continue;                                // CXL ในช่อง AVBL → ข้ามพีเรียดนี้
                    }
                    if ($avblCode === null) {
                        \Illuminate\Support\Facades\Log::warning(
                            'HeadcodeUniqueinterAdapter: AVBL รูปแบบไม่รู้จัก',
                            [
                                'wholesaler_id' => $this->wholesalerId,
                                'pid'           => (string) ($r['pid'] ?? ''),
                                'avbl_raw'      => $avblRaw,
                            ]
                        );
                    }
                    // ข้อความสถานะใด ๆ ในช่องที่นั่ง = ขายไม่ได้
                    $status        = self::STATUS_CLOSED;
                    $forceZeroSeat = true;
                    $available     = 0;
                } else {
                    $available = 0;                              // ว่าง → ไม่มีข้อมูลที่นั่ง
                }

                // ── กติกา 2026-08-10: ตัดกรุ๊ป/ปิดกรุ๊ป/เต็ม → ที่นั่งเหลือต้องเป็น 0 เสมอ ──
                if ($forceZeroSeat) {
                    $available = 0;
                }

                // เปิดขายแต่ที่นั่งเหลือ 0 = ขายไม่ได้จริง → ปิด กันสับสนหน้าเว็บ
                if ($status === self::STATUS_OPEN && $available <= 0) {
                    $status = self::STATUS_CLOSED;
                }

                // ── Size มาเป็นข้อความ เช่น "25+1" (25 ที่นั่งลูกค้า + 1 หัวหน้าทัวร์) ──
                // capacity = เฉพาะที่นั่งที่ขายลูกค้าได้ (ไม่รวมหัวหน้าทัวร์)
                // เก็บค่าดิบไว้ใน capacity_raw เพื่อตรวจย้อนหลัง
                $sizeRaw  = trim((string) ($r['Size'] ?? ''));
                $capacity = 0;
                if (preg_match('/^(\d+)\s*\+\s*(\d+)$/u', $sizeRaw, $sm)) {
                    $capacity = (int) $sm[1];                    // "25+1" → 25
                } elseif ($sizeRaw !== '' && preg_match('/^\d+$/u', $sizeRaw)) {
                    $capacity = (int) $sizeRaw;                  // "25" → 25
                } elseif ($sizeRaw !== '') {
                    // รูปแบบที่ไม่รู้จัก เช่น "FULL", "TBA" → log ไว้ อย่าแปลงเงียบ ๆ
                    \Illuminate\Support\Facades\Log::warning(
                        'HeadcodeUniqueinterAdapter: Size รูปแบบไม่รู้จัก',
                        [
                            'wholesaler_id' => $this->wholesalerId,
                            'pid'           => (string) ($r['pid'] ?? ''),
                            'size_raw'      => $sizeRaw,
                        ]
                    );
                    $capacity = (int) $this->num($sizeRaw);      // fallback แบบเดิม
                }

                // กันข้อมูลเพี้ยน: คงเหลือมากกว่าขนาดกรุ๊ปเป็นไปไม่ได้
                if ($capacity > 0 && $available > $capacity) {
                    \Illuminate\Support\Facades\Log::warning(
                        'HeadcodeUniqueinterAdapter: AVBL เกิน Size — ตรวจ mapping',
                        [
                            'wholesaler_id' => $this->wholesalerId,
                            'pid'           => (string) ($r['pid'] ?? ''),
                            'size_raw'      => $sizeRaw,
                            'capacity'      => $capacity,
                            'available'     => $available,
                        ]
                    );
                }

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
                    'capacity_raw'      => $sizeRaw ?: null,
                    // เมื่อบังคับที่นั่ง 0 (ตัดกรุ๊ป/ปิดกรุ๊ป/เต็ม) ตัวเลขจองจริงไม่รู้
                    // → null ดีกว่าคำนวณมั่วจาก capacity − 0
                    'booked'            => ($forceZeroSeat || $capacity <= 0)
                                            ? null
                                            : max(0, $capacity - $available),
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
     * ⚠ ชื่อไฟล์ของ Unique มีช่องว่าง เช่น "PdfUI-ASIA_001_Vietnam Danang Bana Hills.pdf"
     *   ต้อง encode เป็น %20 ไม่งั้น HTTP client ฝั่งเซิร์ฟเวอร์ดาวน์โหลดล้ม
     *   → ไฟล์ไม่เข้า R2 → ไม่ผ่าน PDF Branding
     */
    private function absUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        // แยกส่วน domain (ถ้ามี) ออกจาก path ก่อน encode
        $prefix = '';
        if (preg_match('#^(https?://[^/]+/)(.*)$#i', $path, $m)) {
            $prefix = $m[1];
            $path   = $m[2];
        }

        // encode ทีละ segment (คงเครื่องหมาย / ไว้)
        // ถ้ามี % อยู่แล้ว = ถูก encode มาก่อน ไม่ encode ซ้ำ
        if (!str_contains($path, '%')) {
            $path = implode('/', array_map('rawurlencode', explode('/', $path)));
        }

        if ($prefix !== '') {
            return $prefix . $path;
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
     *
     * ⚠ เวอร์ชันใหม่ (2026-08-11): ใช้ DB `transports` เป็นแหล่งหลัก แทน hardcoded array
     *   1) ถ้า API ส่งรหัส 2 ตัว (เช่น "EK") → ใช้ตรง ๆ
     *   2) ค้นชื่อใน `transports.name` (case-insensitive, partial match)
     *      แล้วอ่านรหัสจาก `transports.code` / `transports.code1`
     *   3) ถ้ายังไม่เจอ ใช้ alias สั้น ๆ เฉพาะกรณีที่ API ส่งชื่อเล่นที่ DB ไม่มี
     *      (เช่น "ANA" ที่ DB เก็บชื่อเต็ม "All Nippon Airways")
     */
    private function airlineNameToCode(string $name): ?string
    {
        $upper = mb_strtoupper(trim($name), 'UTF-8');
        if ($upper === '') return null;

        // ถ้าส่งมาเป็นรหัส 2 ตัวอยู่แล้ว
        if (preg_match('/^[A-Z0-9]{2}$/', $upper)) {
            return $upper;
        }

        // 1) ALIAS ก่อน — API ส่งชื่อเล่นสั้น ๆ ที่ DB มักเก็บชื่อเต็ม
        //    เช่น "ANA" → NH (DB name = "All Nippon Airways" → LIKE '%ANA%' จะจับผิด)
        static $aliases = [
            'ANA'      => 'NH',
            'AIRASIA'  => 'FD',
            'CATHAY'   => 'CX',
            'SCOOT'    => 'TR',
        ];
        foreach ($aliases as $needle => $code) {
            if (str_contains($upper, $needle)) return $code;
        }

        // 2) DB exact match ก่อน (ทั้งบรรทัด)
        $transport = \App\Models\Transport::query()
            ->whereRaw('UPPER(name) = ?', [$upper])
            ->first(['code', 'code1']);
        if ($transport) {
            return $transport->code ?: $transport->code1;
        }

        // 3) DB partial match — ชื่อทัวร์บางครั้งมีคำเสริม เช่น "Emirates Skywards"
        //    ⚠ ใช้เป็นทางเลือกสุดท้าย เพราะ LIKE อาจจับผิดชื่อสายการบินที่คล้ายกัน
        $transport = \App\Models\Transport::query()
            ->where('name', 'like', '%' . trim($name) . '%')
            ->first(['code', 'code1']);
        if ($transport) {
            return $transport->code ?: $transport->code1;
        }

        return null;
    }

    /**
     * เดาประเทศจากชื่อทัวร์ → คืนรายการ ISO2
     * จำเป็นเพราะฟิลด์ Country ส่งมาเป็น "ทัวร์เส้นทางยุโรป" ซึ่ง lookup ไม่ได้
     *
     * ⚠ เวอร์ชันใหม่ (2026-08-11): scan ชื่อทัวร์เทียบกับ `countries.name_en` ใน DB
     *   แทน hardcoded array ~60 รายการ → เพิ่มประเทศได้จากหน้า admin ไม่ต้องแก้ code
     *
     * รักษาไว้เฉพาะ ALIASES ที่ไม่ตรงชื่อประเทศจริง เช่น
     *   "SCANDINAVIA" → [SE, NO, DK]     (กลุ่มประเทศ ไม่ใช่ประเทศเดียว)
     *   "DOLOMITES"   → [IT]              (แคว้นในอิตาลี)
     *   "ENGLAND/SCOTLAND/WALES" → [GB]   (subregion → รวมเป็น UK)
     *   "NORWAYS"     → [NO]              (สะกดผิดที่ API ส่งบ่อย)
     *
     * เช่น "UIEU_010_SCANDINAVIA SWEDEN NORWAYS DENMARK 10 DAYS"
     *      → ['SE', 'NO', 'DK']
     */
    private function guessCountryIso2(string $title): array
    {
        $upper = mb_strtoupper($title, 'UTF-8');
        if ($upper === '') return [];

        $found = [];

        // 1) ALIASES ก่อน (region names / กลุ่มประเทศ / subregion → country)
        //    ตรวจก่อน DB เพราะ "Scandinavia" ไม่ใช่ชื่อประเทศใน DB
        static $aliases = [
            'SCANDINAVIA'    => ['SE', 'NO', 'DK'],
            'BENELUX'        => ['BE', 'NL', 'LU'],
            'BALTIC'         => ['EE', 'LV', 'LT'],
            'DOLOMITES'      => ['IT'],
            'ENGLAND'        => ['GB'],
            'SCOTLAND'       => ['GB'],
            'WALES'          => ['GB'],
            'UNITED KINGDOM' => ['GB'],
            'NORWAYS'        => ['NO'],   // สะกดผิดของ API
            'HONGKONG'       => ['HK'],   // ไม่มี space
            'USA'            => ['US'],
            'AMERICA'        => ['US'],
            'DUBAI'          => ['AE'],
            'ABU DHABI'      => ['AE'],
        ];
        foreach ($aliases as $needle => $iso2s) {
            if (str_contains($upper, $needle)) {
                foreach ($iso2s as $iso2) $found[$iso2] = true;
            }
        }

        // 2) DB scan — เทียบชื่อประเทศจริงจาก `countries.name_en`
        //    static cache ใน request เดียว เพื่อเลี่ยง query ซ้ำต่อทัวร์
        static $countryList = null;
        if ($countryList === null) {
            $countryList = \App\Models\Country::query()
                ->where('is_active', true)
                ->get(['iso2', 'name_en'])
                ->map(fn ($c) => [
                    'iso2'   => strtoupper((string) $c->iso2),
                    'needle' => mb_strtoupper((string) $c->name_en, 'UTF-8'),
                ])
                // ยาวก่อนสั้น เพื่อไม่ให้ "GEORGIA" (GE) จับก่อน "SOUTH GEORGIA" (GS)
                ->sortByDesc(fn ($c) => mb_strlen($c['needle']))
                ->values()
                ->all();
        }
        foreach ($countryList as $c) {
            if ($c['needle'] === '' || $c['iso2'] === '') continue;
            // ต้องเป็นคำเต็ม (ขอบด้วยตัวอักษรไม่ใช่ตัวอักษร) เพื่อกัน "INDIA" จับ "INDIANAPOLIS"
            if (preg_match('/(^|[^A-Z])' . preg_quote($c['needle'], '/') . '([^A-Z]|$)/u', $upper)) {
                $found[$c['iso2']] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * เดา region / sub_region จากหมวดเส้นทาง (ฟิลด์ Country) และ ISO2 ที่เจอ
     *
     * ⚠ เวอร์ชันใหม่ (2026-08-11): อ่าน region จาก `countries.region` ของ ISO2 ตัวแรก
     *   ที่เจอ แทน hardcoded regionMap ~35 รายการ
     */
    private function guessRegion(string $categoryName, array $iso2List): array
    {
        // 1) ลองจากชื่อหมวดภาษาไทยก่อน เช่น "ทัวร์เส้นทางยุโรป"
        if (str_contains($categoryName, 'ยุโรป'))      return ['EUROPE', null];
        if (str_contains($categoryName, 'อเมริกา'))    return ['AMERICAS', null];
        if (str_contains($categoryName, 'ออสเตรเลีย')) return ['OCEANIA', null];
        if (str_contains($categoryName, 'แอฟริกา'))    return ['AFRICA', null];

        // 2) อ่าน region จาก DB ของ ISO2 ตัวแรกที่เจอ
        //    static cache ต่อ request เพื่อไม่ query ซ้ำ
        static $regionCache = [];
        foreach ($iso2List as $iso2) {
            if (!array_key_exists($iso2, $regionCache)) {
                $regionCache[$iso2] = \Illuminate\Support\Facades\DB::table('countries')
                    ->where('iso2', strtoupper((string) $iso2))
                    ->value('region');
            }
            if (!empty($regionCache[$iso2])) {
                return [$regionCache[$iso2], null];
            }
        }

        return [null, null];
    }
}
