<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\ContactPageSetting;
use App\Models\SiteContact;
use App\Services\CloudflareImagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    // ═══════════════════════════════════════════
    //  PUBLIC
    // ═══════════════════════════════════════════

    /**
     * Get contact page data (settings + contact info)
     */
    public function publicPage(): JsonResponse
    {
        $settings = ContactPageSetting::getSettings();

        // Reuse existing SiteContact data
        $contacts = SiteContact::active()->ordered()->byGroup('contact')->get();
        $socials = SiteContact::active()->ordered()->byGroup('social')->get();
        $businessHours = SiteContact::active()->ordered()->byGroup('business_hours')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $settings,
                'contacts' => $contacts,
                'socials' => $socials,
                'business_hours' => $businessHours,
            ],
        ]);
    }

    /**
     * Submit contact form (public)
     */
    public function submitForm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $message = ContactMessage::create($validated);

        return response()->json([
            'success' => true,
            'data' => $message,
            'message' => 'ส่งข้อความสำเร็จ เราจะติดต่อกลับโดยเร็วที่สุด',
        ], 201);
    }

    // ═══════════════════════════════════════════
    //  ADMIN - Page Settings
    // ═══════════════════════════════════════════

    public function getSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ContactPageSetting::getSettings(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $settings = ContactPageSetting::getSettings();

        $validated = $request->validate([
            'hero_title' => 'sometimes|string|max:255',
            'hero_subtitle' => 'nullable|string|max:255',
            'hero_image_url' => 'nullable|string|max:500',
            'intro_text' => 'nullable|string|max:2000',
            'map_embed_url' => 'nullable|string|max:1000',
            'office_name' => 'nullable|string|max:255',
            'office_address' => 'nullable|string|max:1000',
            'office_lat' => 'nullable|string|max:50',
            'office_lng' => 'nullable|string|max:50',
            'show_map' => 'boolean',
            'show_form' => 'boolean',
            'is_active' => 'boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|string|max:255',
        ]);

        $settings->update($validated);

        return response()->json([
            'success' => true,
            'data' => $settings,
            'message' => 'อัปเดตการตั้งค่าสำเร็จ',
        ]);
    }

    /**
     * Upload hero image
     */
    public function uploadHeroImage(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240']);

        $settings = ContactPageSetting::getSettings();

        // Delete old
        if ($settings->hero_image_cf_id) {
            try { $this->cloudflare->delete($settings->hero_image_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }

        $result = $this->cloudflare->uploadFromFile($request->file('image'));
        $settings->update([
            'hero_image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'hero_image_cf_id' => $result['id'],
        ]);

        return response()->json(['success' => true, 'data' => $settings->fresh()]);
    }

    /**
     * Delete hero image
     */
    public function deleteHeroImage(): JsonResponse
    {
        $settings = ContactPageSetting::getSettings();
        if ($settings->hero_image_cf_id) {
            try { $this->cloudflare->delete($settings->hero_image_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }
        $settings->update(['hero_image_url' => null, 'hero_image_cf_id' => null]);
        return response()->json(['success' => true, 'data' => $settings->fresh()]);
    }

    // ═══════════════════════════════════════════
    //  ADMIN - Messages
    // ═══════════════════════════════════════════

    /**
     * List messages with filters
     */
    public function messageIndex(Request $request): JsonResponse
    {
        $query = ContactMessage::query()->orderByDesc('created_at');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $messages = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $messages->items(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
            'counts' => [
                'all' => ContactMessage::count(),
                'new' => ContactMessage::where('status', 'new')->count(),
                'read' => ContactMessage::where('status', 'read')->count(),
                'replied' => ContactMessage::where('status', 'replied')->count(),
                'archived' => ContactMessage::where('status', 'archived')->count(),
            ],
        ]);
    }

    /**
     * Show single message (auto marks as read)
     */
    public function messageShow(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->markAsRead();
        $contactMessage->append('status_label');

        return response()->json([
            'success' => true,
            'data' => $contactMessage,
        ]);
    }

    /**
     * Update message status / admin notes
     */
    public function messageUpdate(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:new,read,replied,archived',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $contactMessage->update($validated);
        $contactMessage->append('status_label');

        return response()->json([
            'success' => true,
            'data' => $contactMessage,
            'message' => 'อัปเดตสำเร็จ',
        ]);
    }

    /**
     * Delete message
     */
    public function messageDestroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบข้อความสำเร็จ',
        ]);
    }

    /**
     * Get unread count (for sidebar badge)
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'count' => ContactMessage::where('status', 'new')->count(),
        ]);
    }
}
