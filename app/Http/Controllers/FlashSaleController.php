<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Append computed status
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
        $flashSale->load(['items.tour' => function ($q) {
            $q->select('id', 'title', 'slug', 'tour_code', 'cover_image_url', 'min_price', 'price_adult', 'max_discount_percent', 'status');
        }]);

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
    //  ITEMS MANAGEMENT
    // ═══════════════════════════════════════════

    public function addItem(Request $request, FlashSale $flashSale): JsonResponse
    {
        $validated = $request->validate([
            'tour_id' => 'required|exists:tours,id',
            'flash_price' => 'nullable|numeric|min:0',
            'quantity_limit' => 'nullable|integer|min:1',
            'sort_order' => 'integer',
        ]);

        // Check duplicate
        $exists = $flashSale->items()->where('tour_id', $validated['tour_id'])->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'ทัวร์นี้มีอยู่ใน Flash Sale นี้แล้ว',
            ], 422);
        }

        // Get tour original price
        $tour = Tour::findOrFail($validated['tour_id']);
        $originalPrice = $tour->min_price ?? $tour->price_adult;
        $flashPrice = $validated['flash_price'] ?? $originalPrice;

        // Calculate discount percent
        $discountPercent = 0;
        if ($originalPrice > 0 && $flashPrice < $originalPrice) {
            $discountPercent = round((($originalPrice - $flashPrice) / $originalPrice) * 100, 1);
        }

        $item = $flashSale->items()->create([
            'tour_id' => $validated['tour_id'],
            'flash_price' => $flashPrice,
            'original_price' => $originalPrice,
            'discount_percent' => $discountPercent,
            'quantity_limit' => $validated['quantity_limit'] ?? null,
            'quantity_sold' => 0,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        $item->load('tour:id,title,slug,tour_code,cover_image_url,min_price,price_adult,status');

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'เพิ่มทัวร์ลง Flash Sale สำเร็จ',
        ], 201);
    }

    public function updateItem(Request $request, FlashSale $flashSale, FlashSaleItem $item): JsonResponse
    {
        $validated = $request->validate([
            'flash_price' => 'nullable|numeric|min:0',
            'quantity_limit' => 'nullable|integer|min:1',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['flash_price'])) {
            $originalPrice = $item->original_price;
            $flashPrice = $validated['flash_price'];
            $discountPercent = 0;
            if ($originalPrice > 0 && $flashPrice < $originalPrice) {
                $discountPercent = round((($originalPrice - $flashPrice) / $originalPrice) * 100, 1);
            }
            $validated['discount_percent'] = $discountPercent;
        }

        $item->update($validated);

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'อัปเดตรายการ Flash Sale สำเร็จ',
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

    /**
     * Mass update discount for all items in a flash sale.
     * Supports 2 modes:
     *   - 'percent': ลดราคาตาม % ที่ระบุ
     *   - 'amount': ลดราคาตามจำนวนเงินที่ระบุ
     */
    public function massUpdateDiscount(Request $request, FlashSale $flashSale): JsonResponse
    {
        $validated = $request->validate([
            'discount_type' => 'required|in:percent,amount',
            'discount_value' => 'required|numeric|min:0',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer',
        ]);

        $type = $validated['discount_type'];
        $value = $validated['discount_value'];

        $query = $flashSale->items();
        if (!empty($validated['item_ids'])) {
            $query->whereIn('id', $validated['item_ids']);
        }
        $items = $query->get();
        $updated = 0;

        foreach ($items as $item) {
            $originalPrice = $item->original_price;
            if (!$originalPrice || $originalPrice <= 0) continue;

            if ($type === 'percent') {
                // ลดตาม %
                $flashPrice = round($originalPrice * (1 - $value / 100));
                $discountPercent = $value;
            } else {
                // ลดตามจำนวนเงิน
                $flashPrice = max(0, $originalPrice - $value);
                $discountPercent = round(($value / $originalPrice) * 100, 1);
            }

            $item->update([
                'flash_price' => $flashPrice,
                'discount_percent' => $discountPercent,
            ]);
            $updated++;
        }

        return response()->json([
            'success' => true,
            'message' => "อัปเดตส่วนลดทั้งหมด {$updated} รายการสำเร็จ",
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
    //  SEARCH TOURS (for admin selection)
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

        $tours = $query->orderBy('title')->limit(20)->get();

        return response()->json([
            'success' => true,
            'data' => $tours,
        ]);
    }

    // ═══════════════════════════════════════════
    //  PUBLIC API
    // ═══════════════════════════════════════════

    public function publicActive(): JsonResponse
    {
        // Get currently running or upcoming flash sales (running first)
        $flashSales = FlashSale::active()
            ->where('end_date', '>=', now())
            ->with(['items' => function ($q) {
                $q->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['tour' => function ($tq) {
                        $tq->where('status', 'active')
                            ->with(['transports.transport', 'periods', 'country']);
                    }]);
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
                    ->filter(fn ($item) => $item->tour !== null)
                    ->values()
                    ->map(function ($item) {
                        $tour = $item->tour;
                        $formatted = $this->formatTourForFlash($tour);
                        $formatted['flash_price'] = (float)$item->flash_price;
                        $formatted['original_price_snapshot'] = (float)$item->original_price;
                        $formatted['discount_percent'] = (float)$item->discount_percent;
                        $formatted['quantity_limit'] = $item->quantity_limit;
                        $formatted['quantity_sold'] = $item->quantity_sold;
                        $formatted['remaining'] = $item->remaining;
                        $formatted['sold_percent'] = $item->sold_percent;
                        $formatted['is_sold_out'] = $item->isSoldOut();
                        return $formatted;
                    }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    private function formatTourForFlash(Tour $tour): array
    {
        $airlineTransport = $tour->transports
            ->where('transport_type', 'outbound')
            ->first();
        $airline = $airlineTransport
            ? ($airlineTransport->transport?->name ?? $airlineTransport->transport_name)
            : null;

        $openPeriods = $tour->periods
            ->where('status', 'open')
            ->where('start_date', '>=', now()->toDateString());
        $minDeparture = $openPeriods->min('start_date');
        $maxDeparture = $openPeriods->max('start_date');
        $availableSeats = $openPeriods->sum('available');

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
            'departure_date' => $minDeparture,
            'max_departure_date' => $maxDeparture,
            'airline' => $airline,
            'image_url' => $tour->cover_image_url,
            'badge' => $tour->badge,
            'rating' => $tour->rating,
            'review_count' => $tour->review_count,
            'available_seats' => $availableSeats,
            'view_count' => $tour->view_count ?? 0,
            'hotel_star' => $tour->hotel_star,
        ];
    }
}
