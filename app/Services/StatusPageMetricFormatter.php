<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StatusPageMetricFormatter
{
    public function __construct(protected StatusPageMacroParser $macros) {}

    public function publicMetrics(Collection $macros, Collection $items): array
    {
        $keys = $this->macros->metricKeys($this->macros->string($macros, '{$PUBLIC_METRICS}') ?? '');
        $labels = $this->macros->metricKeys($this->macros->string($macros, '{$PUBLIC_METRIC_MAP}') ?? '');

        return collect($keys)->map(function (string $key, int $index) use ($items, $labels): ?array {
            $item = $items->firstWhere('key_', $key);

            return $item ? $this->item($item, $labels[$index] ?? null) : null;
        })->filter()->values()->all();
    }

    public function withChanges(Collection $items, Collection $macros): Collection
    {
        $publicItems = $this->publicMetricItems($items, $macros)
            ->filter(fn (array $item) => $this->canShowChange($item))
            ->keyBy('itemid');

        return $items->map(function (array $item) use ($publicItems): array {
            $metric = $publicItems[$item['itemid']] ?? null;

            return $metric ? [...$item, 'change' => $this->change($metric['lastvalue'], $metric['prevvalue'])] : $item;
        });
    }

    public function mergeChanges(Collection $availableItems, Collection $publicMetricItems): Collection
    {
        if ($availableItems->isEmpty()) {
            return $availableItems;
        }

        $changes = $publicMetricItems->filter(fn (array $item) => isset($item['change']))->keyBy('itemid');

        return $availableItems->map(function (array $item) use ($changes): array {
            $changed = $changes[$item['itemid']] ?? null;

            return $changed ? [...$item, 'change' => $changed['change']] : $item;
        });
    }

    public function apiHealth(?array $item): ?array
    {
        if (! $item || ($item['lastvalue'] ?? '') === '') {
            return null;
        }

        return [
            'name' => $item['name'],
            'value' => $item['lastvalue'],
            'display_value' => $this->applyValueMap($item),
            'clock' => $item['lastclock'] ?? null,
            'ok' => $item['lastvalue'] === config('zabbix.api_health_success_value'),
        ];
    }

    public function item(array $item, ?string $label = null): array
    {
        $value = $item['lastvalue'];
        $display = $this->displayValue($this->applyValueMap($item), $item);
        $units = $this->units($item['units'] ?? '');

        return [
            'itemid' => $item['itemid'],
            'name' => $label ?: $item['name'],
            'key' => $item['key_'],
            'lastvalue' => $value,
            'display_value' => $display,
            'formatted_value' => $this->valueWithUnits($display, $units),
            'lastclock' => $item['lastclock'],
            'status' => $item['status'],
            'state' => $item['state'],
            'value_type' => $item['value_type'],
            'units' => $units,
            'change' => $item['change'] ?? null,
        ];
    }

    public function change(string $currentValue, string $previousValue): array
    {
        $delta = (float) $currentValue - (float) $previousValue;

        return [
            'direction' => match (true) {
                abs($delta) < 0.000000001 => 'same',
                $delta > 0 => 'up',
                $delta < 0 => 'down',
                default => 'same',
            },
            'previous_value' => $previousValue,
            'delta' => $delta,
        ];
    }

    public function displayValue(string $value, array $item): string
    {
        if (($item['units'] ?? '') === 'B' && is_numeric($value)) {
            return $this->scaledValue((float) $value, 1024);
        }

        if (in_array(($item['units'] ?? ''), ['B/sec', 'Bps', 'bps'], true) && is_numeric($value)) {
            return $this->scaledValue((float) $value, 1000);
        }

        if (($item['value_type'] ?? null) === '0' && is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        if (in_array((string) ($item['value_type'] ?? ''), ['1', '4'], true)) {
            $trimmed = trim($value);

            return $trimmed !== '' && preg_match('/[a-z]/', $trimmed) === 1 && $trimmed === strtolower($trimmed)
                ? ucfirst($trimmed)
                : $value;
        }

        return $value;
    }

    public function units(string $units): string
    {
        return str_starts_with($units, '!') ? substr($units, 1) : $units;
    }

    public function valueWithUnits(string $value, string $units): string
    {
        if (in_array($units, ['B', 'bps', 'B/sec', 'Bps'], true)
            && preg_match('/^(-?\d+(?:\.\d+)?)([KMGTPE])$/', $value, $matches) === 1) {
            return $matches[1].' '.$matches[2].$units;
        }

        return $units === '' ? $value : $value.' '.$units;
    }

    protected function publicMetricItems(Collection $items, Collection $macros): Collection
    {
        $keysByHost = $macros->where('macro', '{$PUBLIC_METRICS}')->groupBy('hostid')
            ->map(fn (Collection $hostMacros) => $hostMacros
                ->flatMap(fn (array $macro) => $this->macros->metricKeys($macro['value'] ?? ''))
                ->unique()->values()->all());

        return $items->filter(fn (array $item) => in_array(
            $item['key_'] ?? null,
            $keysByHost[$item['hostid']] ?? [],
            true,
        ))->values();
    }

    protected function canShowChange(array $item): bool
    {
        return ($item['lastvalue'] ?? '') !== ''
            && ($item['prevvalue'] ?? '') !== ''
            && is_numeric($item['lastvalue'])
            && is_numeric($item['prevvalue'])
            && in_array((string) ($item['value_type'] ?? ''), ['0', '3'], true);
    }

    protected function applyValueMap(array $item): string
    {
        $value = $item['lastvalue'] ?? '';
        $default = null;

        foreach ($item['valuemap']['mappings'] ?? [] as $mapping) {
            if (($mapping['type'] ?? null) === '5') {
                $default = $mapping['newvalue'] ?? null;
            } elseif (($mapping['type'] ?? null) === '0' && ($mapping['value'] ?? null) === $value) {
                return $mapping['newvalue'] ?? $value;
            }
        }

        return $default ?? $value;
    }

    protected function scaledValue(float $input, int $base): string
    {
        $prefixes = ['', 'K', 'M', 'G', 'T', 'P', 'E'];
        $value = abs($input);
        $prefix = 0;

        while ($value >= $base && $prefix < count($prefixes) - 1) {
            $value /= $base;
            $prefix++;
        }

        $scaled = $input < 0 ? -$value : $value;

        return $prefix === 0
            ? (string) (int) round($scaled)
            : number_format($scaled, 2, '.', '').$prefixes[$prefix];
    }
}
