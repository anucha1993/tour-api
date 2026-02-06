<?php

namespace App\Http\Controllers;

use App\Models\WebPage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

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
        'payment_channels' => [
            'title' => 'ช่องทางการชำระเงิน',
            'description' => 'ช่องทางและวิธีการชำระเงิน',
        ],
        'cookie_policy' => [
            'title' => 'นโยบายคุกกี้',
            'description' => 'การใช้งานคุกกี้และการติดตามข้อมูล',
        ],
        'privacy_policy' => [
            'title' => 'นโยบายความเป็นส่วนตัว',
            'description' => 'การเก็บรวบรวมและใช้ข้อมูลส่วนบุคคล',
        ],
        'login_page' => [
            'title' => 'หน้าเข้าสู่ระบบ',
            'description' => 'รูปภาพและ SEO สำหรับหน้าเข้าสู่ระบบ',
        ],
        'register_page' => [
            'title' => 'หน้าสมัครสมาชิก',
            'description' => 'รูปภาพและ SEO สำหรับหน้าสมัครสมาชิก',
        ],
    ];

    /**
     * Get all page contents
     */
    public function index(): JsonResponse
    {
        $pages = [];
        
        foreach ($this->pageKeys as $key => $info) {
            $page = WebPage::where('key', $key)->first();
            
            $pages[$key] = [
                'key' => $key,
                'title' => $page?->title ?? $info['title'],
                'description' => $info['description'],
                'content' => $page?->content ?? '',
                'updated_at' => $page?->updated_at?->toISOString(),
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $pages,
        ]);
    }

    /**
     * Upload Image for Login/Register Pages
     */
    public function uploadImage(Request $request, string $key): JsonResponse
    {
        if (!in_array($key, ['login_page', 'register_page'])) {
             return response()->json(['success' => false, 'message' => 'Invalid page key for image upload. Only login_page and register_page are supported.'], 400);
        }

        $request->validate([
            'image' => 'required|image|mimes:webp|max:800',
            'alt' => 'required|string|max:255',
            'title' => 'required|string|max:255',
        ], [
            'image.mimes' => 'The image must be a file of type: webp.',
            'image.max' => 'The image must not be greater than 800 kilobytes.',
        ]);

        try {
            // Upload to R2
            $path = $request->file('image')->store('web-pages', 'r2');
            
            // Get URL (Assuming R2 disk is configured with 'url')
            $url = Storage::disk('r2')->url($path);

            $content = [
                'image_url' => $url,
                'alt' => $request->alt,
                'title' => $request->title,
            ];

            $page = WebPage::updateOrCreate(
                ['key' => $key],
                [
                    'title' => $this->pageKeys[$key]['title'],
                    'description' => $this->pageKeys[$key]['description'],
                    'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                    'is_active' => true,
                ]
            );

            return response()->json([
                'success' => true, 
                'data' => [
                    'key' => $key,
                    'content' => $content
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Image for Login/Register Pages
     */
    public function deleteImage(string $key): JsonResponse
    {
        if (!in_array($key, ['login_page', 'register_page'])) {
             return response()->json(['success' => false, 'message' => 'Invalid page key for image deletion.'], 400);
        }

        try {
            $page = WebPage::where('key', $key)->first();
            
            if (!$page) {
                return response()->json(['success' => false, 'message' => 'Page content not found.'], 404);
            }

            $content = json_decode($page->content ?? '{}', true);
            
            // Delete existing image from R2
            if (!empty($content['image_url'])) {
                try {
                    $path = parse_url($content['image_url'], PHP_URL_PATH);
                    if ($path) {
                        $path = ltrim($path, '/');
                        if (Storage::disk('r2')->exists($path)) {
                            Storage::disk('r2')->delete($path);
                        }
                    }
                } catch (\Exception $e) {
                    // Log error but continue to clear DB
                    logger()->error('Failed to delete image from R2: ' . $e->getMessage());
                }
            }

            // Set image_url to empty string
            $content['image_url'] = '';
            
            $page->content = json_encode($content, JSON_UNESCAPED_UNICODE);
            $page->save();

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
                'data' => [
                    'key' => $key,
                    'content' => $content
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete failed: ' . $e->getMessage()
            ], 500);
        }
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
        
        $page = WebPage::where('key', $key)->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'key' => $key,
                'title' => $page?->title ?? $this->pageKeys[$key]['title'],
                'description' => $this->pageKeys[$key]['description'],
                'content' => $page?->content ?? '',
                'updated_at' => $page?->updated_at?->toISOString(),
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
        
        $page = WebPage::updateOrCreate(
            ['key' => $key],
            [
                'title' => $this->pageKeys[$key]['title'],
                'description' => $this->pageKeys[$key]['description'],
                'content' => $validated['content'],
                'is_active' => true,
            ]
        );
        
        return response()->json([
            'success' => true,
            'data' => [
                'key' => $key,
                'title' => $page->title,
                'description' => $page->description,
                'content' => $page->content,
                'updated_at' => $page->updated_at->toISOString(),
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
        
        $page = WebPage::where('key', $key)->where('is_active', true)->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'key' => $key,
                'title' => $page?->title ?? $this->pageKeys[$key]['title'],
                'description' => $this->pageKeys[$key]['description'],
                'content' => $page?->content ?? '',
                'updated_at' => $page?->updated_at?->toISOString(),
            ],
        ]);
    }
}
