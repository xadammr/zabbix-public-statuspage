<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class StatusPageBuilder
{
    protected const LATENCY_HISTORY_MINUTES = 60;
    protected const LATENCY_HISTORY_LIMIT = 60;

    protected array $profileEvents = [];
    protected int $profileStartedAt = 0;

    public function __construct(
        protected ZabbixClient $zabbix,
        protected StatusPageSummary $summary,
    ) {}

    public function build(): array
    {
        $this->startProfile();
        $sections = collect(config('zabbix.statuspage_sections'));
        $hosts = $this->timed('statuspage.hosts', fn () => $sections
            ->keys()
            ->flatMap(fn (string $section) => collect($this->timed(
                'zabbix.host.get',
                fn () => $this->fetchStatuspageHosts($section),
                ['section' => $section],
            ))
                ->map(fn (array $host) => [
                    ...$host,
                    'statuspage_section' => $section,
                ]))
            ->unique('hostid')
            ->values());
        $hostIds = $hosts->pluck('hostid')->values()->all();
        $triggers = collect($this->timed(
            'zabbix.trigger.get',
            fn () => $this->fetchTriggers($hostIds),
            ['hosts' => count($hostIds)],
        ));
        $macros = collect($this->timed(
            'zabbix.usermacro.get',
            fn () => $this->fetchMacros($hostIds),
            ['hosts' => count($hostIds)],
        ));
        $availableItems = collect($this->timed(
            'zabbix.item.get.available',
            fn () => $this->shouldFetchAvailableItems() ? $this->fetchAvailableItems($hostIds) : [],
            ['hosts' => count($hostIds), 'enabled' => $this->shouldFetchAvailableItems()],
        ));
        $publicMetricItems = $availableItems->isNotEmpty()
            ? $availableItems
            : collect($this->timed(
                'zabbix.item.get.public_metrics',
                fn () => $this->fetchPublicMetricItems($hostIds, $macros),
                ['hosts' => count($hostIds)],
            ));
        $apiHealthItems = collect($this->timed(
            'zabbix.item.get.api_health',
            fn () => $this->fetchItems($hostIds, config('zabbix.api_health_item_key')),
            ['hosts' => count($hostIds), 'key' => config('zabbix.api_health_item_key')],
        ));
        $latencyHosts = $hosts
            ->filter(fn (array $host) => $this->sectionShowsLatency($host['statuspage_section']))
            ->filter(fn (array $host) => $this->macroStringValue($macros->where('hostid', $host['hostid']), '{$PUBLIC_LATENCY_ITEM_KEY}') !== null)
            ->values();
        $latencyItems = collect($this->timed(
            'zabbix.item.get.latency',
            fn () => $this->fetchLatencyItems($latencyHosts, $macros),
            ['hosts' => $latencyHosts->count()],
        ));
        $latencyHistory = collect($this->timed(
            'zabbix.history.get.latency_total',
            fn () => $this->fetchLatencyHistory($latencyItems, self::LATENCY_HISTORY_MINUTES),
            [
                'items' => $latencyItems->count(),
                'minutes' => self::LATENCY_HISTORY_MINUTES,
                'limit' => self::LATENCY_HISTORY_LIMIT,
            ],
        ));

        $services = $this->timed(
            'statuspage.services',
            fn () => $hosts
                ->map(fn (array $host) => $this->buildService(
                    $host,
                    $triggers,
                    $availableItems,
                    $publicMetricItems,
                    $macros,
                    $latencyItems,
                    $latencyHistory,
                    $apiHealthItems,
                ))
                ->values(),
            ['hosts' => $hosts->count()],
        );
        $serviceSections = $this->timed(
            'statuspage.sections',
            fn () => $sections
                ->map(fn (array $sectionConfig, string $section) => [
                    'key' => $section,
                    'title' => $sectionConfig['title'],
                    'description' => $sectionConfig['description'],
                    'services' => $services
                        ->where('section', $section)
                        ->values()
                        ->all(),
                ])
                ->filter(fn (array $section) => count($section['services']) > 0)
                ->values(),
            ['sections' => $sections->count()],
        );

        $statusPage = [
            'generated_at' => now(),
            'summary' => $this->summary->build($services),
            'services' => $services->all(),
            'sections' => $serviceSections->all(),
        ];

        $this->logProfile($statusPage);

        return $statusPage;
    }

    protected function startProfile(): void
    {
        $this->profileEvents = [];
        $this->profileStartedAt = hrtime(true);
    }

    protected function timed(string $label, callable $callback, array $context = []): mixed
    {
        $startedAt = hrtime(true);

        try {
            $result = $callback();
        } catch (Throwable $exception) {
            $this->profileEvents[] = [
                'label' => $label,
                'ms' => $this->elapsedMilliseconds($startedAt),
                'failed' => true,
                ...$context,
            ];

            throw $exception;
        }

        $event = [
            'label' => $label,
            'ms' => $this->elapsedMilliseconds($startedAt),
            ...$context,
        ];

        if (is_countable($result)) {
            $event['count'] = count($result);
        }

        $this->profileEvents[] = $event;

        return $result;
    }

    protected function logProfile(array $statusPage): void
    {
        if (! config('zabbix.statuspage_profile_log')) {
            return;
        }

        $events = collect($this->profileEvents);

        Log::info('statuspage:build_profile', [
            'total_ms' => $this->elapsedMilliseconds($this->profileStartedAt),
            'services' => count($statusPage['services'] ?? []),
            'sections' => count($statusPage['sections'] ?? []),
            'events' => $events
                ->groupBy('label')
                ->map(fn (Collection $group, string $label) => [
                    'label' => $label,
                    'calls' => $group->count(),
                    'total_ms' => round($group->sum('ms'), 2),
                    'max_ms' => round($group->max('ms'), 2),
                    'count' => $group->sum('count'),
                ])
                ->sortByDesc('total_ms')
                ->values()
                ->all(),
            'slowest' => $events
                ->sortByDesc('ms')
                ->take(10)
                ->map(fn (array $event) => [
                    ...$event,
                    'ms' => round($event['ms'], 2),
                ])
                ->values()
                ->all(),
        ]);
    }

    protected function elapsedMilliseconds(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }

    protected function fetchStatuspageHosts(string $section): array
    {
        return $this->zabbix->request('host.get', [
            'tags' => [
                [
                    'tag' => 'statuspage',
                    'value' => $section,
                    'operator' => 1,
                ],
            ],
            'output' => [
                'hostid',
                'host',
                'name',
                'description',
            ],
            'selectTags' => [
                'tag',
                'value',
            ],
            'sortfield' => 'name',
        ]);
    }

    protected function fetchTriggers(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        return $this->zabbix->request('trigger.get', [
            'hostids' => $hostIds,
            'maintenance' => false,
            'only_true' => true,
            'skipDependent' => true,
            'output' => [
                'triggerid',
                'description',
                'priority',
                'status',
                'value',
            ],
            'selectHosts' => [
                'hostid',
                'host',
                'name',
            ],
            'expandDescription' => true,
        ]);
    }

    protected function fetchItems(array $hostIds, string $key): array
    {
        if ($hostIds === []) {
            return [];
        }

        return $this->zabbix->request('item.get', [
            'hostids' => $hostIds,
            'output' => [
                'itemid',
                'hostid',
                'name',
                'key_',
                'lastvalue',
                'lastclock',
                'status',
                'state',
            ],
            'filter' => [
                'key_' => $key,
            ],
            'selectValueMap' => 'extend',
            'sortfield' => 'name',
        ]);
    }

    protected function fetchAvailableItems(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        return $this->zabbix->request('item.get', [
            'hostids' => $hostIds,
            'output' => [
                'itemid',
                'hostid',
                'name',
                'key_',
                'lastvalue',
                'lastclock',
                'status',
                'state',
                'value_type',
                'units',
            ],
            'sortfield' => [
                'name',
                'key_',
            ],
            'selectValueMap' => 'extend',
        ]);
    }

    protected function fetchPublicMetricItems(array $hostIds, Collection $macros): array
    {
        $metricKeys = $macros
            ->where('macro', '{$PUBLIC_METRICS}')
            ->flatMap(fn (array $macro) => $this->parseMetricKeys($macro['value'] ?? ''))
            ->unique()
            ->values()
            ->all();

        if ($hostIds === [] || $metricKeys === []) {
            return [];
        }

        return $this->zabbix->request('item.get', [
            'hostids' => $hostIds,
            'output' => [
                'itemid',
                'hostid',
                'name',
                'key_',
                'lastvalue',
                'lastclock',
                'status',
                'state',
                'value_type',
                'units',
            ],
            'filter' => [
                'key_' => $metricKeys,
            ],
            'sortfield' => [
                'name',
                'key_',
            ],
            'selectValueMap' => 'extend',
        ]);
    }

    protected function fetchMacros(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        return $this->zabbix->request('usermacro.get', [
            'hostids' => $hostIds,
            'output' => [
                'hostmacroid',
                'hostid',
                'macro',
                'value',
                'type',
            ],
        ]);
    }

    protected function fetchLatencyItems(Collection $hosts, Collection $macros): array
    {
        if ($hosts->isEmpty()) {
            return [];
        }

        $hostIds = $hosts->pluck('hostid')->values()->all();
        $keys = $hosts
            ->map(fn (array $host) => $this->macroStringValue($macros->where('hostid', $host['hostid']), '{$PUBLIC_LATENCY_ITEM_KEY}'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($keys === []) {
            return [];
        }

        $items = $this->zabbix->request('item.get', [
            'hostids' => $hostIds,
            'webitems' => true,
            'output' => [
                'itemid',
                'hostid',
                'name',
                'key_',
                'lastvalue',
                'lastclock',
                'status',
                'state',
            ],
            'filter' => [
                'key_' => $keys,
            ],
            'sortfield' => 'name',
        ]);

        $keysByHost = $hosts
            ->mapWithKeys(fn (array $host) => [
                $host['hostid'] => $this->macroStringValue($macros->where('hostid', $host['hostid']), '{$PUBLIC_LATENCY_ITEM_KEY}'),
            ]);

        return collect($items)
            ->filter(fn (array $item) => ($keysByHost[$item['hostid']] ?? null) === ($item['key_'] ?? null))
            ->values()
            ->all();
    }

    protected function fetchLatencyHistory(Collection $latencyItems, int $minutes): array
    {
        $history = [];

        foreach ($latencyItems as $item) {
            $series = $this->fetchLatencySeriesForItem($item['itemid'], $minutes);
            $latest = $series[array_key_last($series)] ?? null;

            if ($latest !== null) {
                $history[] = [
                    'hostid' => $item['hostid'],
                    'itemid' => $item['itemid'],
                    'lastvalue' => (string) $latest['seconds'],
                    'lastclock' => $latest['clock'],
                    'name' => $item['name'],
                    'series' => $series,
                ];
            }
        }

        return $history;
    }

    protected function fetchLatencySeriesForItem(string $itemId, int $minutes): array
    {
        $values = $this->timed(
            'zabbix.history.get.latency_item',
            fn () => $this->zabbix->request('history.get', [
                'itemids' => [$itemId],
                'history' => 0,
                'time_from' => now()->subMinutes($minutes)->timestamp,
                'sortfield' => 'clock',
                'sortorder' => 'ASC',
                'limit' => self::LATENCY_HISTORY_LIMIT,
            ]),
            ['itemid' => $itemId, 'limit' => self::LATENCY_HISTORY_LIMIT],
        );

        return collect($values)
            ->filter(function (array $value): bool {
                $seconds = (float) $value['value'];

                return $seconds > 0 && $seconds < 30;
            })
            ->map(function (array $value): array {
                $seconds = (float) $value['value'];

                return [
                    'clock' => (int) $value['clock'],
                    'seconds' => $seconds,
                    'milliseconds' => (int) round($seconds * 1000),
                ];
            })
            ->values()
            ->all();
    }

    protected function buildService(
        array $host,
        Collection $triggers,
        Collection $availableItems,
        Collection $publicMetricItems,
        Collection $macros,
        Collection $latencyItems,
        Collection $latencyHistory,
        Collection $apiHealthItems,
    ): array {
        $hostTriggers = $triggers
            ->filter(fn (array $trigger) => collect($trigger['hosts'] ?? [])
                ->contains(fn (array $triggerHost) => $triggerHost['hostid'] === $host['hostid']))
            ->values();

        $latencyItem = null;
        $historyLatencyItem = null;

        if ($this->sectionShowsLatency($host['statuspage_section'])) {
            $latencyItem = $latencyItems->firstWhere('hostid', $host['hostid']);
            $historyLatencyItem = $latencyHistory->firstWhere('hostid', $host['hostid']);
            $latencyItem ??= $historyLatencyItem;
        }

        $apiHealthItem = $apiHealthItems->firstWhere('hostid', $host['hostid']);
        $hostMacros = $macros->where('hostid', $host['hostid']);
        $displayName = $this->macroStringValue($hostMacros, '{$PUBLIC_DN}')
            ?: ($host['name'] && $host['name'] !== $host['host']
            ? $host['name']
            : $host['host']);
        $publicUrl = $this->publicUrlValue($hostMacros);
        $hasProblem = $hostTriggers->contains(fn (array $trigger) => $trigger['value'] === '1');
        $thresholds = $this->formatLatencyThresholds($hostMacros);
        $hostAvailableItems = $availableItems->where('hostid', $host['hostid']);
        $hostPublicMetricItems = $publicMetricItems->where('hostid', $host['hostid']);
        $severity = $this->formatServiceSeverity($hostTriggers);

        return [
            'hostid' => $host['hostid'],
            'host' => $host['host'],
            'section' => $host['statuspage_section'],
            'name' => $displayName,
            'description' => $host['description'] ?? '',
            'public_url' => $publicUrl,
            'status' => $hasProblem ? 'problem' : 'ok',
            'severity' => $severity,
            'triggers' => $hostTriggers
                ->map(fn (array $trigger) => $this->formatTrigger($trigger))
                ->all(),
            'latency' => $this->formatLatency($latencyItem, $historyLatencyItem, $thresholds),
            'api_health' => $this->formatApiHealth($apiHealthItem),
            'public_metrics' => $this->formatPublicMetrics($hostMacros, $hostPublicMetricItems),
            'available_items' => $hostAvailableItems
                ->map(fn (array $item) => $this->formatAvailableItem($item))
                ->values()
                ->all(),
        ];
    }

    protected function shouldFetchAvailableItems(): bool
    {
        return config('app.env') !== 'production';
    }

    protected function publicUrlValue(Collection $macros): ?string
    {
        $url = $this->macroStringValue($macros, '{$PUBLIC_URL_OVERRIDE}')
            ?: $this->macroStringValue($macros, '{$PUBLIC_URL}');

        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    protected function sectionShowsLatency(string $section): bool
    {
        return in_array($section, config('zabbix.latency_sections', ['public']), true);
    }

    protected function formatLatency(?array $item, ?array $historyItem = null, array $thresholds = []): ?array
    {
        if (! $item || ($item['lastvalue'] ?? '') === '') {
            return null;
        }

        $seconds = (float) $item['lastvalue'];
        $series = $item['series'] ?? $historyItem['series'] ?? [];

        return [
            'seconds' => $seconds,
            'milliseconds' => (int) round($seconds * 1000),
            'clock' => $item['lastclock'] ?? null,
            'source' => $item['name'] ?? 'Latency',
            'series' => $this->formatLatencyChart($series, $thresholds),
        ];
    }

    protected function formatTrigger(array $trigger): array
    {
        $priority = (int) $trigger['priority'];
        $severity = $this->summary->forPriority($priority);

        return [
            ...$trigger,
            'priority_label' => $severity['label'],
            'priority_class' => $severity['class'],
        ];
    }

    protected function formatServiceSeverity(Collection $hostTriggers): array
    {
        $activeTriggers = $hostTriggers->where('value', '1');

        if ($activeTriggers->isEmpty()) {
            return $this->summary->forClass('ok');
        }

        return $this->summary->forPriority((int) $activeTriggers->max('priority'));
    }

    protected function formatLatencyChart(array $series, array $thresholds): ?array
    {
        if (count($series) < 2) {
            return null;
        }

        $warningThreshold = $thresholds['warning'];
        $dangerThreshold = $thresholds['danger'];
        $values = collect($series);
        $minClock = $values->min('clock');
        $maxClock = $values->max('clock');
        $maxMilliseconds = max($dangerThreshold, $values->max('milliseconds'));
        $width = 240;
        $height = 72;
        $padding = 6;
        $innerWidth = $width - ($padding * 2);
        $innerHeight = $height - ($padding * 2);

        $points = $values
            ->map(function (array $point) use ($minClock, $maxClock, $maxMilliseconds, $padding, $innerWidth, $innerHeight): string {
                $clockRange = max(1, $maxClock - $minClock);
                $x = $padding + ((($point['clock'] - $minClock) / $clockRange) * $innerWidth);
                $y = $padding + ($innerHeight - (($point['milliseconds'] / $maxMilliseconds) * $innerHeight));

                return round($x, 2).','.round($y, 2);
            })
            ->implode(' ');

        return [
            'points' => $points,
            'width' => $width,
            'height' => $height,
            'max_ms' => (int) round($maxMilliseconds),
            'min_ms' => (int) round($values->min('milliseconds')),
            'samples' => $values->count(),
            'bands' => $this->formatLatencyBands($maxMilliseconds, $padding, $innerHeight, $warningThreshold, $dangerThreshold),
            'thresholds' => collect([$warningThreshold, $dangerThreshold])
                ->map(function (int $threshold) use ($maxMilliseconds, $padding, $innerHeight): array {
                    $y = $padding + ($innerHeight - (($threshold / $maxMilliseconds) * $innerHeight));

                    return [
                        'value' => $threshold,
                        'y' => round(max($padding, min($padding + $innerHeight, $y)), 2),
                    ];
                })
                ->all(),
        ];
    }

    protected function formatLatencyBands(float|int $maxMilliseconds, int $padding, int $innerHeight, int $warningThreshold, int $dangerThreshold): array
    {
        $yFor = function (int $milliseconds) use ($maxMilliseconds, $padding, $innerHeight): float {
            $y = $padding + ($innerHeight - (($milliseconds / $maxMilliseconds) * $innerHeight));

            return round(max($padding, min($padding + $innerHeight, $y)), 2);
        };

        $top = $padding;
        $bottom = $padding + $innerHeight;
        $warningTop = $yFor($dangerThreshold);
        $warningBottom = $yFor($warningThreshold);

        return [
            [
                'class' => 'danger',
                'y' => $top,
                'height' => max(0, round($warningTop - $top, 2)),
            ],
            [
                'class' => 'warning',
                'y' => $warningTop,
                'height' => max(0, round($warningBottom - $warningTop, 2)),
            ],
            [
                'class' => 'ok',
                'y' => $warningBottom,
                'height' => max(0, round($bottom - $warningBottom, 2)),
            ],
        ];
    }

    protected function formatLatencyThresholds(Collection $macros): array
    {
        $warning = $this->macroIntegerValue($macros, '{$PUBLIC_HTTP_RESPONSE_WARN}', 200);
        $danger = $this->macroIntegerValue($macros, '{$PUBLIC_HTTP_RESPONSE_HIGH}', 1000);

        if ($danger <= $warning) {
            $danger = $warning + 1;
        }

        return [
            'warning' => $warning,
            'danger' => $danger,
        ];
    }

    protected function macroIntegerValue(Collection $macros, string $macro, int $default): int
    {
        $value = $macros->firstWhere('macro', $macro)['value'] ?? null;

        if ($value === null || ! is_numeric($value)) {
            return $default;
        }

        return max(1, (int) round((float) $value));
    }

    protected function macroStringValue(Collection $macros, string $macro): ?string
    {
        $value = $macros->firstWhere('macro', $macro)['value'] ?? null;

        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    protected function formatPublicMetrics(Collection $macros, Collection $items): array
    {
        $metricKeys = $this->parseMetricKeys($this->macroStringValue($macros, '{$PUBLIC_METRICS}') ?? '');
        $metricLabels = $this->parseMetricKeys($this->macroStringValue($macros, '{$PUBLIC_METRIC_MAP}') ?? '');

        return collect($metricKeys)
            ->map(function (string $key, int $index) use ($items, $metricLabels): ?array {
                $item = $items->firstWhere('key_', $key);

                if (! $item) {
                    return null;
                }

                return $this->formatAvailableItem($item, $metricLabels[$index] ?? null);
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function parseMetricKeys(string $value): array
    {
        $keys = [];
        $current = '';
        $bracketDepth = 0;
        $quote = null;
        $characters = str_split($value);

        foreach ($characters as $character) {
            if ($quote !== null) {
                $current .= $character;

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $current .= $character;

                continue;
            }

            if ($character === '[') {
                $bracketDepth++;
                $current .= $character;

                continue;
            }

            if ($character === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                $current .= $character;

                continue;
            }

            if (($character === ',' || $character === ';') && $bracketDepth === 0) {
                $keys[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $keys[] = trim($current);

        return collect($keys)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function formatApiHealth(?array $item): ?array
    {
        if (! $item || ($item['lastvalue'] ?? '') === '') {
            return null;
        }

        $successValue = config('zabbix.api_health_success_value');

        return [
            'name' => $item['name'],
            'value' => $item['lastvalue'],
            'display_value' => $this->applyValueMap($item),
            'clock' => $item['lastclock'] ?? null,
            'ok' => $item['lastvalue'] === $successValue,
        ];
    }

    protected function formatAvailableItem(array $item, ?string $label = null): array
    {
        $value = $item['lastvalue'];
        $displayValue = $this->applyValueMap($item);

        return [
            'itemid' => $item['itemid'],
            'name' => $label ?: $item['name'],
            'key' => $item['key_'],
            'lastvalue' => $value,
            'display_value' => $this->formatDisplayValue($displayValue, $item),
            'lastclock' => $item['lastclock'],
            'status' => $item['status'],
            'state' => $item['state'],
            'value_type' => $item['value_type'],
            'units' => $item['units'] ?? '',
        ];
    }

    protected function applyValueMap(array $item): string
    {
        $value = $item['lastvalue'] ?? '';
        $mappings = $item['valuemap']['mappings'] ?? [];
        $default = null;

        foreach ($mappings as $mapping) {
            if (($mapping['type'] ?? null) === '5') {
                $default = $mapping['newvalue'] ?? null;

                continue;
            }

            if (($mapping['type'] ?? null) === '0' && ($mapping['value'] ?? null) === $value) {
                return $mapping['newvalue'] ?? $value;
            }
        }

        return $default ?? $value;
    }

    protected function formatDisplayValue(string $value, array $item): string
    {
        if (($item['units'] ?? '') === 'B' && is_numeric($value)) {
            return $this->formatByteValue((float) $value);
        }

        if (($item['value_type'] ?? null) === '0' && is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return $value;
    }

    protected function formatByteValue(float $bytes): string
    {
        $prefixes = ['', 'K', 'M', 'G', 'T', 'P', 'E'];
        $value = abs($bytes);
        $prefix = 0;

        while ($value >= 1024 && $prefix < count($prefixes) - 1) {
            $value /= 1024;
            $prefix++;
        }

        $scaled = $bytes < 0 ? -$value : $value;

        if ($prefix === 0) {
            return (string) (int) round($scaled);
        }

        return number_format($scaled, 2, '.', '').$prefixes[$prefix];
    }
}
