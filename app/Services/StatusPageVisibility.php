<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\IpUtils;

class StatusPageVisibility
{
    public function __construct(
        protected TrustedProxyRanges $trustedProxyRanges,
    ) {}

    public function filter(array $statusPage, string|array|null $clientIps): array
    {
        if ($this->canSeePrivateSections($clientIps)) {
            return $statusPage;
        }

        $privateSections = $this->privateSections();

        if ($privateSections === []) {
            return $statusPage;
        }

        $statusPage['sections'] = collect($statusPage['sections'] ?? [])
            ->reject(fn (array $section) => in_array($section['key'] ?? '', $privateSections, true))
            ->values()
            ->all();

        $statusPage['services'] = collect($statusPage['services'] ?? [])
            ->reject(fn (array $service) => in_array($service['section'] ?? '', $privateSections, true))
            ->values()
            ->all();

        $statusPage['summary'] = $this->summaryForServices($statusPage['services']);

        return $statusPage;
    }

    public function debug(
        array $originalStatusPage,
        array $visibleStatusPage,
        ?string $requestIp,
        ?string $realIp = null,
        ?string $cloudflareIp = null,
        ?string $forwardedFor = null,
    ): array {
        $originalSections = collect($originalStatusPage['sections'] ?? [])
            ->pluck('key')
            ->filter()
            ->values();
        $visibleSections = collect($visibleStatusPage['sections'] ?? [])
            ->pluck('key')
            ->filter()
            ->values();

        return [
            'request_ip' => $requestIp ?: 'unknown',
            'real_ip' => $realIp ?: $cloudflareIp ?: $this->firstForwardedIp($forwardedFor) ?: 'unknown',
            'shown_sections' => $visibleSections->all(),
            'hidden_sections' => $originalSections
                ->diff($visibleSections)
                ->values()
                ->all(),
        ];
    }

    protected function firstForwardedIp(?string $forwardedFor): ?string
    {
        if (! $forwardedFor) {
            return null;
        }

        return trim(explode(',', $forwardedFor)[0]) ?: null;
    }

    public function candidateIps(
        ?string $requestIp,
        ?string $realIp = null,
        ?string $cloudflareIp = null,
        ?string $forwardedFor = null,
        ?string $proxyIp = null,
    ): array {
        $ips = [$requestIp];

        if ($this->trustsForwardedHeaders($proxyIp)) {
            $ips[] = $realIp;
            $ips[] = $cloudflareIp;
            $ips[] = $this->firstForwardedIp($forwardedFor);
        }

        return collect($ips)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function trustsForwardedHeaders(?string $proxyIp): bool
    {
        $trustedProxies = $this->trustedProxies();

        if (! $proxyIp || $trustedProxies === []) {
            return false;
        }

        if (
            in_array('*', $trustedProxies, true)
            || in_array('**', $trustedProxies, true)
            || in_array('REMOTE_ADDR', $trustedProxies, true)
        ) {
            return true;
        }

        return IpUtils::checkIp($proxyIp, $trustedProxies);
    }

    protected function canSeePrivateSections(string|array|null $clientIps): bool
    {
        $allowedIps = $this->privateAllowedIps();
        $clientIps = collect(is_array($clientIps) ? $clientIps : [$clientIps])
            ->filter()
            ->values()
            ->all();

        if ($clientIps === [] || $allowedIps === []) {
            return false;
        }

        return collect($clientIps)
            ->contains(fn (string $clientIp) => IpUtils::checkIp($clientIp, $allowedIps));
    }

    protected function privateSections(): array
    {
        return collect(config('zabbix.statuspage_private_sections', []))
            ->filter()
            ->values()
            ->all();
    }

    protected function privateAllowedIps(): array
    {
        return collect(config('zabbix.statuspage_private_ips', []))
            ->filter()
            ->values()
            ->all();
    }

    protected function trustedProxies(): array
    {
        $configuredProxies = collect(config('zabbix.trusted_proxies', []))
            ->filter()
            ->values()
            ->all();

        return $this->trustedProxyRanges->resolve($configuredProxies);
    }

    protected function summaryForServices(array $services): array
    {
        $services = collect($services);
        $severityOrder = ['disaster', 'high', 'average', 'warning', 'information', 'not-classified', 'ok'];
        $highest = collect($severityOrder)
            ->first(fn (string $class) => $services->contains(fn (array $service) => $service['severity']['class'] === $class)) ?? 'ok';

        return [
            'total' => $services->count(),
            'ok' => $services->where('severity.class', 'ok')->count(),
            'problem' => $services->where('severity.class', '!=', 'ok')->count(),
            'highest' => $this->severityForClass($highest),
            'severity_counts' => collect($severityOrder)
                ->map(fn (string $class) => [
                    ...$this->severityForClass($class),
                    'count' => $services->where('severity.class', $class)->count(),
                ])
                ->filter(fn (array $severity) => $severity['count'] > 0)
                ->values()
                ->all(),
        ];
    }

    protected function severityForClass(string $class): array
    {
        return [
            'label' => match ($class) {
                'ok' => 'OK',
                'not-classified' => 'Not classified',
                'information' => 'Information',
                'warning' => 'Warning',
                'average' => 'Average',
                'high' => 'High',
                'disaster' => 'Disaster',
                default => 'Unknown',
            },
            'class' => $class,
        ];
    }
}
