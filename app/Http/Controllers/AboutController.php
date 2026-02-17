<?php

namespace App\Http\Controllers;

use App\Models\AboutPageSetting;
use App\Models\AboutAssociation;
use App\Models\AboutService;
use App\Models\AboutCustomerGroup;
use App\Models\AboutAward;
use App\Models\OurClient;
use App\Models\Country;
use App\Models\Tour;
use App\Services\CloudflareImagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AboutController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    // ============================================================
    // PUBLIC ENDPOINTS
    // ============================================================

    /**
     * Public: Get all about page data in one call
     */
    public function publicPage(): JsonResponse
    {
        $settings = AboutPageSetting::getSettings();
        $associations = AboutAssociation::active()->ordered()->get();
        $services = AboutService::active()->ordered()->get();
        $customerGroups = AboutCustomerGroup::active()->ordered()->get();
        $awards = AboutAward::active()->ordered()->get();
        $clients = OurClient::active()->ordered()->get();

        // Auto-fill highlight values from DB when value is empty
        $autoFillMap = [
            'ประเทศทั่วโลก' => fn() => number_format(Country::count()),
            'โปรแกรมทัวร์'  => fn() => number_format(Tour::count()),
        ];

        $highlights = $settings->highlights ?? [];
        foreach ($highlights as &$item) {
            if (empty($item['value']) && isset($autoFillMap[$item['label'] ?? ''])) {
                $item['value'] = $autoFillMap[$item['label']]();
            }
        }
        unset($item);
        $settings->highlights = $highlights;

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $settings,
                'associations' => $associations,
                'services' => $services,
                'customer_groups' => $customerGroups,
                'awards' => $awards,
                'clients' => $clients,
            ],
        ]);
    }

    // ============================================================
    // ADMIN: PAGE SETTINGS
    // ============================================================

    public function getSettings(): JsonResponse
    {
        $settings = AboutPageSetting::getSettings();
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $settings = AboutPageSetting::getSettings();
        $settings->update($request->only([
            'hero_title', 'hero_subtitle', 'hero_image_position',
            'about_title', 'about_content', 'highlights', 'value_props',
            'company_name', 'registration_no', 'capital', 'vat_no', 'tat_license', 'company_info_extra',
            'seo_title', 'seo_description', 'seo_keywords',
            'is_active',
        ]));
        return response()->json(['success' => true, 'data' => $settings->fresh()]);
    }

    public function uploadHeroImage(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240']);

        $settings = AboutPageSetting::getSettings();

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

    public function deleteHeroImage(): JsonResponse
    {
        $settings = AboutPageSetting::getSettings();
        if ($settings->hero_image_cf_id) {
            try { $this->cloudflare->delete($settings->hero_image_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }
        $settings->update(['hero_image_url' => null, 'hero_image_cf_id' => null]);
        return response()->json(['success' => true, 'data' => $settings->fresh()]);
    }

    public function uploadLicenseImage(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240']);

        $settings = AboutPageSetting::getSettings();

        if ($settings->license_image_cf_id) {
            try { $this->cloudflare->delete($settings->license_image_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }

        $result = $this->cloudflare->uploadFromFile($request->file('image'));
        $settings->update([
            'license_image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'license_image_cf_id' => $result['id'],
        ]);

        return response()->json(['success' => true, 'data' => $settings->fresh()]);
    }

    public function deleteLicenseImage(): JsonResponse
    {
        $settings = AboutPageSetting::getSettings();
        if ($settings->license_image_cf_id) {
            try { $this->cloudflare->delete($settings->license_image_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }
        $settings->update(['license_image_url' => null, 'license_image_cf_id' => null]);
        return response()->json(['success' => true, 'data' => $settings->fresh()]);
    }

    // ============================================================
    // ADMIN: ASSOCIATIONS
    // ============================================================

    public function listAssociations(): JsonResponse
    {
        $items = AboutAssociation::ordered()->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeAssociation(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255']);
        $maxOrder = AboutAssociation::max('sort_order') ?? 0;
        $item = AboutAssociation::create(array_merge(
            $request->only(['name', 'license_no', 'website_url', 'is_active']),
            ['sort_order' => $maxOrder + 1]
        ));
        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function updateAssociation(Request $request, $id): JsonResponse
    {
        $item = AboutAssociation::findOrFail($id);
        $item->update($request->only(['name', 'license_no', 'website_url', 'is_active', 'sort_order']));
        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    public function destroyAssociation($id): JsonResponse
    {
        $item = AboutAssociation::findOrFail($id);
        if ($item->logo_cf_id) {
            try { $this->cloudflare->delete($item->logo_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }
        $item->delete();
        return response()->json(['success' => true]);
    }

    public function uploadAssociationLogo(Request $request, $id): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:5120']);
        $item = AboutAssociation::findOrFail($id);

        if ($item->logo_cf_id) {
            try { $this->cloudflare->delete($item->logo_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }

        $result = $this->cloudflare->uploadFromFile($request->file('image'));
        $item->update([
            'logo_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'logo_cf_id' => $result['id'],
        ]);

        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    public function reorderAssociations(Request $request): JsonResponse
    {
        $request->validate(['items' => 'required|array', 'items.*.id' => 'required|integer', 'items.*.sort_order' => 'required|integer']);
        foreach ($request->items as $item) {
            AboutAssociation::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        return response()->json(['success' => true]);
    }

    // ============================================================
    // ADMIN: SERVICES
    // ============================================================

    public function listServices(): JsonResponse
    {
        $items = AboutService::ordered()->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeService(Request $request): JsonResponse
    {
        $request->validate(['title' => 'required|string|max:255']);
        $maxOrder = AboutService::max('sort_order') ?? 0;
        $item = AboutService::create(array_merge(
            $request->only(['title', 'description', 'icon', 'is_active']),
            ['sort_order' => $maxOrder + 1]
        ));
        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function updateService(Request $request, $id): JsonResponse
    {
        $item = AboutService::findOrFail($id);
        $item->update($request->only(['title', 'description', 'icon', 'is_active', 'sort_order']));
        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    public function destroyService($id): JsonResponse
    {
        AboutService::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function reorderServices(Request $request): JsonResponse
    {
        $request->validate(['items' => 'required|array', 'items.*.id' => 'required|integer', 'items.*.sort_order' => 'required|integer']);
        foreach ($request->items as $item) {
            AboutService::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        return response()->json(['success' => true]);
    }

    // ============================================================
    // ADMIN: CUSTOMER GROUPS
    // ============================================================

    public function listCustomerGroups(): JsonResponse
    {
        $items = AboutCustomerGroup::ordered()->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeCustomerGroup(Request $request): JsonResponse
    {
        $request->validate(['title' => 'required|string|max:255']);
        $maxOrder = AboutCustomerGroup::max('sort_order') ?? 0;
        $item = AboutCustomerGroup::create(array_merge(
            $request->only(['title', 'description', 'icon', 'is_active']),
            ['sort_order' => $maxOrder + 1]
        ));
        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function updateCustomerGroup(Request $request, $id): JsonResponse
    {
        $item = AboutCustomerGroup::findOrFail($id);
        $item->update($request->only(['title', 'description', 'icon', 'is_active', 'sort_order']));
        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    public function destroyCustomerGroup($id): JsonResponse
    {
        $item = AboutCustomerGroup::findOrFail($id);
        if ($item->image_cf_id) {
            try { $this->cloudflare->delete($item->image_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }
        $item->delete();
        return response()->json(['success' => true]);
    }

    public function uploadCustomerGroupImage(Request $request, $id): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:5120']);
        $item = AboutCustomerGroup::findOrFail($id);

        if ($item->image_cf_id) {
            try { $this->cloudflare->delete($item->image_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }

        $result = $this->cloudflare->uploadFromFile($request->file('image'));
        $item->update([
            'image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'image_cf_id' => $result['id'],
        ]);

        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    public function reorderCustomerGroups(Request $request): JsonResponse
    {
        $request->validate(['items' => 'required|array', 'items.*.id' => 'required|integer', 'items.*.sort_order' => 'required|integer']);
        foreach ($request->items as $item) {
            AboutCustomerGroup::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        return response()->json(['success' => true]);
    }

    // ============================================================
    // ADMIN: AWARDS
    // ============================================================

    public function listAwards(): JsonResponse
    {
        $items = AboutAward::ordered()->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeAward(Request $request): JsonResponse
    {
        $request->validate(['title' => 'required|string|max:255']);
        $maxOrder = AboutAward::max('sort_order') ?? 0;
        $item = AboutAward::create(array_merge(
            $request->only(['title', 'description', 'year', 'is_active']),
            ['sort_order' => $maxOrder + 1]
        ));
        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function updateAward(Request $request, $id): JsonResponse
    {
        $item = AboutAward::findOrFail($id);
        $item->update($request->only(['title', 'description', 'year', 'is_active', 'sort_order']));
        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    public function destroyAward($id): JsonResponse
    {
        $item = AboutAward::findOrFail($id);
        if ($item->image_cf_id) {
            try { $this->cloudflare->delete($item->image_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }
        $item->delete();
        return response()->json(['success' => true]);
    }

    public function uploadAwardImage(Request $request, $id): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:5120']);
        $item = AboutAward::findOrFail($id);

        if ($item->image_cf_id) {
            try { $this->cloudflare->delete($item->image_cf_id); } catch (\Exception $e) { Log::warning('CF delete failed: ' . $e->getMessage()); }
        }

        $result = $this->cloudflare->uploadFromFile($request->file('image'));
        $item->update([
            'image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'image_cf_id' => $result['id'],
        ]);

        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    public function reorderAwards(Request $request): JsonResponse
    {
        $request->validate(['items' => 'required|array', 'items.*.id' => 'required|integer', 'items.*.sort_order' => 'required|integer']);
        foreach ($request->items as $item) {
            AboutAward::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        return response()->json(['success' => true]);
    }
}
