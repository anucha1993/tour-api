<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * RemoteFileProbe
 *
 * Cheaply fingerprints a remote media file (PDF / cover image) using an HTTP
 * HEAD request — WITHOUT downloading the body. Returns filename, byte size,
 * ETag and Last-Modified so the sync can detect when a wholesaler silently
 * replaces a file behind the same or a different URL.
 *
 * Most CDN/object-storage/IIS hosts return Content-Length + ETag + Last-Modified.
 * A few (Google Drive share links, some dev hosts) return no metadata — in that
 * case only the URL / filename is comparable and content-only changes cannot be
 * detected here (documented limitation).
 */
class RemoteFileProbe
{
    /**
     * Probe a remote URL and return its fingerprint.
     *
     * @return array{
     *   url:string, ok:bool, name:?string, size:?int, etag:?string, modified:?string
     * }
     */
    public function probe(string $url): array
    {
        $out = [
            'url' => $url,
            'ok' => false,
            'name' => $this->filenameFromUrl($url),
            'size' => null,
            'etag' => null,
            'modified' => null,
        ];

        try {
            $response = Http::timeout(12)
                ->connectTimeout(6)
                ->withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'NexttripMediaProbe/1.0',
                    'Accept' => '*/*',
                ])
                ->head($url);

            if ($response->successful()) {
                $out['ok'] = true;

                $length = $response->header('Content-Length');
                if ($length !== null && $length !== '' && is_numeric($length)) {
                    $out['size'] = (int) $length;
                }

                $etag = $response->header('ETag');
                if ($etag) {
                    $out['etag'] = trim($etag);
                }

                $lastModified = $response->header('Last-Modified');
                if ($lastModified) {
                    try {
                        $out['modified'] = Carbon::parse($lastModified)->utc()->format('Y-m-d H:i:s');
                    } catch (\Throwable $e) {
                        // Unparseable date — ignore, keep null
                    }
                }
            }
        } catch (\Throwable $e) {
            // Host unreachable / blocks HEAD — leave ok=false, name still usable
        }

        return $out;
    }

    /**
     * Decide whether an incoming file differs from previously stored metadata.
     *
     * Content signals (size/etag/modified) are only trusted when the fresh probe
     * succeeded, to avoid false "changed" results on a transient probe failure.
     * A filename change is always treated as a change.
     */
    public function hasChanged(array $stored, array $fresh): bool
    {
        // Filename change is a strong, transport-independent signal.
        if (!empty($fresh['name']) && !empty($stored['name']) && $fresh['name'] !== $stored['name']) {
            return true;
        }

        if (!($fresh['ok'] ?? false)) {
            return false;
        }

        if (!empty($fresh['size']) && !empty($stored['size']) && (int) $fresh['size'] !== (int) $stored['size']) {
            return true;
        }

        if (!empty($fresh['etag']) && !empty($stored['etag']) && $fresh['etag'] !== $stored['etag']) {
            return true;
        }

        if (!empty($fresh['modified']) && !empty($stored['modified'])) {
            $a = substr((string) $stored['modified'], 0, 19);
            $b = substr((string) $fresh['modified'], 0, 19);
            if ($a !== $b) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when we have no usable baseline to compare against yet.
     */
    public function isEmpty(array $meta): bool
    {
        return empty($meta['name'])
            && empty($meta['size'])
            && empty($meta['etag'])
            && empty($meta['modified']);
    }

    private function filenameFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }
        $name = basename($path);
        if ($name === '') {
            return null;
        }
        return urldecode($name);
    }
}
