<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class StatusPageBuilder
{
    protected array $profileEvents = [];

    protected int $profileStartedAt = 0;

    protected StatusPageHostDiscovery $hostDiscovery;

    protected StatusPageMetricFormatter $metrics;

    protected StatusPageLatencyFormatter $latency;

    protected StatusPageMacroParser $macros;

    public function __construct(
        protected ZabbixClient $zabbix,
        protected StatusPageSummary $summary,
        ?StatusPageHostDiscovery $hostDiscovery = null,
        ?StatusPageMetricFormatter $metrics = null,
        ?StatusPageLatencyFormatter $latency = null,
        ?StatusPageMacroParser $macros = null,
    ) {
        $this->macros = $macros ?? new StatusPageMacroParser;
        $this->hostDiscovery = $hostDiscovery ?? new StatusPageHostDiscovery($zabbix);
        $this->metrics = $metrics ?? new StatusPageMetricFormatter($this->macros);
        $this->latency = $latency ?? new StatusPageLatencyFormatter($this->macros);
    }

    public function build(): array
    {
        $this->startProfile();
        $sections = collect(config('zabbix.statuspage_sections'));
        $hosts = $this->timed(
            'statuspage.hosts',
            fn () => $this->hostDiscovery->forSections(
                collect($this->timed(
                    'zabbix.host.get',
                    fn () => $this->hostDiscovery->fetch(),
                    ['sections' => $sections->count()],
                )),
                $sections->keys(),
            ),
        );
        $hostIds = $hosts->pluck('hostid')->values()->all();
        $triggers = collect($this->timed(
            'zabbix.trigger.get',
            fn () => $this->fetchTriggers($hostIds),
            ['hosts' => count($hostIds)],
        ));
        $problems = collect($this->timed(
            'zabbix.problem.get',
            fn () => $this->fetchProblems($hostIds),
            ['hosts' => count($hostIds)],
        ));
        $triggers = $this->withoutSuppressedTriggers($triggers, $problems);
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
        $publicMetricItems = $this->metrics->withChanges($publicMetricItems, $macros);
        $availableItems = $this->metrics->mergeChanges($availableItems, $publicMetricItems);
        $apiHealthItems = collect($this->timed(
            'zabbix.item.get.api_health',
            fn () => $this->fetchItems($hostIds, config('zabbix.api_health_item_key')),
            ['hosts' => count($hostIds), 'key' => config('zabbix.api_health_item_key')],
        ));
        $latencyHosts = $hosts
            ->filter(fn (array $host) => $this->sectionShowsLatency($host['statuspage_section']))
            ->filter(fn (array $host) => $this->macros->string($macros->where('hostid', $host['hostid']), '{$PUBLIC_LATENCY_ITEM_KEY}') !== null)
            ->values();
        $latencyItems = collect($this->timed(
            'zabbix.item.get.latency',
            fn () => $this->fetchLatencyItems($latencyHosts, $macros),
            ['hosts' => $latencyHosts->count()],
        ));
        $latencyHistory = collect($this->timed(
            'zabbix.history.get.latency_total',
            fn () => $this->fetchLatencyHistory($latencyItems, StatusPageLatencyFormatter::HISTORY_MINUTES),
            [
                'items' => $latencyItems->count(),
                'minutes' => StatusPageLatencyFormatter::HISTORY_MINUTES,
                'limit' => StatusPageLatencyFormatter::HISTORY_LIMIT,
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
                ->map(fn (string $title, string $section) => [
                    'key' => $section,
                    'title' => $title,
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

    protected function fetchTriggers(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        return $this->zabbix->request('trigger.get', [
            'hostids' => $hostIds,
            'maintenance' => false,
            'monitored' => true,
            'only_true' => true,
            'skipDependent' => true,
            'output' => [
                'triggerid',
                'description',
                'lastchange',
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

    protected function fetchProblems(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        return $this->zabbix->request('problem.get', [
            'hostids' => $hostIds,
            'recent' => false,
            'output' => [
                'objectid',
                'suppressed',
            ],
        ]);
    }

    protected function withoutSuppressedTriggers(Collection $triggers, Collection $problems): Collection
    {
        $problemsByTrigger = $problems->groupBy('objectid');

        return $triggers
            ->reject(function (array $trigger) use ($problemsByTrigger): bool {
                $triggerProblems = $problemsByTrigger->get($trigger['triggerid'], collect());

                return $triggerProblems->isNotEmpty()
                    && $triggerProblems->every(fn (array $problem) => (string) ($problem['suppressed'] ?? '0') === '1');
            })
            ->values();
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
                'prevvalue',
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
                'prevvalue',
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
            ->flatMap(fn (array $macro) => $this->macros->metricKeys($macro['value'] ?? ''))
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
                'prevvalue',
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
            ->map(fn (array $host) => $this->macros->string($macros->where('hostid', $host['hostid']), '{$PUBLIC_LATENCY_ITEM_KEY}'))
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
                $host['hostid'] => $this->macros->string($macros->where('hostid', $host['hostid']), '{$PUBLIC_LATENCY_ITEM_KEY}'),
            ]);

        return collect($items)
            ->filter(fn (array $item) => ($keysByHost[$item['hostid']] ?? null) === ($item['key_'] ?? null))
            ->values()
            ->all();
    }

    protected function fetchLatencyHistory(Collection $latencyItems, int $minutes): array
    {
        $history = [];
        $seriesByItem = $this->fetchLatencySeriesForItems(
            $latencyItems->pluck('itemid')->values()->all(),
            $minutes,
        );

        foreach ($latencyItems as $item) {
            $series = $seriesByItem[$item['itemid']] ?? [];
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

    protected function fetchLatencySeriesForItems(array $itemIds, int $minutes): array
    {
        if ($itemIds === []) {
            return [];
        }

        $values = $this->timed(
            'zabbix.history.get.latency_items',
            fn () => $this->zabbix->request('history.get', [
                'itemids' => $itemIds,
                'history' => 0,
                'time_from' => now()->subMinutes($minutes)->timestamp,
                'sortfield' => 'clock',
                'sortorder' => 'ASC',
                'limit' => StatusPageLatencyFormatter::HISTORY_LIMIT * count($itemIds),
            ]),
            ['items' => count($itemIds), 'limit' => StatusPageLatencyFormatter::HISTORY_LIMIT * count($itemIds)],
        );

        return collect($values)
            ->filter(function (array $value): bool {
                $seconds = (float) $value['value'];

                return $seconds > 0 && $seconds < 30;
            })
            ->map(function (array $value): array {
                $seconds = (float) $value['value'];

                return [
                    'itemid' => $value['itemid'],
                    'clock' => (int) $value['clock'],
                    'seconds' => $seconds,
                    'milliseconds' => (int) round($seconds * 1000),
                ];
            })
            ->groupBy('itemid')
            ->map(fn (Collection $series) => $series
                ->map(fn (array $point) => [
                    'clock' => $point['clock'],
                    'seconds' => $point['seconds'],
                    'milliseconds' => $point['milliseconds'],
                ])
                ->values()
                ->all())
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
        $displayName = $this->macros->string($hostMacros, '{$PUBLIC_DN}')
            ?: ($host['name'] && $host['name'] !== $host['host']
            ? $host['name']
            : $host['host']);
        $publicUrl = $this->macros->publicUrl($hostMacros);
        $hasProblem = $hostTriggers->contains(fn (array $trigger) => $trigger['value'] === '1');
        $thresholds = $this->latency->thresholds($hostMacros);
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
            'latency' => $this->latency->format($latencyItem, $historyLatencyItem, $thresholds),
            'api_health' => $this->metrics->apiHealth($apiHealthItem),
            'public_metrics' => $this->metrics->publicMetrics($hostMacros, $hostPublicMetricItems),
            'available_items' => $hostAvailableItems
                ->map(fn (array $item) => $this->metrics->item($item))
                ->values()
                ->all(),
        ];
    }

    protected function shouldFetchAvailableItems(): bool
    {
        return (bool) config('zabbix.statuspage_fetch_available_items');
    }

    protected function sectionShowsLatency(string $section): bool
    {
        return in_array($section, config('zabbix.latency_sections', ['public']), true);
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
}
