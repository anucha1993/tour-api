# Headcode Adapters

Place custom PHP adapter files here.

## Naming Convention

- Filename: `{name}.php` (alphanumeric, dash, underscore only)
- Class name: `Headcode{StudlyCase(name)}Adapter`

## Example

File: `storage/headcode/lookplanets.php`

```php
<?php

class HeadcodeLookplanetsAdapter extends \App\Services\WholesalerAdapters\HeadcodeBaseAdapter
{
    public function fetchTours(?string $cursor = null): \App\Services\WholesalerAdapters\Contracts\DTOs\SyncResult
    {
        $data = $this->httpGet('https://api.example.com/tours');
        return $this->buildSyncResult($data['tours'] ?? []);
    }

    public function fetchTourDetail(string $code): ?array
    {
        return null;
    }
}
```

## Available Helpers

- `$this->httpGet(string $url, array $params = []): array`
- `$this->httpPost(string $url, array $data = []): array`
- `$this->lookupCountryId(string $iso2): ?int`
- `$this->lookupTransportId(string $name): ?int`
- `$this->buildSyncResult(array $tours, ?string $nextCursor = null): SyncResult`
- `$this->config` — WholesalerApiConfig model
- `$this->wholesalerId` — int
