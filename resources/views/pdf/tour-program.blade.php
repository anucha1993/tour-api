<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <style>
        /* ===== Base Styles ===== */
        body {
            font-family: 'garuda', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
        }

        h1, h2, h3, h4 { margin: 0; padding: 0; }

        /* ===== Cover Section ===== */
        .cover-section {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
        }
        .cover-section h1 {
            font-size: 22pt;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .cover-section .tour-code {
            font-size: 14pt;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        .cover-section .tour-meta {
            font-size: 11pt;
            opacity: 0.85;
        }

        /* ===== Cover Image ===== */
        .cover-image-container {
            text-align: center;
            margin-bottom: 25px;
        }
        .cover-image-container img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
        }

        /* ===== Info Box ===== */
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-grid td {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .info-grid .label {
            background-color: #fff7ed;
            color: #9a3412;
            font-weight: bold;
            width: 30%;
            white-space: nowrap;
        }
        .info-grid .value {
            color: #374151;
        }

        /* ===== Section Headers ===== */
        .section-header {
            background-color: #f97316;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            margin: 25px 0 12px 0;
            font-size: 13pt;
            font-weight: bold;
        }
        .section-header-alt {
            background-color: #1e3a5f;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            margin: 25px 0 12px 0;
            font-size: 13pt;
            font-weight: bold;
        }

        /* ===== Itinerary ===== */
        .itinerary-day {
            margin-bottom: 18px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .itinerary-day-header {
            background-color: #fff7ed;
            padding: 10px 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        .itinerary-day-header .day-num {
            color: #f97316;
            font-weight: bold;
            font-size: 12pt;
        }
        .itinerary-day-header .day-title {
            color: #1a1a1a;
            font-weight: bold;
            font-size: 12pt;
        }
        .itinerary-day-body {
            padding: 12px 16px;
        }
        .itinerary-desc {
            color: #374151;
            line-height: 1.7;
        }

        /* Meals & Hotel row */
        .meals-row {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #d1d5db;
            font-size: 10pt;
            color: #6b7280;
        }
        .meal-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 9pt;
            margin-right: 4px;
        }
        .meal-yes { background-color: #dcfce7; color: #166534; }
        .meal-no { background-color: #f3f4f6; color: #9ca3af; text-decoration: line-through; }
        .hotel-info {
            color: #4b5563;
            font-style: italic;
        }

        /* ===== Highlights List ===== */
        .highlights-list {
            margin: 0;
            padding-left: 2px;
            list-style: none;
        }
        .highlights-list li {
            padding: 4px 0;
            color: #374151;
        }
        .highlights-list li::before {
            content: "★ ";
            color: #f97316;
        }

        /* ===== Periods Table ===== */
        .periods-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 10pt;
        }
        .periods-table th {
            background-color: #f97316;
            color: #fff;
            padding: 8px 10px;
            text-align: center;
            font-weight: bold;
        }
        .periods-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: center;
        }
        .periods-table tr:nth-child(even) {
            background-color: #fff7ed;
        }
        .price-highlight {
            color: #dc2626;
            font-weight: bold;
            font-size: 11pt;
        }
        .discount-badge {
            background-color: #fef2f2;
            color: #dc2626;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9pt;
        }

        /* ===== Inclusions / Exclusions ===== */
        .inc-exc-table {
            width: 100%;
            border-collapse: collapse;
        }
        .inc-exc-table td {
            vertical-align: top;
            padding: 12px;
            width: 50%;
        }
        .inc-box {
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 12px;
            background-color: #f0fdf4;
        }
        .inc-box h4 { color: #166534; margin-bottom: 8px; }
        .exc-box {
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 12px;
            background-color: #fef2f2;
        }
        .exc-box h4 { color: #991b1b; margin-bottom: 8px; }

        /* ===== Conditions ===== */
        .conditions-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            background-color: #f9fafb;
            font-size: 10pt;
            line-height: 1.6;
            color: #4b5563;
        }

        /* ===== Footer ===== */
        .footer {
            text-align: center;
            font-size: 9pt;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            margin-top: 30px;
        }

        /* Page break helper */
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

    {{-- ======= COVER SECTION ======= --}}
    <div class="cover-section">
        <div class="tour-code">{{ $tour->tour_code }}</div>
        <h1>{{ $tour->title }}</h1>
        <div class="tour-meta">
            {{ $tour->duration_days }} วัน {{ $tour->duration_nights }} คืน
            @if($countries->isNotEmpty())
                &bull; {{ $countries->pluck('name_th')->filter()->join(', ') ?: $countries->pluck('name_en')->join(', ') }}
            @endif
        </div>
    </div>

    {{-- Cover Image --}}
    @if($tour->effective_cover_image_url)
    <div class="cover-image-container">
        <img src="{{ $tour->effective_cover_image_url }}" alt="{{ $tour->effective_cover_image_alt ?? $tour->title }}" />
    </div>
    @endif

    {{-- ======= TOUR INFO ======= --}}
    <table class="info-grid">
        <tr>
            <td class="label">รหัสทัวร์</td>
            <td class="value">{{ $tour->tour_code }}</td>
            <td class="label">ระยะเวลา</td>
            <td class="value">{{ $tour->duration_days }} วัน {{ $tour->duration_nights }} คืน</td>
        </tr>
        <tr>
            <td class="label">ประเทศ</td>
            <td class="value">{{ $countries->pluck('name_th')->filter()->join(', ') ?: $countries->pluck('name_en')->join(', ') }}</td>
            <td class="label">เมือง</td>
            <td class="value">{{ $cities->pluck('name_th')->filter()->join(', ') ?: $cities->pluck('name_en')->join(', ') ?: '-' }}</td>
        </tr>
        @if($tour->min_price)
        <tr>
            <td class="label">ราคาเริ่มต้น</td>
            <td class="value" style="color: #dc2626; font-weight: bold; font-size: 13pt;">
                ฿{{ number_format($tour->min_price, 0) }}
                @if($tour->discount_adult && $tour->discount_adult > 0)
                    <span style="font-size: 10pt; color: #6b7280; text-decoration: line-through; margin-left: 5px;">
                        ฿{{ number_format($tour->price_adult, 0) }}
                    </span>
                @endif
            </td>
            <td class="label">โรงแรม</td>
            <td class="value">
                @if($tour->hotel_star)
                    {{ $tour->hotel_star }} ดาว
                    @if($tour->hotel_star_min && $tour->hotel_star_max && $tour->hotel_star_min != $tour->hotel_star_max)
                        ({{ $tour->hotel_star_min }}-{{ $tour->hotel_star_max }} ดาว)
                    @endif
                @else
                    -
                @endif
            </td>
        </tr>
        @endif
        @if($tour->tour_type)
        <tr>
            <td class="label">ประเภท</td>
            <td class="value">{{ ucfirst($tour->tour_type) }}</td>
            <td class="label">สายการบิน</td>
            <td class="value">
                @if($tour->transports && $tour->transports->isNotEmpty())
                    {{ $tour->transports->map(fn($t) => $t->transport?->name)->filter()->join(', ') }}
                @else
                    -
                @endif
            </td>
        </tr>
        @endif
    </table>

    {{-- ======= HIGHLIGHTS ======= --}}
    @if(($tour->highlights && count($tour->highlights) > 0) || ($tour->shopping_highlights && count($tour->shopping_highlights) > 0) || ($tour->food_highlights && count($tour->food_highlights) > 0))
    <div class="section-header">✨ ไฮไลท์โปรแกรมทัวร์</div>

    @if($tour->highlights && count($tour->highlights) > 0)
    <ul class="highlights-list">
        @foreach($tour->highlights as $h)
            <li>{{ $h }}</li>
        @endforeach
    </ul>
    @endif

    @if($tour->shopping_highlights && count($tour->shopping_highlights) > 0)
    <div style="margin-top: 10px;">
        <strong style="color: #f97316;">🛍️ ไฮไลท์ช้อปปิ้ง:</strong>
        <ul class="highlights-list">
            @foreach($tour->shopping_highlights as $h)
                <li>{{ $h }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($tour->food_highlights && count($tour->food_highlights) > 0)
    <div style="margin-top: 10px;">
        <strong style="color: #f97316;">🍜 ไฮไลท์อาหาร:</strong>
        <ul class="highlights-list">
            @foreach($tour->food_highlights as $h)
                <li>{{ $h }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    @endif

    {{-- ======= ITINERARY ======= --}}
    @if($itineraries->isNotEmpty())
    <div class="page-break"></div>
    <div class="section-header">📅 รายละเอียดโปรแกรมทัวร์</div>

    @foreach($itineraries as $itinerary)
    <div class="itinerary-day">
        <div class="itinerary-day-header">
            <span class="day-num">วันที่ {{ $itinerary->day_number }}</span>
            @if($itinerary->title)
            <span class="day-title"> — {{ $itinerary->title }}</span>
            @endif
        </div>
        <div class="itinerary-day-body">
            @if($itinerary->description)
            <div class="itinerary-desc">{!! nl2br(e($itinerary->description)) !!}</div>
            @endif

            @if($itinerary->places && count($itinerary->places) > 0)
            <div style="margin-top: 8px; color: #6b7280; font-size: 10pt;">
                📍 สถานที่: {{ collect($itinerary->places)->join(', ') }}
            </div>
            @endif

            <div class="meals-row">
                <span class="meal-badge {{ $itinerary->has_breakfast ? 'meal-yes' : 'meal-no' }}">
                    เช้า {{ $itinerary->has_breakfast ? '✓' : '✗' }}
                </span>
                <span class="meal-badge {{ $itinerary->has_lunch ? 'meal-yes' : 'meal-no' }}">
                    กลางวัน {{ $itinerary->has_lunch ? '✓' : '✗' }}
                </span>
                <span class="meal-badge {{ $itinerary->has_dinner ? 'meal-yes' : 'meal-no' }}">
                    เย็น {{ $itinerary->has_dinner ? '✓' : '✗' }}
                </span>
                @if($itinerary->accommodation)
                    &nbsp;&bull;&nbsp;
                    <span class="hotel-info">🏨 {{ $itinerary->accommodation }}
                        @if($itinerary->hotel_star)
                            ({{ $itinerary->hotel_star }}★)
                        @endif
                    </span>
                @endif
            </div>
        </div>
    </div>
    @endforeach
    @endif

    {{-- ======= DEPARTURE PERIODS ======= --}}
    @if($periods->isNotEmpty())
    <div class="page-break"></div>
    <div class="section-header">📆 ช่วงเวลาเดินทาง & ราคา</div>

    <table class="periods-table">
        <thead>
            <tr>
                <th>วันเดินทาง</th>
                <th>วันกลับ</th>
                <th>ราคาผู้ใหญ่</th>
                <th>ส่วนลด</th>
                <th>ราคาสุทธิ</th>
                <th>ที่นั่ง</th>
            </tr>
        </thead>
        <tbody>
            @foreach($periods as $period)
            <tr>
                <td>{{ \Carbon\Carbon::parse($period->start_date)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($period->end_date)->format('d/m/Y') }}</td>
                <td>
                    @if($period->offer)
                        ฿{{ number_format($period->offer->price_adult ?? 0, 0) }}
                    @else
                        -
                    @endif
                </td>
                <td>
                    @if($period->offer && $period->offer->discount_adult > 0)
                        <span class="discount-badge">-฿{{ number_format($period->offer->discount_adult, 0) }}</span>
                    @else
                        -
                    @endif
                </td>
                <td>
                    @if($period->offer)
                        @php
                            $net = ($period->offer->price_adult ?? 0) - ($period->offer->discount_adult ?? 0);
                        @endphp
                        <span class="price-highlight">฿{{ number_format($net, 0) }}</span>
                    @else
                        -
                    @endif
                </td>
                <td>{{ $period->available ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ======= INCLUSIONS / EXCLUSIONS ======= --}}
    @if($tour->inclusions || $tour->exclusions)
    <div class="section-header-alt">📋 อัตราค่าบริการรวม / ไม่รวม</div>

    <table class="inc-exc-table">
        <tr>
            @if($tour->inclusions)
            <td>
                <div class="inc-box">
                    <h4>✅ อัตราค่าบริการรวม</h4>
                    <div>{!! nl2br(e($tour->inclusions)) !!}</div>
                </div>
            </td>
            @endif
            @if($tour->exclusions)
            <td>
                <div class="exc-box">
                    <h4>❌ อัตราค่าบริการไม่รวม</h4>
                    <div>{!! nl2br(e($tour->exclusions)) !!}</div>
                </div>
            </td>
            @endif
        </tr>
    </table>
    @endif

    {{-- ======= CONDITIONS ======= --}}
    @if($tour->conditions)
    <div class="section-header-alt">📜 เงื่อนไขการจอง</div>
    <div class="conditions-box">
        {!! nl2br(e($tour->conditions)) !!}
    </div>
    @endif

    {{-- ======= FOOTER ======= --}}
    <div class="footer">
        <p>เอกสารนี้สร้างอัตโนมัติจากระบบ NowTravel เมื่อ {{ $generatedAt }} — ข้อมูลอาจมีการเปลี่ยนแปลง กรุณาตรวจสอบข้อมูลล่าสุดก่อนจองทัวร์</p>
    </div>

</body>
</html>
