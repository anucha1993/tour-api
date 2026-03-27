<?php

namespace App\Services\WholesalerAdapters;

use App\Services\WholesalerAdapters\Contracts\DTOs\SyncResult;
use Illuminate\Support\Facades\DB;

/**
 * Base class for Headcode adapters
 *
 * Headcode adapters are custom PHP files stored in storage/headcode/.
 * Each file must define ONE class named Headcode{Filename}Adapter that extends this class.
 *
 * Example (storage/headcode/lookplanets.php):
 *
 *   class HeadcodeLookplanetsAdapter extends \App\Services\WholesalerAdapters\HeadcodeBaseAdapter
 *   {
 *       public function fetchTours(?string $cursor = null): \App\Services\WholesalerAdapters\Contracts\DTOs\SyncResult
 *       {
 *           $data = $this->httpGet('https://api.example.com/tours');
 *           return $this->buildSyncResult($data['items'] ?? []);
 *       }
 *
 *       public function fetchTourDetail(string $code): ?array
 *       {
 *           return null; // optional
 *       }
 *   }
 *
 * Provided helpers:
 *   - httpGet(string $url, array $params = []): array
 *   - httpPost(string $url, array $data = []): array
 *   - lookupCountryId(string $iso2): ?int
 *   - lookupTransportId(string $name): ?int
 *   - buildSyncResult(array $tours, ?string $nextCursor = null): SyncResult
 *   - $this->config  — WholesalerApiConfig model instance
 *   - $this->wholesalerId — int
 *
 * All HTTP logic (auth, retry, logging) is inherited from BaseAdapter.
 * If the headcode adapter needs its own HTTP calls outside the configured
 * base URL, use httpGet()/httpPost() with the full URL.
 */
abstract class HeadcodeBaseAdapter extends BaseAdapter
{
    /**
     * Health check for headcode adapters.
     * Instead of calling a /health endpoint (there isn't one),
     * verify the adapter file is loadable and report based on sync history.
     */
    public function healthCheck(): bool
    {
        $healthy = true;

        // Check adapter file exists
        $file = $this->config->headcode_file ?? '';
        if ($file && !file_exists(storage_path("headcode/{$file}.php"))) {
            $healthy = false;
        }

        $this->config->update([
            'last_health_check_at' => now(),
            'last_health_check_status' => $healthy,
        ]);

        return $healthy;
    }

    // ───────────────────────────────────────────────────────────
    // SIMPLE HTTP HELPERS
    // ───────────────────────────────────────────────────────────

    /**
     * GET request — returns parsed JSON response array.
     * Logs every call to outbound_api_logs (use for Phase 1 / single calls).
     *
     * @param string $url    Full URL (absolute) or relative path
     * @param array  $params Query parameters appended to URL
     */
    protected function httpGet(string $url, array $params = []): array
    {
        if (!empty($params)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($params);
        }

        return $this->request('GET', $url);
    }

    /**
     * GET request WITHOUT database logging.
     * Use this for bulk/Phase-2 requests where logging every call would
     * add hundreds of DB round-trips and degrade performance significantly.
     * Auth headers from config are applied automatically.
     *
     * @param string $url    Full absolute URL
     * @param array  $params Query parameters appended to URL
     */
    protected function httpGetQuiet(string $url, array $params = []): array
    {
        if (!empty($params)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($params);
        }

        $client = \Illuminate\Support\Facades\Http
            ::timeout($this->config->request_timeout_seconds ?? 30)
            ->connectTimeout($this->config->connect_timeout_seconds ?? 10)
            ->withHeaders($this->getDefaultHeaders());

        // Apply auth (re-use base class helper)
        $client = $this->applyAuth($client);

        $response = $client->get($url);

        if (!$response->successful()) {
            throw new \Exception(
                "API Error {$response->status()}: " . $response->body(),
                $response->status()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * POST request — returns parsed JSON response array.
     *
     * @param string $url  Full URL (absolute) or relative path
     * @param array  $data POST body (will be sent as JSON)
     */
    protected function httpPost(string $url, array $data = []): array
    {
        return $this->request('POST', $url, $data);
    }

    // ───────────────────────────────────────────────────────────
    // DATABASE LOOKUP HELPERS
    // ───────────────────────────────────────────────────────────

    /**
     * Look up country ID from ISO 3166-1 alpha-2 code.
     *
     * @param string $iso2  e.g. 'TH', 'JP', 'HK', 'CN'
     * @return int|null null if not found
     */
    protected function lookupCountryId(string $iso2): ?int
    {
        $id = DB::table('countries')->where('iso2', strtoupper(trim($iso2)))->value('id');
        return $id !== null ? (int) $id : null;
    }

    /**
     * Look up transport ID from name.
     *
     * @param string $name  e.g. 'Thai Airways', 'AirAsia'
     * @return int|null null if not found
     */
    protected function lookupTransportId(string $name): ?int
    {
        $id = DB::table('transports')->where('name', trim($name))->value('id');
        return $id !== null ? (int) $id : null;
    }

    // ───────────────────────────────────────────────────────────
    // SYNC RESULT HELPERS
    // ───────────────────────────────────────────────────────────

    /**
     * Build a successful SyncResult from raw tour data arrays.
     *
     * Each item in $tours is a raw array that SyncToursJob will process
     * using the wholesaler_field_mappings configured for this wholesaler.
     *
     * @param array       $tours      Raw tour data from the external API
     * @param string|null $nextCursor Cursor for next batch (null = no more pages)
     */
    protected function buildSyncResult(array $tours, ?string $nextCursor = null): SyncResult
    {
        return SyncResult::success(
            tours: $tours,
            nextCursor: $nextCursor,
            hasMore: $nextCursor !== null,
        );
    }
}
