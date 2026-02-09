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
use App\Http\Controllers\TourTabController;
use App\Http\Controllers\GalleryImageController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\WholesalerSyncController;
use App\Http\Controllers\Api\TourSearchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\PageContentController;

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

// Public Popular Countries (for tour-web homepage)
Route::get('popular-countries/public', [\App\Http\Controllers\PopularCountryController::class, 'publicList']);

// Public Promotions (for tour-web homepage)
Route::get('promotions/public', [PromotionController::class, 'publicList']);

// Public Tour Tabs (for tour-web homepage)
Route::get('tour-tabs/public', [TourTabController::class, 'publicList']);
Route::get('tour-tabs/public/{slug}', [TourTabController::class, 'publicShow']);

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
    Route::get('tours/{tour}/debug', [TourController::class, 'debug']);
    Route::patch('tours/{tour}/toggle-status', [TourController::class, 'toggleStatus']);
    Route::patch('tours/{tour}/toggle-publish', [TourController::class, 'togglePublish']);
    Route::post('tours/{tour}/recalculate', [TourController::class, 'recalculate']);
    Route::post('tours/{tour}/upload-cover-image', [TourController::class, 'uploadCoverImage']);
    Route::post('tours/{tour}/upload-pdf', [TourController::class, 'uploadPdf']);
    Route::post('tours/mass-delete', [TourController::class, 'massDelete']);
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

    // Tour Tabs
    Route::get('tour-tabs/condition-options', [TourTabController::class, 'getConditionOptions']);
    Route::post('tour-tabs/preview-conditions', [TourTabController::class, 'previewConditions']);
    Route::get('tour-tabs/{tourTab}/preview', [TourTabController::class, 'preview']);
    Route::patch('tour-tabs/{tourTab}/toggle-status', [TourTabController::class, 'toggleStatus']);
    Route::post('tour-tabs/reorder', [TourTabController::class, 'reorder']);
    Route::apiResource('tour-tabs', TourTabController::class);

    // Gallery Images CRUD
    Route::get('gallery/tags', [GalleryImageController::class, 'tags']);
    Route::get('gallery/statistics', [GalleryImageController::class, 'statistics']);
    Route::post('gallery/for-tour', [GalleryImageController::class, 'getForTour']);
    Route::post('gallery/bulk-upload', [GalleryImageController::class, 'bulkUpload']);
    Route::patch('gallery/{gallery}/toggle-status', [GalleryImageController::class, 'toggleStatus']);
    Route::apiResource('gallery', GalleryImageController::class)->parameters(['gallery' => 'gallery']);

    // Hero Slides CRUD
    Route::get('hero-slides/statistics', [\App\Http\Controllers\HeroSlideController::class, 'statistics']);
    Route::post('hero-slides/reorder', [\App\Http\Controllers\HeroSlideController::class, 'reorder']);
    Route::patch('hero-slides/{heroSlide}/toggle-status', [\App\Http\Controllers\HeroSlideController::class, 'toggleStatus']);
    Route::post('hero-slides/{heroSlide}/replace-image', [\App\Http\Controllers\HeroSlideController::class, 'replaceImage']);
    Route::apiResource('hero-slides', \App\Http\Controllers\HeroSlideController::class);

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
        
        Route::get('/{key}', [SettingsController::class, 'show']);
        Route::put('/{key}', [SettingsController::class, 'update']);
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

Route::prefix('web')->group(function () {
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

    // Public page content (เงื่อนไขการให้บริการ, เงื่อนไขการชำระเงิน)
    Route::get('/page-content/{key}', [PageContentController::class, 'getPublicContent']);

    // Protected routes (member auth required)
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [WebAuthController::class, 'logout']);
        
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
    });
});
