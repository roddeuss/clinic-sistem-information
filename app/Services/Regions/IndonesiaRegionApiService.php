<?php

namespace App\Services\Regions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IndonesiaRegionApiService
{
    public function provinces(): array
    {
        return $this->request('provinces.json');
    }

    public function cities(string $provinceCode): array
    {
        return $this->request('regencies/' . $provinceCode . '.json');
    }

    public function districts(string $cityCode): array
    {
        return $this->request('districts/' . $cityCode . '.json');
    }

    public function villages(string $districtCode): array
    {
        return $this->request('villages/' . $districtCode . '.json');
    }

    private function request(string $endpoint): array
    {
        $cacheKey = 'indonesia-regions:' . md5($endpoint);
        $ttl = now()->addMinutes((int) config('services.indonesia_regions.cache_minutes', 1440));

        return Cache::remember($cacheKey, $ttl, function () use ($endpoint): array {
            $response = Http::baseUrl((string) config('services.indonesia_regions.base_url'))
                ->acceptJson()
                ->timeout((int) config('services.indonesia_regions.timeout', 10))
                ->get($endpoint);

            if (! $response->successful()) {
                throw new RuntimeException('Gagal memuat referensi wilayah dari provider eksternal.');
            }

            return collect($response->json())
                ->map(function (array $row): array {
                    return [
                        'code' => (string) ($row['id'] ?? $row['code'] ?? ''),
                        'name' => (string) ($row['name'] ?? ''),
                    ];
                })
                ->filter(fn (array $row): bool => $row['code'] !== '' && $row['name'] !== '')
                ->values()
                ->all();
        });
    }
}
