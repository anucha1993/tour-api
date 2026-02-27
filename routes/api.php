<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WholesalerController;
use App\Http\Controllers\Api\TransportController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PromotionNotificationController;
use App\Http\Controllers\TourTabController;
use App\Http\Controllers\GalleryImageController;
use App\Http\Controllers\GalleryVideoController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\WholesalerSyncController;
use App\Http\Controllers\Api\TourSearchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\RecommendedTourController;
use App\Http\Controllers\PageContentController;
use App\Http\Controllers\PublicTourController;
use App\Http\Controllers\InternationalTourSettingController;
use App\Http\Controllers\DomesticTourSettingController;
use App\Http\Controllers\FestivalHolidayController;
use App\Http\Controllers\TourPackageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\GroupTourController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\TourReviewAdminController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health Check - ใช้ทดสอบว่า API ทำงานได้
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is running',
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment(),
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
    ]);
});

// Public routes (no auth required)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Dashboard Summary
Route::get('dashboard/summary', [DashboardController::class, 'summary']);

// Protected routes (auth required)
// Integrations (Wholesaler API Configs)
// Public endpoints for testing/preview (no auth needed as it tests external API)
Route::post('integrations/test-connection', [IntegrationController::class, 'testConnection']);
Route::get('integrations/check-schedule', [IntegrationController::class, 'checkScheduleConflict']);
Route::get('integrations/{id}/fetch-sample', [IntegrationController::class, 'fetchSample']);
Route::get('integrations/{id}/mappings', [IntegrationController::class, 'getFieldMappings']);
Route::post('integrations/{id}/mappings', [IntegrationController::class, 'saveFieldMappings']);
Route::post('integrations/{id}/test-mapping', [IntegrationController::class, 'testMapping']);
Route::get('integrations/{id}/check-tour-count', [IntegrationController::class, 'checkTourCount']);

// Unified Tour Search (Realtime from Wholesaler APIs)
Route::get('tours/search', [TourSearchController::class, 'search']);
Route::get('tours/search/filters', [TourSearchController::class, 'getFilters']);
Route::get('integrations/{id}/tours/search', [TourSearchController::class, 'searchWholesaler']);
Route::get('integrations/{id}/tours/{tourId}', [TourSearchController::class, 'getTourDetail']);

// Tour Code Lookup (by external_id)
Route::post('tours/lookup-codes', [TourSearchController::class, 'lookupTourCodes']);

// Mass Sync from Search (sync selected tours)
Route::post('integrations/{id}/tours/sync-selected', [IntegrationController::class, 'syncSelectedTours']);

// Queue & Sync Status (for monitoring/debugging)
Route::get('queue/status', [IntegrationController::class, 'getQueueStatus']);
Route::get('queue/failed-jobs', [IntegrationController::class, 'getFailedJobs']);
Route::post('queue/fix-stuck', [IntegrationController::class, 'fixStuckSyncs']);
Route::post('queue/clear-failed', [IntegrationController::class, 'clearFailedJobs']);
Route::post('queue/process', [IntegrationController::class, 'processQueue']);

// Sync Progress & Control (NEW)
Route::get('sync/running', [IntegrationController::class, 'getRunningSyncs']);
Route::get('sync/{syncLogId}/progress', [IntegrationController::class, 'getSyncProgress']);
Route::post('sync/{syncLogId}/cancel', [IntegrationController::class, 'cancelSync']);
Route::post('sync/{syncLogId}/force-cancel', [IntegrationController::class, 'forceCancelSync']);

// Public Hero Slides (for tour-web homepage)
Route::get('hero-slides/public', [\App\Http\Controllers\HeroSlideController::class, 'publicList']);

// Public Our Clients (for tour-web)
Route::get('our-clients/public', [\App\Http\Controllers\OurClientController::class, 'publicList']);

// Public Popups (for tour-web)
Route::get('popups/public', [\App\Http\Controllers\PopupController::class, 'publicList']);

// Public Menus (for tour-web header/footer)
Route::get('menus/public', [\App\Http\Controllers\MenuController::class, 'publicList']);

// Public SEO (for tour-web)
Route::get('seo/public/{slug}', [\App\Http\Controllers\SeoController::class, 'publicShow']);

// Public Site Contacts (for tour-web)
Route::get('site-contacts/public', [\App\Http\Controllers\SeoController::class, 'contactPublic']);

// Public Footer Config (for tour-web)
Route::get('footer-config/public', [SettingsController::class, 'getFooterConfigPublic']);
Route::get('why-choose-us/public', [SettingsController::class, 'getWhyChooseUsConfigPublic']);

// Public Subscriber endpoints
Route::post('subscribers/subscribe', [SubscriberController::class, 'subscribe']);
Route::get('subscribers/confirm/{token}', [SubscriberController::class, 'confirm']);
Route::get('subscribers/unsubscribe/{token}', [SubscriberController::class, 'unsubscribe']);
Route::post('subscribers/unsubscribe/{token}', [SubscriberController::class, 'unsubscribe']); // Gmail One-Click Unsubscribe

// Public Popular Countries (for tour-web homepage)
Route::get('popular-countries/public', [\App\Http\Controllers\PopularCountryController::class, 'publicList']);

// Public Promotions (for tour-web homepage)
Route::get('promotions/public', [PromotionController::class, 'publicList']);

// Public Tour Tabs (for tour-web homepage)
Route::get('tour-tabs/public', [TourTabController::class, 'publicList']);
Route::get('tour-tabs/public/badges', [TourTabController::class, 'publicBadges']);
Route::get('tour-tabs/public/promotions', [TourTabController::class, 'publicPromotions']);
Route::get('tour-tabs/public/{slug}', [TourTabController::class, 'publicShow']);

// Public Recommended Tours (for tour-web homepage)
Route::get('recommended-tours/public', [RecommendedTourController::class, 'publicShow']);

// Public Tour Detail (for tour-web tour page)
Route::get('tours/detail/{slug}', [PublicTourController::class, 'show']);
Route::post('tours/detail/{slug}/view', [PublicTourController::class, 'recordView']);
Route::get('tours/detail/{slug}/related', [PublicTourController::class, 'relatedTours']);

// Public Tour Reviews (for tour-web)
Route::get('reviews/featured', [\App\Http\Controllers\Web\WebTourReviewController::class, 'featured']);
Route::get('tours/{tourSlug}/reviews', [\App\Http\Controllers\Web\WebTourReviewController::class, 'index']);
Route::get('tours/{tourSlug}/reviews/summary', [\App\Http\Controllers\Web\WebTourReviewController::class, 'summary']);
Route::post('reviews/{reviewId}/helpful', [\App\Http\Controllers\Web\WebTourReviewController::class, 'markHelpful']);
Route::get('review-tags', [\App\Http\Controllers\Web\WebTourReviewController::class, 'tags']);

// Public International Tours Menu (for tour-web mega menu)
Route::get('tours/international-menu', [PublicTourController::class, 'internationalMenu']);

// Public International Tours Listing (with filters, pagination, periods)
Route::get('tours/international', [PublicTourController::class, 'internationalTours']);
Route::get('tours/international/settings', [InternationalTourSettingController::class, 'getPublicSetting']);

// Public Domestic Tours Menu & Listing
Route::get('tours/domestic-menu', [PublicTourController::class, 'domesticMenu']);
Route::get('tours/domestic', [PublicTourController::class, 'domesticTours']);
Route::get('tours/domestic/settings', [DomesticTourSettingController::class, 'getPublicSetting']);

// Public Festival Tours
Route::get('tours/festival', [FestivalHolidayController::class, 'publicList']);
Route::get('tours/festival-badges', [FestivalHolidayController::class, 'publicBadges']);
Route::get('tours/festival/page-settings', [FestivalHolidayController::class, 'publicPageSettings']);
Route::get('tours/festival/{slug}', [FestivalHolidayController::class, 'publicShow']);

// Public Tour Packages
Route::get('tours/packages', [TourPackageController::class, 'publicList']);
Route::get('tours/packages/page-settings', [TourPackageController::class, 'publicPageSettings']);
Route::get('tours/packages/{slug}', [TourPackageController::class, 'publicShow']);

// Public Group Tours
Route::get('tours/group', [GroupTourController::class, 'publicPage']);
Route::post('tours/group/inquiry', [GroupTourController::class, 'publicSubmitInquiry']);

// Public Blog
Route::get('blog/settings', [BlogController::class, 'publicSettings']);
Route::get('blog/categories', [BlogController::class, 'publicCategories']);
Route::get('blog/filters', [BlogController::class, 'publicFilters']);
Route::get('blog/posts', [BlogController::class, 'publicPosts']);
Route::get('blog/posts/{slug}', [BlogController::class, 'publicShow']);

// Public About Page
Route::get('about/public', [AboutController::class, 'publicPage']);

// PDF Preview (uses token query param for auth)
Route::get('tours/{tour}/generate-pdf', [TourController::class, 'generatePdf']);

// Public Flash Sale
Route::get('flash-sales/public', [FlashSaleController::class, 'publicActive']);

// Public Contact Page
Route::get('contact/public', [ContactController::class, 'publicPage']);
Route::post('contact/submit', [ContactController::class, 'submitForm']);

// Public Search & Autocomplete
Route::get('search/autocomplete', [SearchController::class, 'autocomplete']);
Route::get('search/popular', [SearchController::class, 'popular']);
Route::get('search/suggestions', [SearchController::class, 'suggestions']);
Route::post('search/track', [SearchController::class, 'trackKeyword']);
Route::get('search', [SearchController::class, 'search']);

// Wholesaler Sync API (Public for testing - move inside auth:sanctum for production)
Route::prefix('wholesalers/{wholesaler}/sync')->group(function () {
    Route::post('/tour', [WholesalerSyncController::class, 'syncTour']);
    Route::post('/tours', [WholesalerSyncController::class, 'syncTours']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Users CRUD (Backend Admin/Staff - แยกจาก WebMembers)
    Route::apiResource('users', UserController::class);

    // Web Members Management (สมาชิกหน้าเว็บ - แยกจาก Users)
    Route::prefix('web-members')->group(function () {
        Route::get('/statistics', [\App\Http\Controllers\Api\WebMemberController::class, 'statistics']);
        Route::get('/export', [\App\Http\Controllers\Api\WebMemberController::class, 'export']);
        Route::patch('/{id}/status', [\App\Http\Controllers\Api\WebMemberController::class, 'updateStatus']);
        Route::post('/{id}/reset-password', [\App\Http\Controllers\Api\WebMemberController::class, 'resetPassword']);
        Route::post('/{id}/unlock', [\App\Http\Controllers\Api\WebMemberController::class, 'unlock']);
    });
    Route::apiResource('web-members', \App\Http\Controllers\Api\WebMemberController::class)->only(['index', 'show', 'destroy']);

    // Member Points Admin
    Route::prefix('member-points')->group(function () {
        // Stats dashboard
        Route::get('/stats', [\App\Http\Controllers\MemberPointAdminController::class, 'stats']);
        // Levels CRUD
        Route::get('/levels', [\App\Http\Controllers\MemberPointAdminController::class, 'listLevels']);
        Route::post('/levels', [\App\Http\Controllers\MemberPointAdminController::class, 'createLevel']);
        Route::put('/levels/{id}', [\App\Http\Controllers\MemberPointAdminController::class, 'updateLevel']);
        Route::delete('/levels/{id}', [\App\Http\Controllers\MemberPointAdminController::class, 'deleteLevel']);
        // Rules CRUD
        Route::get('/rules', [\App\Http\Controllers\MemberPointAdminController::class, 'listRules']);
        Route::put('/rules/{id}', [\App\Http\Controllers\MemberPointAdminController::class, 'updateRule']);
        // Members
        Route::get('/members', [\App\Http\Controllers\MemberPointAdminController::class, 'listMembers']);
        Route::get('/members/{id}', [\App\Http\Controllers\MemberPointAdminController::class, 'getMemberDetail']);
        Route::get('/members/{id}/transactions', [\App\Http\Controllers\MemberPointAdminController::class, 'getMemberTransactions']);
        Route::post('/members/{id}/adjust', [\App\Http\Controllers\MemberPointAdminController::class, 'adjustMemberPoints']);
    });

    // Wholesalers CRUD
    Route::patch('wholesalers/{wholesaler}/toggle-active', [WholesalerController::class, 'toggleActive']);
    Route::apiResource('wholesalers', WholesalerController::class);

    // Transports CRUD
    Route::get('transports/types', [TransportController::class, 'types']);
    Route::patch('transports/{transport}/toggle-status', [TransportController::class, 'toggleStatus']);
    Route::post('transports/{transport}/upload-image', [TransportController::class, 'uploadImage']);
    Route::apiResource('transports', TransportController::class);

    // Countries CRUD
    Route::get('countries/regions', [CountryController::class, 'regions']);
    Route::patch('countries/{country}/toggle-status', [CountryController::class, 'toggleStatus']);
    Route::apiResource('countries', CountryController::class);

    // Cities CRUD
    Route::get('cities/countries', [CityController::class, 'countries']);
    Route::get('cities/countries-with-cities', [CityController::class, 'countriesWithCities']);
    Route::patch('cities/{city}/toggle-status', [CityController::class, 'toggleStatus']);
    Route::patch('cities/{city}/toggle-popular', [CityController::class, 'togglePopular']);
    Route::apiResource('cities', CityController::class);

    // Tours CRUD
    Route::get('tours/regions', [TourController::class, 'regions']);
    Route::get('tours/themes', [TourController::class, 'themes']);
    Route::get('tours/tour-types', [TourController::class, 'tourTypes']);
    Route::get('tours/suitable-for', [TourController::class, 'suitableFor']);
    Route::get('tours/statistics', [TourController::class, 'statistics']);
    Route::get('tours/counts', [TourController::class, 'counts']);
    Route::get('tours/view-stats/summary', [PublicTourController::class, 'viewStatsSummary']);
    Route::post('tours/check-slug', [TourController::class, 'checkSlug']);
    Route::post('tours/preview-slug', [TourController::class, 'previewSlug']);
    Route::get('tours/{tour}/debug', [TourController::class, 'debug']);
    Route::patch('tours/{tour}/toggle-status', [TourController::class, 'toggleStatus']);
    Route::post('tours/{tour}/generate-slug', [TourController::class, 'generateSlug']);
    Route::post('tours/{tour}/recalculate', [TourController::class, 'recalculate']);
    Route::post('tours/{tour}/upload-cover-image', [TourController::class, 'uploadCoverImage']);
    Route::post('tours/{tour}/upload-pdf', [TourController::class, 'uploadPdf']);
    Route::post('tours/{tour}/upload-custom-cover-image', [TourController::class, 'uploadCustomCoverImage']);
    Route::post('tours/{tour}/upload-custom-pdf', [TourController::class, 'uploadCustomPdf']);
    Route::post('tours/{tour}/set-media-source', [TourController::class, 'setMediaSource']);
    Route::get('tours/{tour}/media-info', [TourController::class, 'getMediaInfo']);
    Route::delete('tours/{tour}/custom-cover-image', [TourController::class, 'removeCustomCoverImage']);
    Route::delete('tours/{tour}/custom-pdf', [TourController::class, 'removeCustomPdf']);
    Route::post('tours/mass-delete', [TourController::class, 'massDelete']);
    
    // Tour Manual Override Management (Smart Sync)
    Route::get('tours/{tour}/manual-overrides', [TourController::class, 'getManualOverrides']);
    Route::post('tours/{tour}/manual-overrides/mark', [TourController::class, 'markFieldsAsOverridden']);
    Route::post('tours/{tour}/manual-overrides/clear', [TourController::class, 'clearFieldOverrides']);
    Route::post('tours/{tour}/manual-overrides/clear-all', [TourController::class, 'clearAllOverrides']);
    Route::post('tours/{tour}/toggle-sync-lock', [TourController::class, 'toggleSyncLock']);
    
    Route::apiResource('tours', TourController::class);

    // Tour Periods CRUD
    Route::patch('tours/{tour}/periods/{period}/toggle-status', [PeriodController::class, 'toggleStatus']);
    Route::post('tours/{tour}/periods/bulk-update', [PeriodController::class, 'bulkUpdate']);
    Route::post('tours/{tour}/periods/mass-update-promo', [PeriodController::class, 'massUpdatePromo']);
    Route::post('tours/{tour}/periods/mass-update-discount', [PeriodController::class, 'massUpdateDiscount']);
    Route::apiResource('tours.periods', PeriodController::class);

    // Tour Itineraries CRUD
    Route::post('itineraries/upload-image', [App\Http\Controllers\TourItineraryController::class, 'uploadImageOnly']);
    Route::post('itineraries/delete-image', [App\Http\Controllers\TourItineraryController::class, 'deleteImage']);
    Route::post('tours/{tour}/itineraries/reorder', [App\Http\Controllers\TourItineraryController::class, 'reorder']);
    Route::post('tours/{tour}/itineraries/{itinerary}/upload-image', [App\Http\Controllers\TourItineraryController::class, 'uploadImage']);
    Route::post('tours/{tour}/itineraries/{itinerary}/remove-image', [App\Http\Controllers\TourItineraryController::class, 'removeImage']);
    Route::apiResource('tours.itineraries', App\Http\Controllers\TourItineraryController::class);

    // Promotions
    Route::post('promotions/{promotion}/upload-banner', [PromotionController::class, 'uploadBanner']);
    Route::delete('promotions/{promotion}/delete-banner', [PromotionController::class, 'deleteBanner']);
    Route::patch('promotions/{promotion}/toggle-status', [PromotionController::class, 'toggleStatus']);
    Route::post('promotions/reorder', [PromotionController::class, 'reorder']);
    Route::apiResource('promotions', PromotionController::class);

    // Promotion Notifications (admin)
    Route::prefix('promotion-notifications')->group(function () {
        Route::get('/meta', [PromotionNotificationController::class, 'meta']);
        Route::get('/claims/lookup', [PromotionNotificationController::class, 'lookupByCode']);
        Route::patch('/{id}/toggle-status', [PromotionNotificationController::class, 'toggleStatus']);
        Route::post('/{id}/upload-banner', [PromotionNotificationController::class, 'uploadBanner']);
        Route::delete('/{id}/delete-banner', [PromotionNotificationController::class, 'deleteBanner']);
        Route::get('/{id}/claims', [PromotionNotificationController::class, 'claims']);
        Route::patch('/claims/{claimId}/mark-used', [PromotionNotificationController::class, 'markClaimUsed']);
    });
    Route::apiResource('promotion-notifications', PromotionNotificationController::class);

    // Tour Tabs
    Route::get('tour-tabs/condition-options', [TourTabController::class, 'getConditionOptions']);
    Route::post('tour-tabs/preview-conditions', [TourTabController::class, 'previewConditions']);
    Route::get('tour-tabs/{tourTab}/preview', [TourTabController::class, 'preview']);
    Route::patch('tour-tabs/{tourTab}/toggle-status', [TourTabController::class, 'toggleStatus']);
    Route::post('tour-tabs/reorder', [TourTabController::class, 'reorder']);
    Route::apiResource('tour-tabs', TourTabController::class);

    // Recommended Tours
    Route::get('recommended-tours/condition-options', [RecommendedTourController::class, 'getConditionOptions']);
    Route::get('recommended-tours/settings', [RecommendedTourController::class, 'getSettings']);
    Route::put('recommended-tours/settings', [RecommendedTourController::class, 'updateSettings']);
    Route::post('recommended-tours/preview-conditions', [RecommendedTourController::class, 'previewConditions']);
    Route::get('recommended-tours/search-tours', [RecommendedTourController::class, 'searchTours']);
    Route::get('recommended-tours/{recommendedTourSection}/preview', [RecommendedTourController::class, 'preview']);
    Route::patch('recommended-tours/{recommendedTourSection}/toggle-status', [RecommendedTourController::class, 'toggleStatus']);
    Route::post('recommended-tours/reorder', [RecommendedTourController::class, 'reorder']);
    Route::apiResource('recommended-tours', RecommendedTourController::class)->parameters([
        'recommended-tours' => 'recommendedTourSection',
    ]);

    // International Tour Settings (Admin)
    Route::get('international-tour-settings/condition-options', [InternationalTourSettingController::class, 'getConditionOptions']);

    // Tour Reviews Management (Admin)
    Route::prefix('tour-reviews')->group(function () {
        Route::get('/', [TourReviewAdminController::class, 'index']);
        Route::get('/{id}', [TourReviewAdminController::class, 'show']);
        Route::post('/assisted', [TourReviewAdminController::class, 'createAssisted']);
        Route::post('/bulk-approve', [TourReviewAdminController::class, 'bulkApprove']);
        Route::patch('/{id}/approve', [TourReviewAdminController::class, 'approve']);
        Route::patch('/{id}/reject', [TourReviewAdminController::class, 'reject']);
        Route::post('/{id}/reply', [TourReviewAdminController::class, 'reply']);
        Route::patch('/{id}/toggle-featured', [TourReviewAdminController::class, 'toggleFeatured']);
        Route::post('/{id}/update', [TourReviewAdminController::class, 'update']);
        Route::delete('/{id}', [TourReviewAdminController::class, 'destroy']);
    });

    // Review Tags Management (Admin)
    Route::prefix('admin/review-tags')->group(function () {
        Route::get('/', [TourReviewAdminController::class, 'tagIndex']);
        Route::post('/', [TourReviewAdminController::class, 'tagStore']);
        Route::put('/{id}', [TourReviewAdminController::class, 'tagUpdate']);
        Route::delete('/{id}', [TourReviewAdminController::class, 'tagDestroy']);
        Route::patch('/{id}/toggle', [TourReviewAdminController::class, 'tagToggle']);
        Route::post('/reorder', [TourReviewAdminController::class, 'tagReorder']);
    });
    Route::post('international-tour-settings/preview-conditions', [InternationalTourSettingController::class, 'previewConditions']);
    Route::patch('international-tour-settings/{internationalTourSetting}/toggle-status', [InternationalTourSettingController::class, 'toggleStatus']);
    Route::post('international-tour-settings/{internationalTourSetting}/cover-image', [InternationalTourSettingController::class, 'uploadCoverImage']);
    Route::delete('international-tour-settings/{internationalTourSetting}/cover-image', [InternationalTourSettingController::class, 'deleteCoverImage']);
    Route::post('international-tour-settings/{internationalTourSetting}/country-cover/{countryId}', [InternationalTourSettingController::class, 'uploadCountryCover']);
    Route::delete('international-tour-settings/{internationalTourSetting}/country-cover/{countryId}', [InternationalTourSettingController::class, 'deleteCountryCover']);
    Route::patch('international-tour-settings/{internationalTourSetting}/country-cover/{countryId}/position', [InternationalTourSettingController::class, 'updateCountryCoverPosition']);
    Route::apiResource('international-tour-settings', InternationalTourSettingController::class);

    // Domestic Tour Settings (Admin)
    Route::get('domestic-tour-settings/condition-options', [DomesticTourSettingController::class, 'getConditionOptions']);
    Route::post('domestic-tour-settings/preview-conditions', [DomesticTourSettingController::class, 'previewConditions']);
    Route::patch('domestic-tour-settings/{domesticTourSetting}/toggle-status', [DomesticTourSettingController::class, 'toggleStatus']);
    Route::post('domestic-tour-settings/{domesticTourSetting}/cover-image', [DomesticTourSettingController::class, 'uploadCoverImage']);
    Route::delete('domestic-tour-settings/{domesticTourSetting}/cover-image', [DomesticTourSettingController::class, 'deleteCoverImage']);
    Route::post('domestic-tour-settings/{domesticTourSetting}/city-cover/{cityId}', [DomesticTourSettingController::class, 'uploadCityCover']);
    Route::delete('domestic-tour-settings/{domesticTourSetting}/city-cover/{cityId}', [DomesticTourSettingController::class, 'deleteCityCover']);
    Route::patch('domestic-tour-settings/{domesticTourSetting}/city-cover/{cityId}/position', [DomesticTourSettingController::class, 'updateCityCoverPosition']);
    Route::apiResource('domestic-tour-settings', DomesticTourSettingController::class);

    // Festival Holidays (Admin)
    Route::get('festival-page-settings', [FestivalHolidayController::class, 'getPageSettings']);
    Route::put('festival-page-settings', [FestivalHolidayController::class, 'updatePageSettings']);
    Route::post('festival-page-settings/cover-image', [FestivalHolidayController::class, 'uploadPageCoverImage']);
    Route::delete('festival-page-settings/cover-image', [FestivalHolidayController::class, 'deletePageCoverImage']);
    Route::patch('festival-holidays/{festivalHoliday}/toggle-status', [FestivalHolidayController::class, 'toggleStatus']);
    Route::post('festival-holidays/{festivalHoliday}/image', [FestivalHolidayController::class, 'uploadImage']);
    Route::delete('festival-holidays/{festivalHoliday}/image', [FestivalHolidayController::class, 'deleteImage']);
    Route::post('festival-holidays/{festivalHoliday}/cover-image', [FestivalHolidayController::class, 'uploadCoverImage']);
    Route::delete('festival-holidays/{festivalHoliday}/cover-image', [FestivalHolidayController::class, 'deleteCoverImage']);
    Route::get('festival-holidays/{festivalHoliday}/preview', [FestivalHolidayController::class, 'previewTours']);
    Route::apiResource('festival-holidays', FestivalHolidayController::class);

    // Tour Packages (Admin)
    Route::get('tour-package-page-settings', [TourPackageController::class, 'getPageSettings']);
    Route::put('tour-package-page-settings', [TourPackageController::class, 'updatePageSettings']);
    Route::post('tour-package-page-settings/cover-image', [TourPackageController::class, 'uploadPageCoverImage']);
    Route::delete('tour-package-page-settings/cover-image', [TourPackageController::class, 'deletePageCoverImage']);
    Route::patch('tour-packages/{tourPackage}/toggle-status', [TourPackageController::class, 'toggleStatus']);
    Route::post('tour-packages/{tourPackage}/image', [TourPackageController::class, 'uploadImage']);
    Route::delete('tour-packages/{tourPackage}/image', [TourPackageController::class, 'deleteImage']);
    Route::post('tour-packages/{tourPackage}/pdf', [TourPackageController::class, 'uploadPdf']);
    Route::delete('tour-packages/{tourPackage}/pdf', [TourPackageController::class, 'deletePdf']);
    Route::apiResource('tour-packages', TourPackageController::class);

    // Group Tours (Admin)
    Route::get('group-tour-settings', [GroupTourController::class, 'getSettings']);
    Route::put('group-tour-settings', [GroupTourController::class, 'updateSettings']);
    Route::post('group-tour-settings/hero-image', [GroupTourController::class, 'uploadHeroImage']);
    Route::delete('group-tour-settings/hero-image', [GroupTourController::class, 'deleteHeroImage']);
    Route::post('group-tour-settings/advantages-image', [GroupTourController::class, 'uploadAdvantagesImage']);
    Route::delete('group-tour-settings/advantages-image', [GroupTourController::class, 'deleteAdvantagesImage']);
    Route::get('group-tour-portfolios', [GroupTourController::class, 'listPortfolios']);
    Route::post('group-tour-portfolios/reorder', [GroupTourController::class, 'reorderPortfolios']);
    Route::post('group-tour-portfolios', [GroupTourController::class, 'storePortfolio']);
    Route::put('group-tour-portfolios/{portfolio}', [GroupTourController::class, 'updatePortfolio']);
    Route::delete('group-tour-portfolios/{portfolio}', [GroupTourController::class, 'destroyPortfolio']);
    Route::post('group-tour-portfolios/{portfolio}/image', [GroupTourController::class, 'uploadPortfolioImage']);
    Route::post('group-tour-portfolios/{portfolio}/logo', [GroupTourController::class, 'uploadPortfolioLogo']);
    Route::delete('group-tour-portfolios/{portfolio}/logo', [GroupTourController::class, 'deletePortfolioLogo']);
    Route::get('group-tour-inquiries/count-new', [GroupTourController::class, 'countNewInquiries']);
    Route::get('group-tour-inquiries', [GroupTourController::class, 'listInquiries']);
    Route::get('group-tour-inquiries/{inquiry}', [GroupTourController::class, 'showInquiry']);
    Route::put('group-tour-inquiries/{inquiry}', [GroupTourController::class, 'updateInquiry']);
    Route::delete('group-tour-inquiries/{inquiry}', [GroupTourController::class, 'destroyInquiry']);

    // Blog (Admin)
    Route::get('blog-categories', [BlogController::class, 'listCategories']);
    Route::post('blog-categories', [BlogController::class, 'storeCategory']);
    Route::put('blog-categories/{category}', [BlogController::class, 'updateCategory']);
    Route::delete('blog-categories/{category}', [BlogController::class, 'destroyCategory']);
    Route::post('blog-categories/reorder', [BlogController::class, 'reorderCategories']);
    Route::get('blog-posts', [BlogController::class, 'listPosts']);
    Route::post('blog-posts', [BlogController::class, 'storePost']);
    Route::get('blog-posts/{post}', [BlogController::class, 'showPost']);
    Route::put('blog-posts/{post}', [BlogController::class, 'updatePost']);
    Route::delete('blog-posts/{post}', [BlogController::class, 'destroyPost']);
    Route::post('blog-posts/{post}/cover-image', [BlogController::class, 'uploadCoverImage']);
    Route::delete('blog-posts/{post}/cover-image', [BlogController::class, 'deleteCoverImage']);
    Route::post('blog-posts/{post}/content-image', [BlogController::class, 'uploadContentImage']);
    Route::get('blog-settings', [BlogController::class, 'getPageSettings']);
    Route::put('blog-settings', [BlogController::class, 'updatePageSettings']);
    Route::post('blog-settings/hero-image', [BlogController::class, 'uploadHeroImage']);
    Route::delete('blog-settings/hero-image', [BlogController::class, 'deleteHeroImage']);

    // About Page (Admin)
    Route::get('about-settings', [AboutController::class, 'getSettings']);
    Route::put('about-settings', [AboutController::class, 'updateSettings']);
    Route::post('about-settings/hero-image', [AboutController::class, 'uploadHeroImage']);
    Route::delete('about-settings/hero-image', [AboutController::class, 'deleteHeroImage']);
    Route::post('about-settings/license-image', [AboutController::class, 'uploadLicenseImage']);
    Route::delete('about-settings/license-image', [AboutController::class, 'deleteLicenseImage']);

    Route::get('about-associations', [AboutController::class, 'listAssociations']);
    Route::post('about-associations', [AboutController::class, 'storeAssociation']);
    Route::put('about-associations/{id}', [AboutController::class, 'updateAssociation']);
    Route::delete('about-associations/{id}', [AboutController::class, 'destroyAssociation']);
    Route::post('about-associations/{id}/logo', [AboutController::class, 'uploadAssociationLogo']);
    Route::post('about-associations/reorder', [AboutController::class, 'reorderAssociations']);

    Route::get('about-services', [AboutController::class, 'listServices']);
    Route::post('about-services', [AboutController::class, 'storeService']);
    Route::put('about-services/{id}', [AboutController::class, 'updateService']);
    Route::delete('about-services/{id}', [AboutController::class, 'destroyService']);
    Route::post('about-services/reorder', [AboutController::class, 'reorderServices']);

    Route::get('about-customer-groups', [AboutController::class, 'listCustomerGroups']);
    Route::post('about-customer-groups', [AboutController::class, 'storeCustomerGroup']);
    Route::put('about-customer-groups/{id}', [AboutController::class, 'updateCustomerGroup']);
    Route::delete('about-customer-groups/{id}', [AboutController::class, 'destroyCustomerGroup']);
    Route::post('about-customer-groups/{id}/image', [AboutController::class, 'uploadCustomerGroupImage']);
    Route::post('about-customer-groups/reorder', [AboutController::class, 'reorderCustomerGroups']);

    Route::get('about-awards', [AboutController::class, 'listAwards']);
    Route::post('about-awards', [AboutController::class, 'storeAward']);
    Route::put('about-awards/{id}', [AboutController::class, 'updateAward']);
    Route::delete('about-awards/{id}', [AboutController::class, 'destroyAward']);
    Route::post('about-awards/{id}/image', [AboutController::class, 'uploadAwardImage']);
    Route::post('about-awards/reorder', [AboutController::class, 'reorderAwards']);

    // Flash Sales CRUD
    Route::apiResource('flash-sales', FlashSaleController::class);
    Route::patch('flash-sales/{flash_sale}/toggle-status', [FlashSaleController::class, 'toggleStatus']);
    Route::get('flash-sales-search-tours', [FlashSaleController::class, 'searchTours']);
    Route::post('flash-sales/{flash_sale}/items', [FlashSaleController::class, 'addItem']);
    Route::post('flash-sales/{flash_sale}/items-batch', [FlashSaleController::class, 'addItems']);
    Route::put('flash-sales/{flash_sale}/items/{item}', [FlashSaleController::class, 'updateItem']);
    Route::delete('flash-sales/{flash_sale}/items/{item}', [FlashSaleController::class, 'removeItem']);
    Route::post('flash-sales/{flash_sale}/items/reorder', [FlashSaleController::class, 'reorderItems']);
    Route::post('flash-sales/{flash_sale}/items/mass-update-discount', [FlashSaleController::class, 'massUpdateDiscount']);

    // Bookings (admin)
    Route::get('bookings', [BookingController::class, 'index']);
    Route::get('bookings/statistics', [BookingController::class, 'statistics']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::get('bookings/{id}', [BookingController::class, 'show']);
    Route::put('bookings/{id}', [BookingController::class, 'update']);
    Route::patch('bookings/{id}/status', [BookingController::class, 'updateStatus']);

    // Gallery Images CRUD
    Route::get('gallery/tags', [GalleryImageController::class, 'tags']);
    Route::get('gallery/statistics', [GalleryImageController::class, 'statistics']);
    Route::post('gallery/for-tour', [GalleryImageController::class, 'getForTour']);
    Route::post('gallery/bulk-upload', [GalleryImageController::class, 'bulkUpload']);
    Route::patch('gallery/{gallery}/toggle-status', [GalleryImageController::class, 'toggleStatus']);
    Route::apiResource('gallery', GalleryImageController::class)->parameters(['gallery' => 'gallery']);

    // Gallery Videos CRUD
    Route::get('gallery-videos/tags', [GalleryVideoController::class, 'tags']);
    Route::get('gallery-videos/statistics', [GalleryVideoController::class, 'statistics']);
    Route::patch('gallery-videos/{galleryVideo}/toggle-status', [GalleryVideoController::class, 'toggleStatus']);
    Route::post('gallery-videos/{galleryVideo}/replace-thumbnail', [GalleryVideoController::class, 'replaceThumbnail']);
    Route::apiResource('gallery-videos', GalleryVideoController::class);

    // Hero Slides CRUD
    Route::get('hero-slides/statistics', [\App\Http\Controllers\HeroSlideController::class, 'statistics']);
    Route::post('hero-slides/reorder', [\App\Http\Controllers\HeroSlideController::class, 'reorder']);
    Route::patch('hero-slides/{heroSlide}/toggle-status', [\App\Http\Controllers\HeroSlideController::class, 'toggleStatus']);
    Route::post('hero-slides/{heroSlide}/replace-image', [\App\Http\Controllers\HeroSlideController::class, 'replaceImage']);
    Route::apiResource('hero-slides', \App\Http\Controllers\HeroSlideController::class);

    // Our Clients CRUD (ลูกค้าของเรา)
    Route::get('our-clients/statistics', [\App\Http\Controllers\OurClientController::class, 'statistics']);
    Route::post('our-clients/reorder', [\App\Http\Controllers\OurClientController::class, 'reorder']);
    Route::patch('our-clients/{ourClient}/toggle-status', [\App\Http\Controllers\OurClientController::class, 'toggleStatus']);
    Route::post('our-clients/{ourClient}/replace-image', [\App\Http\Controllers\OurClientController::class, 'replaceImage']);
    Route::apiResource('our-clients', \App\Http\Controllers\OurClientController::class);

    // Popups CRUD
    Route::get('popups/statistics', [\App\Http\Controllers\PopupController::class, 'statistics']);
    Route::post('popups/reorder', [\App\Http\Controllers\PopupController::class, 'reorder']);
    Route::patch('popups/{popup}/toggle-status', [\App\Http\Controllers\PopupController::class, 'toggleStatus']);
    Route::post('popups/{popup}/replace-image', [\App\Http\Controllers\PopupController::class, 'replaceImage']);
    Route::apiResource('popups', \App\Http\Controllers\PopupController::class);

    // Menus CRUD
    Route::get('menus/locations', [\App\Http\Controllers\MenuController::class, 'locations']);
    Route::post('menus/reorder', [\App\Http\Controllers\MenuController::class, 'reorder']);
    Route::patch('menus/{menu}/toggle-status', [\App\Http\Controllers\MenuController::class, 'toggleStatus']);
    Route::apiResource('menus', \App\Http\Controllers\MenuController::class);

    // SEO Settings
    Route::get('seo/pages', [\App\Http\Controllers\SeoController::class, 'pages']);
    Route::get('seo', [\App\Http\Controllers\SeoController::class, 'index']);
    Route::get('seo/{slug}', [\App\Http\Controllers\SeoController::class, 'show']);
    Route::put('seo/{slug}', [\App\Http\Controllers\SeoController::class, 'update']);
    Route::post('seo/{slug}/og-image', [\App\Http\Controllers\SeoController::class, 'uploadOgImage']);

    // Site Contacts
    Route::get('site-contacts', [\App\Http\Controllers\SeoController::class, 'contactIndex']);
    Route::post('site-contacts', [\App\Http\Controllers\SeoController::class, 'contactStore']);
    Route::put('site-contacts/{siteContact}', [\App\Http\Controllers\SeoController::class, 'contactUpdate']);
    Route::delete('site-contacts/{siteContact}', [\App\Http\Controllers\SeoController::class, 'contactDestroy']);
    Route::patch('site-contacts/{siteContact}/toggle', [\App\Http\Controllers\SeoController::class, 'contactToggle']);

    // Contact Page Settings
    Route::get('contact-settings', [ContactController::class, 'getSettings']);
    Route::put('contact-settings', [ContactController::class, 'updateSettings']);
    Route::post('contact-settings/hero-image', [ContactController::class, 'uploadHeroImage']);
    Route::delete('contact-settings/hero-image', [ContactController::class, 'deleteHeroImage']);

    // Contact Messages
    Route::get('contact-messages', [ContactController::class, 'messageIndex']);
    Route::get('contact-messages/unread-count', [ContactController::class, 'unreadCount']);
    Route::get('contact-messages/{contactMessage}', [ContactController::class, 'messageShow']);
    Route::put('contact-messages/{contactMessage}', [ContactController::class, 'messageUpdate']);
    Route::delete('contact-messages/{contactMessage}', [ContactController::class, 'messageDestroy']);

    // Popular Countries CRUD
    Route::get('popular-countries/filter-options', [\App\Http\Controllers\PopularCountryController::class, 'filterOptions']);
    Route::post('popular-countries/preview-settings', [\App\Http\Controllers\PopularCountryController::class, 'previewSettings']);
    Route::post('popular-countries/reorder', [\App\Http\Controllers\PopularCountryController::class, 'reorder']);
    Route::get('popular-countries/{id}/preview', [\App\Http\Controllers\PopularCountryController::class, 'preview']);
    Route::post('popular-countries/{id}/clear-cache', [\App\Http\Controllers\PopularCountryController::class, 'clearCache']);
    Route::patch('popular-countries/{id}/toggle-status', [\App\Http\Controllers\PopularCountryController::class, 'toggleStatus']);
    Route::post('popular-countries/{settingId}/items/{countryId}/image', [\App\Http\Controllers\PopularCountryController::class, 'uploadItemImage']);
    Route::delete('popular-countries/{settingId}/items/{countryId}/image', [\App\Http\Controllers\PopularCountryController::class, 'deleteItemImage']);
    Route::apiResource('popular-countries', \App\Http\Controllers\PopularCountryController::class);

    // Integrations (Wholesaler API Configs)
    Route::prefix('integrations')->group(function () {
        Route::get('/', [IntegrationController::class, 'index']);
        Route::post('/', [IntegrationController::class, 'store']);
        Route::get('/section-definitions', [IntegrationController::class, 'getSectionDefinitions']);
        // test-connection, fetch-sample, mappings, and test-mapping moved to public routes above
        Route::get('/{id}', [IntegrationController::class, 'show']);
        Route::put('/{id}', [IntegrationController::class, 'update']);
        Route::delete('/{id}', [IntegrationController::class, 'destroy']);
        Route::post('/{id}/toggle-sync', [IntegrationController::class, 'toggleSync']);
        Route::post('/{id}/health-check', [IntegrationController::class, 'healthCheck']);
        Route::post('/{wholesalerId}/preview-mapping', [IntegrationController::class, 'previewMapping']);
        Route::get('/{id}/sync-history', [IntegrationController::class, 'getSyncHistory']);
        
        // Preview Sync - Fetch, map, and show what will be synced
        Route::post('/{id}/preview-sync', [IntegrationController::class, 'previewSync']);
        
        // Sync Now
        Route::post('/{id}/sync-now', [IntegrationController::class, 'syncNow']);
        
        // PDF Branding - Header/Footer upload
        Route::post('/{id}/upload-header', [IntegrationController::class, 'uploadHeader']);
        Route::post('/{id}/upload-footer', [IntegrationController::class, 'uploadFooter']);
        Route::delete('/{id}/header', [IntegrationController::class, 'removeHeader']);
        Route::delete('/{id}/footer', [IntegrationController::class, 'removeFooter']);
        
        // Aggregation Config per Wholesaler
        Route::get('/{id}/aggregation-config', [IntegrationController::class, 'getAggregationConfig']);
        Route::put('/{id}/aggregation-config', [IntegrationController::class, 'updateAggregationConfig']);
        
        // Smart Sync Settings
        Route::get('/{id}/sync-settings', [IntegrationController::class, 'getSyncSettings']);
        Route::put('/{id}/sync-settings', [IntegrationController::class, 'updateSyncSettings']);
        
        // Auto-close expired periods/tours
        Route::post('/{id}/auto-close-expired', [IntegrationController::class, 'runAutoClose']);
        
        // Test Notification
        Route::post('/{id}/test-notification', [IntegrationController::class, 'testNotification']);
    });

    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::get('/aggregation', [SettingsController::class, 'getAggregationConfig']);
        Route::put('/aggregation', [SettingsController::class, 'updateAggregationConfig']);
        
        // SMTP Settings
        Route::get('/smtp', [SettingsController::class, 'getSmtpConfig']);
        Route::put('/smtp', [SettingsController::class, 'updateSmtpConfig']);
        Route::post('/smtp/test', [SettingsController::class, 'testSmtpConfig']);
        
        // OTP Settings
        Route::get('/otp', [SettingsController::class, 'getOtpConfig']);
        Route::put('/otp', [SettingsController::class, 'updateOtpConfig']);
        Route::post('/otp/test', [SettingsController::class, 'testOtpConfig']);

        // Footer Settings
        Route::get('/footer', [SettingsController::class, 'getFooterConfig']);
        Route::put('/footer', [SettingsController::class, 'updateFooterConfig']);
        Route::post('/footer/upload-qr', [SettingsController::class, 'uploadLineQrImage']);

        // Why Choose Us Settings
        Route::get('/why-choose-us', [SettingsController::class, 'getWhyChooseUsConfig']);
        Route::put('/why-choose-us', [SettingsController::class, 'updateWhyChooseUsConfig']);

        // Subscriber SMTP Settings
        Route::get('/subscriber-smtp', [SubscriberController::class, 'getSubscriberSmtp']);
        Route::put('/subscriber-smtp', [SubscriberController::class, 'updateSubscriberSmtp']);
        Route::post('/subscriber-smtp/test', [SubscriberController::class, 'testSubscriberSmtp']);
        
        Route::get('/{key}', [SettingsController::class, 'show']);
        Route::put('/{key}', [SettingsController::class, 'update']);
    });

    // Subscribers & Newsletters
    Route::prefix('subscribers')->group(function () {
        Route::get('/', [SubscriberController::class, 'index']);
        Route::get('/stats', [SubscriberController::class, 'stats']);
        Route::get('/export', [SubscriberController::class, 'export']);
        Route::get('/{subscriber}', [SubscriberController::class, 'show']);
        Route::delete('/{subscriber}', [SubscriberController::class, 'destroy']);
    });

    Route::prefix('newsletters')->group(function () {
        Route::get('/', [SubscriberController::class, 'newsletterIndex']);
        Route::post('/', [SubscriberController::class, 'newsletterStore']);
        Route::post('/preview-count', [SubscriberController::class, 'newsletterPreviewCount']);
        Route::get('/{newsletter}', [SubscriberController::class, 'newsletterShow']);
        Route::put('/{newsletter}', [SubscriberController::class, 'newsletterUpdate']);
        Route::delete('/{newsletter}', [SubscriberController::class, 'newsletterDestroy']);
        Route::post('/{newsletter}/send', [SubscriberController::class, 'newsletterSend']);
        Route::post('/{newsletter}/cancel', [SubscriberController::class, 'newsletterCancel']);
    });

    // System Settings (Global settings for sync, auto-close, etc.)
    Route::prefix('system-settings')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\SystemSettingsController::class, 'index']);
        Route::put('/', [\App\Http\Controllers\Api\SystemSettingsController::class, 'update']);
        Route::post('/clear-cache', [\App\Http\Controllers\Api\SystemSettingsController::class, 'clearCache']);
        
        // Sync Settings (Smart Sync)
        Route::get('/sync', [\App\Http\Controllers\Api\SystemSettingsController::class, 'getSyncSettings']);
        Route::put('/sync', [\App\Http\Controllers\Api\SystemSettingsController::class, 'updateSyncSettings']);
        
        // Auto-Close Settings
        Route::get('/auto-close', [\App\Http\Controllers\Api\SystemSettingsController::class, 'getAutoCloseSettings']);
        Route::put('/auto-close', [\App\Http\Controllers\Api\SystemSettingsController::class, 'updateAutoCloseSettings']);
        Route::post('/auto-close/run', [\App\Http\Controllers\Api\SystemSettingsController::class, 'runAutoClose']);
        
        // Get by group
        Route::get('/group/{group}', [\App\Http\Controllers\Api\SystemSettingsController::class, 'getByGroup']);
    });

    // Page Content Management (จัดการเนื้อหาเว็บไซต์)
    Route::prefix('page-content')->group(function () {
        Route::get('/', [PageContentController::class, 'index']);
        Route::get('/{key}', [PageContentController::class, 'show']);
        Route::put('/{key}', [PageContentController::class, 'update']);
        Route::post('/{key}/image', [PageContentController::class, 'uploadImage']);
        Route::delete('/{key}/image', [PageContentController::class, 'deleteImage']);
    });

    // User route (default)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

/*
|--------------------------------------------------------------------------
| Web Member API Routes (for tour-web frontend)
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Web\WebAuthController;
use App\Http\Controllers\Web\WebWishlistController;
use App\Http\Controllers\Web\WebBookingController;

Route::prefix('web')->group(function () {
    // Public routes
    // Sales users (for booking form dropdown)
    Route::get('/sales', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\User::active()->sales()->select('id', 'name')->orderBy('name')->get(),
        ]);
    });

    // Public auth routes
    Route::prefix('auth')->group(function () {
        // Registration
        Route::post('/register/request-otp', [WebAuthController::class, 'requestRegisterOtp']);
        Route::post('/register', [WebAuthController::class, 'register']);
        
        // Login with password
        Route::post('/login', [WebAuthController::class, 'login']);
        
        // Login with OTP
        Route::post('/login/request-otp', [WebAuthController::class, 'requestLoginOtp']);
        Route::post('/login/verify-otp', [WebAuthController::class, 'verifyLoginOtp']);
        
        // Password reset
        Route::post('/forgot-password', [WebAuthController::class, 'requestPasswordReset']);
        Route::post('/reset-password', [WebAuthController::class, 'resetPassword']);
    });

    // Booking routes (public - guest + member)
    Route::prefix('booking')->group(function () {
        Route::post('/request-otp', [WebBookingController::class, 'requestOtp']);
        Route::post('/verify-otp', [WebBookingController::class, 'verifyOtp']);
        Route::post('/submit', [WebBookingController::class, 'submit']);
    });

    // Public page content (เงื่อนไขการให้บริการ, เงื่อนไขการชำระเงิน)
    Route::get('/page-content/{key}', [PageContentController::class, 'getPublicContent']);

    // Protected routes (member auth required)
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [WebAuthController::class, 'logout']);

        // Flash Sale Booking (requires login)
        Route::post('/booking/flash-sale', [WebBookingController::class, 'submitFlashSale']);

        // My Bookings
        Route::get('/bookings', [WebBookingController::class, 'myBookings']);
        Route::get('/bookings/{id}', [WebBookingController::class, 'showBooking']);
        
        // Profile
        Route::get('/me', [WebAuthController::class, 'me']);
        Route::put('/profile', [WebAuthController::class, 'updateProfile']);
        Route::put('/password', [WebAuthController::class, 'changePassword']);
        
        // Wishlist
        Route::prefix('wishlist')->group(function () {
            Route::get('/', [WebWishlistController::class, 'index']);
            Route::get('/count', [WebWishlistController::class, 'count']);
            Route::post('/', [WebWishlistController::class, 'store']);
            Route::post('/toggle', [WebWishlistController::class, 'toggle']);
            Route::get('/check/{tourId}', [WebWishlistController::class, 'check']);
            Route::delete('/{tourId}', [WebWishlistController::class, 'destroy']);
        });
        
        // Billing Addresses
        Route::prefix('billing-addresses')->group(function () {
            Route::get('/', [\App\Http\Controllers\Web\WebBillingAddressController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Web\WebBillingAddressController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Web\WebBillingAddressController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Web\WebBillingAddressController::class, 'destroy']);
            Route::put('/{id}/default', [\App\Http\Controllers\Web\WebBillingAddressController::class, 'setDefault']);
        });

        // Tour Reviews (member)
        Route::prefix('reviews')->group(function () {
            Route::get('/my', [\App\Http\Controllers\Web\WebTourReviewController::class, 'myReviews']);
            Route::get('/{tourSlug}/can-review', [\App\Http\Controllers\Web\WebTourReviewController::class, 'canReview']);
            Route::post('/{tourSlug}', [\App\Http\Controllers\Web\WebTourReviewController::class, 'store']);
        });

        // Member Points
        Route::prefix('points')->group(function () {
            Route::get('/summary', [\App\Http\Controllers\Web\WebMemberPointController::class, 'summary']);
            Route::get('/history', [\App\Http\Controllers\Web\WebMemberPointController::class, 'history']);
            Route::get('/levels', [\App\Http\Controllers\Web\WebMemberPointController::class, 'levels']);
            Route::get('/rules', [\App\Http\Controllers\Web\WebMemberPointController::class, 'rules']);
            Route::post('/preview-redeem', [\App\Http\Controllers\Web\WebMemberPointController::class, 'previewRedeem']);
            Route::post('/redeem', [\App\Http\Controllers\Web\WebMemberPointController::class, 'redeem']);
        });

        // Notifications (promotion notifications for member)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Web\WebNotificationController::class, 'index']);
            Route::get('/unread-count', [\App\Http\Controllers\Web\WebNotificationController::class, 'unreadCount']);
            Route::post('/read-all', [\App\Http\Controllers\Web\WebNotificationController::class, 'markAllRead']);
            Route::get('/{id}', [\App\Http\Controllers\Web\WebNotificationController::class, 'show']);
            Route::post('/{id}/read', [\App\Http\Controllers\Web\WebNotificationController::class, 'markRead']);
            Route::post('/{id}/claim', [\App\Http\Controllers\Web\WebNotificationController::class, 'claim']);
        });
    });
});
