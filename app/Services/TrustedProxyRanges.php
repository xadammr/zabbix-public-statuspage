<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TrustedProxyRanges
{
    public function resolve(array $ranges): array
    {
        return collect($ranges)
            ->flatMap(fn (string $range) => strtolower($range) === 'cloudflare'
                ? $this->cloudflareRanges()
                : [$range])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function cloudflareRanges(): array
    {
        return Cache::remember(
            config('zabbix.cloudflare_ip_ranges_cache_key', 'statuspage.cloudflare_ip_ranges'),
            now()->addSeconds((int) config('zabbix.cloudflare_ip_ranges_cache_ttl', 86400)),
            fn () => $this->fetchCloudflareRanges(),
        );
    }

    protected function fetchCloudflareRanges(): array
    {
        $response = Http::acceptJson()
            ->timeout(5)
            ->get(config('zabbix.cloudflare_ip_ranges_url'));

        if (! $response->successful()) {
            return [];
        }

        return collect([
            ...($response->json('result.ipv4_cidrs') ?? []),
            ...($response->json('result.ipv6_cidrs') ?? []),
        ])
            ->filter()
            ->values()
            ->all();
    }
}
