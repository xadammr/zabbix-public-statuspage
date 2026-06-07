<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StatusPageBuilder
{
    public function __construct(
        protected ZabbixClient $zabbix,
    ) {}

    public function build(): array
    {
        $sections = collect(config('zabbix.statuspage_sections'));
        $hosts = $sections
            ->keys()
            ->flatMap(fn (string $section) => collect($this->fetchStatuspageHosts($section))
                ->map(fn (array $host) => [
                    ...$host,
                    'statuspage_section' => $section,
                ]))
            ->unique('hostid')
            ->values();
        $hostIds = $hosts->pluck('hostid')->values()->all();
        $triggers = collect($this->fetchTriggers($hostIds));
        $macros = collect($this->fetchMacros($hostIds));
        $availableItems = collect($this->shouldFetchAvailableItems() ? $this->fetchAvailableItems($hostIds) : []);
        $publicMetricItems = $availableItems->isNotEmpty()
            ? $availableItems
            : collect($this->fetchPublicMetricItems($hostIds, $macros));
        $latencyItems = collect($this->fetchItems($hostIds, config('zabbix.latency_item_key')));
        $apiHealthItems = collect($this->fetchItems($hostIds, config('zabbix.api_health_item_key')));
        $latencyHistory = collect($this->fetchLatencyHistory($hostIds, 60));

        $services = $hosts
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
            ->values();
        $serviceSections = $sections
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
            ->values();

        return [
            'generated_at' => now(),
            'summary' => $this->buildSummary($services),
            'services' => $services->all(),
            'sections' => $serviceSections->all(),
        ];
    }

    protected function buildSummary(Collection $services): array
    {
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

    protected function fetchLatencyHistory(array $hostIds, int $minutes): array
    {
        $history = [];
        $timeFrom = now()->subMinutes($minutes)->timestamp;

        foreach ($hostIds as $hostId) {
            $values = $this->zabbix->request('history.get', [
                'hostids' => [$hostId],
                'history' => 0,
                'time_from' => $timeFrom,
                'sortfield' => 'clock',
                'sortorder' => 'DESC',
                'limit' => 200,
            ]);

            $items = collect($values)
                ->filter(function (array $value): bool {
                    $seconds = (float) $value['value'];

                    return $seconds > 0 && $seconds < 30;
                })
                ->groupBy('itemid')
                ->sortByDesc(fn (Collection $points) => $points->count());

            $points = $items->first();

            if (! $points) {
                continue;
            }

            $latest = $points->sortByDesc('clock')->first();
            $series = $points
                ->sortBy('clock')
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

            if ($latest) {
                $history[] = [
                    'hostid' => $hostId,
                    'itemid' => $latest['itemid'],
                    'lastvalue' => $latest['value'],
                    'lastclock' => $latest['clock'],
                    'name' => 'Web response time',
                    'series' => $series,
                ];
            }
        }

        return $history;
    }

    protected function fetchLatencySeriesForItem(string $itemId, int $minutes): array
    {
        $values = $this->zabbix->request('history.get', [
            'itemids' => [$itemId],
            'history' => 0,
            'time_from' => now()->subMinutes($minutes)->timestamp,
            'sortfield' => 'clock',
            'sortorder' => 'ASC',
            'limit' => 200,
        ]);

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
        $severity = $this->severityForPriority($priority);

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
            return $this->severityForClass('ok');
        }

        return $this->severityForPriority((int) $activeTriggers->max('priority'));
    }

    protected function severityForPriority(int $priority): array
    {
        return match ($priority) {
            0 => $this->severityForClass('not-classified'),
            1 => $this->severityForClass('information'),
            2 => $this->severityForClass('warning'),
            3 => $this->severityForClass('average'),
            4 => $this->severityForClass('high'),
            5 => $this->severityForClass('disaster'),
            default => [
                'label' => 'Unknown',
                'class' => 'unknown',
            ],
        };
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
