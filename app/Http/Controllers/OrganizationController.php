<?php

namespace App\Http\Controllers;

use App\Models\ContactPageSetting;
use App\Models\OrganizationSetting;
use App\Models\SiteContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * Public: assembled organization data for schema.org markup on tour-web.
     *
     * Merges the editable OrganizationSetting fields with the office address
     * (from ContactPageSetting) and social profile URLs (from SiteContact) so
     * the public site can build a rich TravelAgency + FAQPage JSON-LD from a
     * single request.
     */
    public function publicPage(): JsonResponse
    {
        $org = OrganizationSetting::getSettings();
        $contact = ContactPageSetting::getSettings();
        $socials = SiteContact::active()->ordered()->byGroup('social')->get();

        $address = null;
        if ($contact->office_address || $contact->office_lat || $contact->office_lng) {
            $address = [
                'street' => $contact->office_address,
                'lat' => $contact->office_lat,
                'lng' => $contact->office_lng,
            ];
        }

        $rating = null;
        if ($org->rating_enabled && $org->rating_value && $org->rating_count) {
            $rating = [
                'value' => (float) $org->rating_value,
                'count' => (int) $org->rating_count,
            ];
        }

        $faqs = [];
        if ($org->faq_enabled && is_array($org->faqs)) {
            $faqs = array_values(array_filter($org->faqs, function ($f) {
                return is_array($f) && ! empty($f['question']) && ! empty($f['answer']);
            }));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'organization' => [
                    'legal_name' => $org->legal_name,
                    'description' => $org->description,
                    'price_range' => $org->price_range,
                    'area_served' => $org->area_served ?? [],
                    'languages' => $org->languages ?? [],
                    'founding_date' => $org->founding_date,
                    'same_as' => $socials->pluck('url')->filter()->values(),
                    'address' => $address,
                    'aggregate_rating' => $rating,
                ],
                'faqs' => $faqs,
            ],
        ]);
    }

    /**
     * Admin: get the singleton organization settings.
     */
    public function getSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => OrganizationSetting::getSettings(),
        ]);
    }

    /**
     * Admin: update the singleton organization settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $settings = OrganizationSetting::getSettings();

        $validated = $request->validate([
            'legal_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price_range' => 'nullable|string|max:20',
            'area_served' => 'nullable|array',
            'area_served.*' => 'string|max:100',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:20',
            'founding_date' => 'nullable|string|max:20',
            'rating_enabled' => 'boolean',
            'rating_value' => 'nullable|numeric|min:0|max:5',
            'rating_count' => 'nullable|integer|min:0',
            'faq_enabled' => 'boolean',
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required|string|max:500',
            'faqs.*.answer' => 'required|string|max:5000',
            'is_active' => 'boolean',
        ]);

        $settings->update($validated);

        return response()->json([
            'success' => true,
            'data' => $settings->fresh(),
            'message' => 'อัปเดตข้อมูลองค์กรสำเร็จ',
        ]);
    }
}
