<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StatusPageDataFetcher
{
    private const ITEM_OUTPUT_IDENTITY = [
        'itemid',
        'hostid',
        'name',
        'key_',
        'lastvalue',
    ];

    private const ITEM_OUTPUT_STATE = [
        'lastclock',
        'status',
        'state',
    ];

    private const ITEM_OUTPUT_BASE = [
        ...self::ITEM_OUTPUT_IDENTITY,
        ...self::ITEM_OUTPUT_STATE,
    ];

    private const ITEM_OUTPUT_WITH_PREVIOUS_VALUE = [
        ...self::ITEM_OUTPUT_IDENTITY,
        'prevvalue',
        ...self::ITEM_OUTPUT_STATE,
    ];

    private const ITEM_OUTPUT_WITH_METRIC_FIELDS = [
        ...self::ITEM_OUTPUT_WITH_PREVIOUS_VALUE,
        'value_type',
        'units',
    ];

    public function __construct(
        protected ZabbixClient $zabbix,
        protected StatusPageMacroParser $macros,
    ) {}

    public function triggers(array $hostIds): array
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
            'output' => ['triggerid', 'description', 'lastchange', 'priority', 'status', 'value'],
            'selectHosts' => ['hostid', 'host', 'name'],
            'expandDescription' => true,
        ]);
    }

    public function problems(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        return $this->zabbix->request('problem.get', [
            'hostids' => $hostIds,
            'recent' => false,
            'output' => ['objectid', 'suppressed'],
        ]);
    }

    public function itemsByKey(array $hostIds, string $key): array
    {
        if ($hostIds === []) {
            return [];
        }

        return $this->zabbix->request('item.get', [
            'hostids' => $hostIds,
            'output' => self::ITEM_OUTPUT_WITH_PREVIOUS_VALUE,
            'filter' => ['key_' => $key],
            'selectValueMap' => 'extend',
            'sortfield' => 'name',
        ]);
    }

    public function availableItems(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        return $this->zabbix->request('item.get', [
            'hostids' => $hostIds,
            'output' => self::ITEM_OUTPUT_WITH_METRIC_FIELDS,
            'sortfield' => ['name', 'key_'],
            'selectValueMap' => 'extend',
        ]);
    }

    public function publicMetricItems(array $hostIds, Collection $macros): array
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
            'output' => self::ITEM_OUTPUT_WITH_METRIC_FIELDS,
            'filter' => ['key_' => $metricKeys],
            'sortfield' => ['name', 'key_'],
            'selectValueMap' => 'extend',
        ]);
    }

    public function macros(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        return $this->zabbix->request('usermacro.get', [
            'hostids' => $hostIds,
            'output' => ['hostmacroid', 'hostid', 'macro', 'value', 'type'],
        ]);
    }

    public function latencyItems(Collection $hosts, Collection $macros): array
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
            'output' => self::ITEM_OUTPUT_BASE,
            'filter' => ['key_' => $keys],
            'sortfield' => 'name',
        ]);
        $keysByHost = $hosts->mapWithKeys(fn (array $host) => [
            $host['hostid'] => $this->macros->string($macros->where('hostid', $host['hostid']), '{$PUBLIC_LATENCY_ITEM_KEY}'),
        ]);

        return collect($items)
            ->filter(fn (array $item) => ($keysByHost[$item['hostid']] ?? null) === ($item['key_'] ?? null))
            ->values()
            ->all();
    }

    public function latencySeries(array $itemIds, int $minutes): array
    {
        if ($itemIds === []) {
            return [];
        }

        $values = $this->zabbix->request('history.get', [
            'itemids' => $itemIds,
            'history' => 0,
            'time_from' => now()->subMinutes($minutes)->timestamp,
            'sortfield' => 'clock',
            'sortorder' => 'ASC',
            'limit' => StatusPageLatencyFormatter::HISTORY_LIMIT * count($itemIds),
        ]);

        return collect($values)
            ->filter(fn (array $value) => (float) $value['value'] > 0 && (float) $value['value'] < 30)
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
}
