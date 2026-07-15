<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Tour;
use App\Models\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\PeriodDisplayFilter;

class FlashSaleController extends Controller
{
    // ═══════════════════════════════════════════
    //  ADMIN CRUD
    // ═══════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $query = FlashSale::withCount('items');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $flashSales = $query->orderBy('sort_order')->orderByDesc('created_at')->get();

        $flashSales->each(function ($fs) {
            $fs->append('status_label');
        });

        return response()->json([
            'success' => true,
            'data' => $flashSales,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner_image_url' => 'nullable|string|max:500',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $flashSale = FlashSale::create($validated);

        return response()->json([
            'success' => true,
            'data' => $flashSale,
            'message' => 'สร้าง Flash Sale สำเร็จ',
        ], 201);
    }

    public function show(FlashSale $flashSale): JsonResponse
    {
        $flashSale->load([
            'items.tour:id,title,slug,tour_code,cover_image_url,min_price,price_adult,max_discount_percent,status',
            'items.period:id,tour_id,start_date,end_date,capacity,booked,available,status',
            'items.period.offer:id,period_id,price_adult',
        ]);

        $flashSale->append('status_label');

        return response()->json([
            'success' => true,
            'data' => $flashSale,
        ]);
    }

    public function update(Request $request, FlashSale $flashSale): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'banner_image_url' => 'nullable|string|max:500',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $flashSale->update($validated);

        return response()->json([
            'success' => true,
            'data' => $flashSale,
            'message' => 'อัปเดต Flash Sale สำเร็จ',
        ]);
    }

    public function destroy(FlashSale $flashSale): JsonResponse
    {
        $flashSale->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบ Flash Sale สำเร็จ',
        ]);
    }

    public function toggleStatus(FlashSale $flashSale): JsonResponse
    {
        $flashSale->update(['is_active' => !$flashSale->is_active]);

        return response()->json([
            'success' => true,
            'data' => $flashSale,
            'message' => $flashSale->is_active ? 'เปิดใช้งาน Flash Sale แล้ว' : 'ปิดใช้งาน Flash Sale แล้ว',
        ]);
    }

    // ═══════════════════════════════════════════
    //  ITEMS MANAGEMENT (per-period)
    // ═══════════════════════════════════════════

    public function addItem(Request $request, FlashSale $flashSale): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:periods,id',
            'flash_price' => 'nullable|numeric|min:0',
            'flash_end_date' => 'nullable|date',
            'quantity_limit' => 'nullable|integer|min:1',
            'sort_order' => 'integer',
        ]);

        // Check duplicate period in this flash sale
        $exists = $flashSale->items()->where('period_id', $validated['period_id'])->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'รอบเดินทางนี้มีอยู่ใน Flash Sale นี้แล้ว',
            ], 422);
        }

        $period = Period::with('offer', 'tour')->findOrFail($validated['period_id']);
        $originalPrice = $period->offer->price_adult ?? $period->tour->min_price ?? $period->tour->price_adult ?? 0;
        $flashPrice = $validated['flash_price'] ?? $originalPrice;

        $discountPercent = 0;
        if ($originalPrice > 0 && $flashPrice < $originalPrice) {
            $discountPercent = round((($originalPrice - $flashPrice) / $originalPrice) * 100, 1);
        }

        $item = $flashSale->items()->create([
            'tour_id' => $period->tour_id,
            'period_id' => $validated['period_id'],
            'flash_price' => $flashPrice,
            'original_price' => $originalPrice,
            'discount_percent' => $discountPercent,
            'flash_end_date' => $validated['flash_end_date'] ?? $flashSale->end_date,
            'quantity_limit' => $validated['quantity_limit'] ?? null,
            'quantity_sold' => 0,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        $item->load([
            'tour:id,title,slug,tour_code,cover_image_url,min_price,price_adult,status',
            'period:id,tour_id,start_date,end_date,capacity,booked,available,status',
            'period.offer:id,period_id,price_adult',
        ]);

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'เพิ่มรอบเดินทางลง Flash Sale สำเร็จ',
        ], 201);
    }

    /**
     * Batch add multiple periods at once (from period table selection UI)
     */
    public function addItems(Request $request, FlashSale $flashSale): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.period_id' => 'required|exists:periods,id',
            'items.*.flash_price' => 'nullable|numeric|min:0',
            'items.*.flash_end_date' => 'nullable|date',
            'items.*.quantity_limit' => 'nullable|integer|min:1',
        ]);

        $added = 0;
        $skipped = 0;

        foreach ($validated['items'] as $itemData) {
            $exists = $flashSale->items()->where('period_id', $itemData['period_id'])->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            $period = Period::with('offer', 'tour')->findOrFail($itemData['period_id']);
            $originalPrice = $period->offer->price_adult ?? $period->tour->min_price ?? $period->tour->price_adult ?? 0;
            $flashPrice = $itemData['flash_price'] ?? $originalPrice;

            $discountPercent = 0;
            if ($originalPrice > 0 && $flashPrice < $originalPrice) {
                $discountPercent = round((($originalPrice - $flashPrice) / $originalPrice) * 100, 1);
            }

            $flashSale->items()->create([
                'tour_id' => $period->tour_id,
                'period_id' => $itemData['period_id'],
                'flash_price' => $flashPrice,
                'original_price' => $originalPrice,
                'discount_percent' => $discountPercent,
                'flash_end_date' => $itemData['flash_end_date'] ?? $flashSale->end_date,
                'quantity_limit' => $itemData['quantity_limit'] ?? null,
                'quantity_sold' => 0,
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $added++;
        }

        return response()->json([
            'success' => true,
            'message' => "เพิ่ม {$added} รอบเดินทางสำเร็จ" . ($skipped > 0 ? " (ข้าม {$skipped} รอบที่ซ้ำ)" : ''),
            'added' => $added,
            'skipped' => $skipped,
        ], 201);
    }

    public function updateItem(Request $request, FlashSale $flashSale, FlashSaleItem $item): JsonResponse
    {
        $validated = $request->validate([
            'flash_price' => 'nullable|numeric|min:0',
            'flash_end_date' => 'nullable|date',
            'quantity_limit' => 'nullable|integer|min:1',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ]);

        // Sync original_price from the period's current offer
        if ($item->period_id) {
            $period = Period::with('offer', 'tour')->find($item->period_id);
            if ($period && $period->offer) {
                $validated['original_price'] = $period->offer->price_adult;
            }
        }

        if (isset($validated['flash_price'])) {
            $originalPrice = $validated['original_price'] ?? $item->original_price;
            $flashPrice = $validated['flash_price'];
            $discountPercent = 0;
            if ($originalPrice > 0 && $flashPrice < $originalPrice) {
                $discountPercent = round((($originalPrice - $flashPrice) / $originalPrice) * 100, 1);
            }
            $validated['discount_percent'] = $discountPercent;
        }

        $item->update($validated);

        // Return item with period relationship
        $item->load([
            'tour:id,title,slug,tour_code,cover_image_url,min_price,price_adult,status',
            'period:id,tour_id,start_date,end_date,capacity,booked,available,status',
            'period.offer:id,period_id,price_adult',
        ]);

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'อัปเดตรอบเดินทาง Flash Sale สำเร็จ',
        ]);
    }

    public function removeItem(FlashSale $flashSale, FlashSaleItem $item): JsonResponse
    {
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบรายการออกจาก Flash Sale สำเร็จ',
        ]);
    }

    public function massUpdateDiscount(Request $request, FlashSale $flashSale): JsonResponse
    {
        $validated = $request->validate([
            'discount_type' => 'required_without:flash_end_date|in:percent,amount',
            'discount_value' => 'required_with:discount_type|numeric|min:0',
            'flash_end_date' => 'nullable|date',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer',
        ]);

        $type = $validated['discount_type'] ?? null;
        $value = $validated['discount_value'] ?? null;
        $flashEndDate = $validated['flash_end_date'] ?? null;

        $query = $flashSale->items();
        if (!empty($validated['item_ids'])) {
            $query->whereIn('id', $validated['item_ids']);
        }
        $items = $query->with('period.offer')->get();
        $updated = 0;

        foreach ($items as $item) {
            $updateData = [];

            // Sync original_price from period's current offer
            $originalPrice = $item->original_price;
            if ($item->period && $item->period->offer) {
                $originalPrice = $item->period->offer->price_adult;
                $updateData['original_price'] = $originalPrice;
            }

            // Update discount/price if provided
            if ($type && $value !== null) {
                if ($originalPrice && $originalPrice > 0) {
                    if ($type === 'percent') {
                        $flashPrice = round($originalPrice * (1 - $value / 100));
                        $discountPercent = $value;
                    } else {
                        $flashPrice = max(0, $originalPrice - $value);
                        $discountPercent = round(($value / $originalPrice) * 100, 1);
                    }
                    $updateData['flash_price'] = $flashPrice;
                    $updateData['discount_percent'] = $discountPercent;
                }
            }

            // Update flash_end_date if provided
            if (array_key_exists('flash_end_date', $validated)) {
                $updateData['flash_end_date'] = $flashEndDate;
            }

            if (!empty($updateData)) {
                $item->update($updateData);
                $updated++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "อัปเดตทั้งหมด {$updated} รายการสำเร็จ",
            'updated_count' => $updated,
        ]);
    }

    public function reorderItems(Request $request, FlashSale $flashSale): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['items'] as $itemData) {
            FlashSaleItem::where('id', $itemData['id'])
                ->where('flash_sale_id', $flashSale->id)
                ->update(['sort_order' => $itemData['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'เรียงลำดับรายการสำเร็จ',
        ]);
    }

    // ═══════════════════════════════════════════
    //  SEARCH TOURS → PERIODS (for admin selection)
    // ═══════════════════════════════════════════

    public function searchTours(Request $request): JsonResponse
    {
        $q = $request->input('q', '');
        $query = Tour::where('status', 'active')
            ->select('id', 'title', 'slug', 'tour_code', 'cover_image_url', 'min_price', 'price_adult', 'max_discount_percent', 'status');

        if ($q) {
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                    ->orWhere('tour_code', 'like', "%{$q}%");
            });
        }

        $tours = $query->with(['periods' => function ($pq) {
            $pq->where('status', 'open')
               ->where('start_date', '>=', now()->toDateString())
               ->with('offer:id,period_id,price_adult')
               ->orderBy('start_date');
        }])
        ->orderBy('title')
        ->limit(20)
        ->get();

        // Transform to include periods info
        $result = $tours->map(function ($tour) {
            return [
                'id' => $tour->id,
                'title' => $tour->title,
                'slug' => $tour->slug,
                'tour_code' => $tour->tour_code,
                'cover_image_url' => $tour->cover_image_url,
                'min_price' => $tour->min_price,
                'price_adult' => $tour->price_adult,
                'max_discount_percent' => $tour->max_discount_percent,
                'status' => $tour->status,
                'periods' => $tour->periods->map(function ($period) {
                    return [
                        'id' => $period->id,
                        'start_date' => $period->start_date->format('Y-m-d'),
                        'end_date' => $period->end_date->format('Y-m-d'),
                        'capacity' => $period->capacity,
                        'booked' => $period->booked,
                        'available' => $period->available,
                        'status' => $period->status,
                        'price_adult' => $period->offer?->price_adult,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    // ═══════════════════════════════════════════
    //  PUBLIC API
    // ═══════════════════════════════════════════

    public function publicActive(): JsonResponse
    {
        $flashSales = FlashSale::active()
            ->where('end_date', '>=', now())
            ->with(['items' => function ($q) {
                $q->where('is_active', true)
                    ->where(function ($sq) {
                        // Only show items whose flash_end_date hasn't passed (or is null → use campaign end)
                        $sq->whereNull('flash_end_date')
                           ->orWhere('flash_end_date', '>=', now());
                    })
                    ->orderBy('sort_order')
                    ->with([
                        'tour' => function ($tq) {
                            $tq->where('status', 'active')
                                ->with(['transports.transport', 'country']);
                        },
                        'period:id,tour_id,start_date,end_date,capacity,booked,available,status',
                        'period.offer:id,period_id,price_adult',
                    ]);
            }])
            ->orderByRaw('CASE WHEN start_date <= NOW() THEN 0 ELSE 1 END')
            ->orderBy('start_date')
            ->limit(3)
            ->get();

        $result = $flashSales->map(function ($fs) {
            return [
                'id' => $fs->id,
                'title' => $fs->title,
                'description' => $fs->description,
                'banner_image_url' => $fs->banner_image_url,
                'start_date' => $fs->start_date->toIso8601String(),
                'end_date' => $fs->end_date->toIso8601String(),
                'is_running' => $fs->isRunning(),
                'is_upcoming' => $fs->isUpcoming(),
                'items' => $fs->items
                    ->filter(fn ($item) => $item->tour !== null && $item->period !== null)
                    ->values()
                    ->map(function ($item) use ($fs) {
                        $tour = $item->tour;
                        $period = $item->period;
                        $formatted = $this->formatTourForFlash($tour, $period);
                        $formatted['flash_sale_item_id'] = $item->id;
                        $formatted['period_id'] = $period->id;
                        $formatted['flash_price'] = (float)$item->flash_price;
                        $formatted['original_price_snapshot'] = (float)$item->original_price;
                        $formatted['discount_percent'] = (float)$item->discount_percent;
                        $formatted['quantity_limit'] = $item->quantity_limit;
                        $formatted['quantity_sold'] = $item->quantity_sold;
                        $formatted['remaining'] = $item->remaining;
                        $formatted['sold_percent'] = $item->sold_percent;
                        $formatted['is_sold_out'] = $item->isSoldOut();
                        // Per-item countdown: use flash_end_date if set, else campaign end_date
                        $formatted['flash_end_date'] = $item->flash_end_date
                            ? $item->flash_end_date->toIso8601String()
                            : $fs->end_date->toIso8601String();
                        $formatted['period_start_date'] = $period->start_date->format('Y-m-d');
                        $formatted['period_end_date'] = $period->end_date->format('Y-m-d');
                        return $formatted;
                    }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    private function formatTourForFlash(Tour $tour, Period $period): array
    {
        $airlineTransport = $tour->transports
            ->where('transport_type', 'outbound')
            ->first();
        $airline = $airlineTransport
            ? ($airlineTransport->transport?->name ?? $airlineTransport->transport_name)
            : null;

        return [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'title' => $tour->title,
            'tour_code' => $tour->tour_code,
            'country' => [
                'id' => $tour->primary_country_id ?? $tour->country_id,
                'name' => $tour->country?->name_th ?? $tour->country?->name_en,
                'iso2' => $tour->country?->iso2,
            ],
            'days' => $tour->duration_days ?? $tour->days,
            'nights' => $tour->duration_nights ?? $tour->nights,
            'price' => $tour->min_price,
            'original_price' => $tour->price_adult,
            'departure_date' => $period->start_date->format('Y-m-d'),
            'airline' => $airline,
            'image_url' => $tour->cover_image_url,
            'badge' => $tour->badge,
            'rating' => $tour->rating,
            'review_count' => $tour->review_count,
            'available_seats' => $period->available,
            'view_count' => $tour->view_count ?? 0,
            'hotel_star' => $tour->hotel_star,
        ];
    }
}
