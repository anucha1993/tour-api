<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    /**
     * List menus by location (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Menu::with('allChildren')->rootItems()->ordered();

        if ($request->has('location')) {
            $query->byLocation($request->location);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $menus = $query->get();

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }

    /**
     * Public menus - grouped by location
     */
    public function publicList(): JsonResponse
    {
        $menus = Menu::active()->rootItems()->ordered()->with('children')->get();

        $grouped = [
            'header' => $menus->where('location', Menu::LOCATION_HEADER)->values(),
            'footer_col1' => $menus->where('location', Menu::LOCATION_FOOTER_COL1)->values(),
            'footer_col2' => $menus->where('location', Menu::LOCATION_FOOTER_COL2)->values(),
            'footer_col3' => $menus->where('location', Menu::LOCATION_FOOTER_COL3)->values(),
        ];

        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    /**
     * Store a new menu item
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location' => 'required|string|in:header,footer_col1,footer_col2,footer_col3',
            'title' => 'required|string|max:255',
            'url' => 'nullable|string|max:500',
            'target' => 'nullable|string|in:_self,_blank',
            'icon' => 'nullable|string|max:100',
            'parent_id' => 'nullable|integer|exists:menus,id',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'css_class' => 'nullable|string|max:255',
        ]);

        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = Menu::where('location', $validated['location'])
                ->where('parent_id', $validated['parent_id'] ?? null)
                ->max('sort_order') + 1;
        }

        $menu = Menu::create($validated);
        $menu->load('allChildren');

        return response()->json([
            'success' => true,
            'data' => $menu,
            'message' => 'สร้างเมนูสำเร็จ',
        ], 201);
    }

    /**
     * Show single menu
     */
    public function show(Menu $menu): JsonResponse
    {
        $menu->load('allChildren');

        return response()->json([
            'success' => true,
            'data' => $menu,
        ]);
    }

    /**
     * Update menu item
     */
    public function update(Request $request, Menu $menu): JsonResponse
    {
        $validated = $request->validate([
            'location' => 'nullable|string|in:header,footer_col1,footer_col2,footer_col3',
            'title' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:500',
            'target' => 'nullable|string|in:_self,_blank',
            'icon' => 'nullable|string|max:100',
            'parent_id' => 'nullable|integer|exists:menus,id',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'css_class' => 'nullable|string|max:255',
        ]);

        $menu->update($validated);
        $menu->load('allChildren');

        return response()->json([
            'success' => true,
            'data' => $menu,
            'message' => 'อัปเดตเมนูสำเร็จ',
        ]);
    }

    /**
     * Delete menu item
     */
    public function destroy(Menu $menu): JsonResponse
    {
        $menu->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบเมนูสำเร็จ',
        ]);
    }

    /**
     * Reorder menus
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'menus' => 'required|array',
            'menus.*.id' => 'required|integer|exists:menus,id',
            'menus.*.sort_order' => 'required|integer',
            'menus.*.parent_id' => 'nullable|integer',
        ]);

        foreach ($validated['menus'] as $item) {
            Menu::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
                'parent_id' => $item['parent_id'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'จัดเรียงเมนูสำเร็จ',
        ]);
    }

    /**
     * Toggle menu status
     */
    public function toggleStatus(Menu $menu): JsonResponse
    {
        $menu->update(['is_active' => !$menu->is_active]);

        return response()->json([
            'success' => true,
            'data' => $menu,
            'message' => $menu->is_active ? 'เปิดใช้งานเมนูแล้ว' : 'ปิดใช้งานเมนูแล้ว',
        ]);
    }

    /**
     * Get location labels
     */
    public function locations(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Menu::LOCATIONS,
        ]);
    }
}
