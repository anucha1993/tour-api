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
use App\Http\Controllers\GalleryImageController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\WholesalerSyncController;
use App\Http\Controllers\Api\TourSearchController;
use App\Http\Controllers\SettingsController;

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

// Protected routes (auth required)
// Integrations (Wholesaler API Configs)
// Public endpoints for testing/preview (no auth needed as it tests external API)
Route::post('integrations/test-connection', [IntegrationController::class, 'testConnection']);
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

// Queue & Sync Status (for monitoring/debugging)
Route::get('queue/status', [IntegrationController::class, 'getQueueStatus']);
Route::post('queue/fix-stuck', [IntegrationController::class, 'fixStuckSyncs']);
Route::post('queue/process', [IntegrationController::class, 'processQueue']);

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

    // Users CRUD
    Route::apiResource('users', UserController::class);

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
    Route::apiResource('promotions', PromotionController::class);

    // Gallery Images CRUD
    Route::get('gallery/tags', [GalleryImageController::class, 'tags']);
    Route::get('gallery/statistics', [GalleryImageController::class, 'statistics']);
    Route::post('gallery/for-tour', [GalleryImageController::class, 'getForTour']);
    Route::post('gallery/bulk-upload', [GalleryImageController::class, 'bulkUpload']);
    Route::patch('gallery/{gallery}/toggle-status', [GalleryImageController::class, 'toggleStatus']);
    Route::apiResource('gallery', GalleryImageController::class)->parameters(['gallery' => 'gallery']);

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
    });

    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::get('/aggregation', [SettingsController::class, 'getAggregationConfig']);
        Route::put('/aggregation', [SettingsController::class, 'updateAggregationConfig']);
        Route::get('/{key}', [SettingsController::class, 'show']);
        Route::put('/{key}', [SettingsController::class, 'update']);
    });

    // User route (default)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
