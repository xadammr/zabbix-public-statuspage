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

    protected StatusPageDataFetcher $data;

    public function __construct(
        protected ZabbixClient $zabbix,
        protected StatusPageSummary $summary,
        ?StatusPageHostDiscovery $hostDiscovery = null,
        ?StatusPageMetricFormatter $metrics = null,
        ?StatusPageLatencyFormatter $latency = null,
        ?StatusPageMacroParser $macros = null,
        ?StatusPageDataFetcher $data = null,
    ) {
        $this->macros = $macros ?? new StatusPageMacroParser;
        $this->hostDiscovery = $hostDiscovery ?? new StatusPageHostDiscovery($zabbix);
        $this->metrics = $metrics ?? new StatusPageMetricFormatter($this->macros);
        $this->latency = $latency ?? new StatusPageLatencyFormatter($this->macros);
        $this->data = $data ?? new StatusPageDataFetcher($zabbix, $this->macros);
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
            fn () => $this->data->triggers($hostIds),
            ['hosts' => count($hostIds)],
        ));
        $problems = collect($this->timed(
            'zabbix.problem.get',
            fn () => $this->data->problems($hostIds),
            ['hosts' => count($hostIds)],
        ));
        $triggers = $this->withoutSuppressedTriggers($triggers, $problems);
        $macros = collect($this->timed(
            'zabbix.usermacro.get',
            fn () => $this->data->macros($hostIds),
            ['hosts' => count($hostIds)],
        ));
        $availableItems = collect($this->timed(
            'zabbix.item.get.available',
            fn () => $this->shouldFetchAvailableItems() ? $this->data->availableItems($hostIds) : [],
            ['hosts' => count($hostIds), 'enabled' => $this->shouldFetchAvailableItems()],
        ));
        $publicMetricItems = $availableItems->isNotEmpty()
            ? $availableItems
            : collect($this->timed(
                'zabbix.item.get.public_metrics',
                fn () => $this->data->publicMetricItems($hostIds, $macros),
                ['hosts' => count($hostIds)],
            ));
        $publicMetricItems = $this->metrics->withChanges($publicMetricItems, $macros);
        $availableItems = $this->metrics->mergeChanges($availableItems, $publicMetricItems);
        $apiHealthItems = collect($this->timed(
            'zabbix.item.get.api_health',
            fn () => $this->data->itemsByKey($hostIds, config('zabbix.api_health_item_key')),
            ['hosts' => count($hostIds), 'key' => config('zabbix.api_health_item_key')],
        ));
        $latencyHosts = $hosts
            ->filter(fn (array $host) => $this->sectionShowsLatency($host['statuspage_section']))
            ->filter(fn (array $host) => $this->macros->string($macros->where('hostid', $host['hostid']), '{$PUBLIC_LATENCY_ITEM_KEY}') !== null)
            ->values();
        $latencyItems = collect($this->timed(
            'zabbix.item.get.latency',
            fn () => $this->data->latencyItems($latencyHosts, $macros),
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

    protected function fetchLatencyHistory(Collection $latencyItems, int $minutes): array
    {
        $history = [];
        $itemIds = $latencyItems->pluck('itemid')->values()->all();
        $seriesByItem = $itemIds === []
            ? []
            : $this->timed(
                'zabbix.history.get.latency_items',
                fn () => $this->data->latencySeries($itemIds, $minutes),
                ['items' => count($itemIds), 'limit' => StatusPageLatencyFormatter::HISTORY_LIMIT * count($itemIds)],
            );

        foreach ($latencyItems as $item) {
            $series = $seriesByItem[$item['itemid']] ?? [];
            $latest = $series === [] ? null : $series[array_key_last($series)];

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
