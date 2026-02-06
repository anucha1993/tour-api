<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PageContentController extends Controller
{
    /**
     * List of available page content keys
     */
    private array $pageKeys = [
        'terms' => [
            'title' => 'เงื่อนไขการให้บริการ',
            'description' => 'ข้อกำหนดและเงื่อนไขการใช้บริการ',
        ],
        'payment_terms' => [
            'title' => 'เงื่อนไขการชำระเงิน',
            'description' => 'ช่องทางและเงื่อนไขการชำระเงิน',
        ],
    ];

    /**
     * Get all page contents
     */
    public function index(): JsonResponse
    {
        $pages = [];
        
        foreach ($this->pageKeys as $key => $info) {
            $settingKey = "page_content.{$key}";
            $content = Setting::get($settingKey, '');
            $updatedAt = Setting::where('key', $settingKey)->first()?->updated_at;
            
            $pages[$key] = [
                'key' => $key,
                'title' => $info['title'],
                'description' => $info['description'],
                'content' => $content,
                'updated_at' => $updatedAt?->toISOString(),
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $pages,
        ]);
    }

    /**
     * Get a specific page content
     */
    public function show(string $key): JsonResponse
    {
        if (!isset($this->pageKeys[$key])) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }
        
        $settingKey = "page_content.{$key}";
        $content = Setting::get($settingKey, '');
        $setting = Setting::where('key', $settingKey)->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'key' => $key,
                'title' => $this->pageKeys[$key]['title'],
                'description' => $this->pageKeys[$key]['description'],
                'content' => $content,
                'updated_at' => $setting?->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Update a page content
     */
    public function update(Request $request, string $key): JsonResponse
    {
        if (!isset($this->pageKeys[$key])) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }
        
        $validated = $request->validate([
            'content' => 'required|string',
        ]);
        
        $settingKey = "page_content.{$key}";
        $setting = Setting::where('key', $settingKey)->first();
        
        if ($setting) {
            $setting->update([
                'value' => $validated['content'],
            ]);
        } else {
            $setting = Setting::create([
                'key' => $settingKey,
                'value' => $validated['content'],
                'type' => 'string',
                'group' => 'page_content',
                'description' => $this->pageKeys[$key]['title'],
                'is_public' => true,
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'บันทึกเนื้อหาสำเร็จ',
            'data' => [
                'key' => $key,
                'title' => $this->pageKeys[$key]['title'],
                'description' => $this->pageKeys[$key]['description'],
                'content' => $validated['content'],
                'updated_at' => $setting->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Public endpoint - Get page content for frontend
     */
    public function getPublicContent(string $key): JsonResponse
    {
        if (!isset($this->pageKeys[$key])) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }
        
        $settingKey = "page_content.{$key}";
        $content = Setting::get($settingKey, '');
        $setting = Setting::where('key', $settingKey)->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'key' => $key,
                'title' => $this->pageKeys[$key]['title'],
                'description' => $this->pageKeys[$key]['description'],
                'content' => $content,
                'updated_at' => $setting?->updated_at?->toISOString(),
            ],
        ]);
    }
}
