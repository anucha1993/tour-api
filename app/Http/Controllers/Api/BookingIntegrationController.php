<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Manages outbound Booking API configuration per integration.
 *
 * Endpoints:
 *   GET    /api/integrations/booking/providers
 *   GET    /api/integrations/booking/providers/{code}/schema
 *   GET    /api/integrations/{id}/booking
 *   PUT    /api/integrations/{id}/booking
 *   POST   /api/integrations/{id}/booking/test
 */
class BookingIntegrationController extends Controller
{
    /**
     * List all available booking providers with their schemas.
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AdapterFactory::listBookingProviders(),
        ]);
    }

    /**
     * Get the config schema for a specific booking provider.
     */
    public function providerSchema(string $code): JsonResponse
    {
        $schema = AdapterFactory::getBookingProviderSchema($code);
        if (!$schema) {
            return response()->json([
                'success' => false,
                'message' => "Unknown booking provider: {$code}",
            ], 404);
        }
        return response()->json(['success' => true, 'data' => $schema]);
    }

    /**
     * Get current booking config for an integration.
     */
    public function show(int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'integration_id' => $config->id,
                'wholesaler_id' => $config->wholesaler_id,
                'booking_provider' => $config->booking_provider,
                'booking_enabled' => (bool) $config->booking_enabled,
                'booking_config' => $config->booking_config ?? [],
                'booking_hold_ttl_seconds' => $config->booking_hold_ttl_seconds ?? 900,
                'provider_schema' => $config->booking_provider
                    ? AdapterFactory::getBookingProviderSchema($config->booking_provider)
                    : null,
            ],
        ]);
    }

    /**
     * Update booking config for an integration.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'booking_enabled' => 'nullable|boolean',
            'booking_provider' => 'nullable|string|max:50',
            'booking_config' => 'nullable|array',
            'booking_hold_ttl_seconds' => 'nullable|integer|min:60|max:7200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $provider = $data['booking_provider'] ?? $config->booking_provider;

        // If enabling booking, provider must be a known one and required fields filled
        if (!empty($data['booking_enabled'])) {
            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'booking_provider is required when enabling booking',
                ], 422);
            }
            $schema = AdapterFactory::getBookingProviderSchema($provider);
            if (!$schema) {
                return response()->json([
                    'success' => false,
                    'message' => "Unknown booking provider: {$provider}",
                ], 422);
            }
            $bookingConfig = $data['booking_config'] ?? ($config->booking_config ?? []);
            $missing = [];
            foreach ($schema['schema'] as $field) {
                if (($field['required'] ?? false) && empty($bookingConfig[$field['key']])) {
                    $missing[] = $field['label'];
                }
            }
            if (!empty($missing)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Required booking config fields missing: ' . implode(', ', $missing),
                ], 422);
            }
        }

        try {
            $config->fill(array_filter([
                'booking_enabled' => $data['booking_enabled'] ?? null,
                'booking_provider' => $data['booking_provider'] ?? null,
                'booking_config' => $data['booking_config'] ?? null,
                'booking_hold_ttl_seconds' => $data['booking_hold_ttl_seconds'] ?? null,
            ], fn($v) => $v !== null));
            $config->save();

            return response()->json([
                'success' => true,
                'message' => 'Booking config saved',
                'data' => [
                    'booking_enabled' => (bool) $config->booking_enabled,
                    'booking_provider' => $config->booking_provider,
                    'booking_config' => $config->booking_config,
                    'booking_hold_ttl_seconds' => $config->booking_hold_ttl_seconds,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to save booking config', [
                'integration_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save booking config: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health-check the configured booking provider.
     */
    public function test(int $id): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);
        if (!$config->booking_enabled || !$config->booking_provider) {
            return response()->json([
                'success' => false,
                'message' => 'Booking is not enabled for this integration',
            ], 400);
        }

        try {
            $adapter = AdapterFactory::createBookingAdapter($config->wholesaler_id);
            $errors = $adapter->validateConfig();
            $healthy = empty($errors) && $adapter->healthCheck();

            return response()->json([
                'success' => $healthy,
                'data' => [
                    'provider' => $config->booking_provider,
                    'healthy' => $healthy,
                    'config_errors' => $errors,
                    'supports' => [
                        'real_hold' => $adapter->supports('real_hold'),
                        'cancel' => $adapter->supports('cancel'),
                        'modify' => $adapter->supports('modify'),
                        'multi_room' => $adapter->supports('multi_room'),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
