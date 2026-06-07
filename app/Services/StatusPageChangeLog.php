<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StatusPageChangeLog
{
    public function summarize(?array $before, array $after): array
    {
        if (! $before) {
            return ['Initial snapshot cached with '.$this->serviceCount($after).' services.'];
        }

        $changes = [
            ...$this->summarizeServices($before, $after),
            ...$this->summarizeSummary($before, $after),
        ];

        return $changes === [] ? ['No visible status page changes.'] : $changes;
    }

    protected function summarizeServices(array $before, array $after): array
    {
        $changes = [];
        $beforeServices = $this->servicesById($before);
        $afterServices = $this->servicesById($after);

        foreach ($afterServices as $id => $service) {
            if (! isset($beforeServices[$id])) {
                $changes[] = 'Added service '.$service['name'].' with status '.$service['severity']['label'].'.';

                continue;
            }

            $previous = $beforeServices[$id];

            if (($previous['name'] ?? '') !== ($service['name'] ?? '')) {
                $changes[] = 'Renamed service '.($previous['name'] ?? $id).' to '.$service['name'].'.';
            }

            if (($previous['severity']['class'] ?? null) !== ($service['severity']['class'] ?? null)) {
                $changes[] = $service['name'].' status changed from '.$previous['severity']['label'].' to '.$service['severity']['label'].'.';
            }

            $triggerChange = $this->triggerChange($previous, $service);

            if ($triggerChange) {
                $changes[] = $service['name'].' '.$triggerChange;
            }

            $latencyChange = $this->latencyChange($previous, $service);

            if ($latencyChange) {
                $changes[] = $service['name'].' '.$latencyChange;
            }

            foreach ($this->metricChanges($previous, $service) as $metricChange) {
                $changes[] = $service['name'].' '.$metricChange;
            }
        }

        foreach ($beforeServices as $id => $service) {
            if (! isset($afterServices[$id])) {
                $changes[] = 'Removed service '.$service['name'].'.';
            }
        }

        return $changes;
    }

    protected function summarizeSummary(array $before, array $after): array
    {
        $beforeHighest = $before['summary']['highest']['class'] ?? null;
        $afterHighest = $after['summary']['highest']['class'] ?? null;

        if ($beforeHighest === $afterHighest) {
            return [];
        }

        return ['Overall alert level changed from '.$before['summary']['highest']['label'].' to '.$after['summary']['highest']['label'].'.'];
    }

    protected function servicesById(array $statusPage): array
    {
        return collect($statusPage['services'] ?? [])
            ->keyBy(fn (array $service) => $service['hostid'] ?? $service['host'] ?? $service['name'])
            ->all();
    }

    protected function serviceCount(array $statusPage): int
    {
        return count($statusPage['services'] ?? []);
    }

    protected function triggerChange(array $before, array $after): ?string
    {
        $previousTriggers = $this->activeTriggerDescriptions($before);
        $currentTriggers = $this->activeTriggerDescriptions($after);
        $opened = $currentTriggers->diff($previousTriggers)->values();
        $resolved = $previousTriggers->diff($currentTriggers)->values();
        $parts = [];

        if ($opened->isNotEmpty()) {
            $parts[] = 'opened trigger(s): '.$opened->implode('; ');
        }

        if ($resolved->isNotEmpty()) {
            $parts[] = 'resolved trigger(s): '.$resolved->implode('; ');
        }

        return $parts === [] ? null : implode(' and ', $parts).'.';
    }

    protected function activeTriggerDescriptions(array $service): Collection
    {
        return collect($service['triggers'] ?? [])
            ->where('value', '1')
            ->map(fn (array $trigger) => $trigger['description'])
            ->values();
    }

    protected function latencyChange(array $before, array $after): ?string
    {
        $previous = $before['latency']['milliseconds'] ?? null;
        $current = $after['latency']['milliseconds'] ?? null;

        if ($previous === $current) {
            return null;
        }

        if ($previous === null) {
            return 'started reporting response time '.$current.' ms.';
        }

        if ($current === null) {
            return 'stopped reporting response time.';
        }

        return 'response time changed from '.$previous.' ms to '.$current.' ms.';
    }

    protected function metricChanges(array $before, array $after): array
    {
        $changes = [];
        $previousMetrics = $this->metricsByKey($before);
        $currentMetrics = $this->metricsByKey($after);

        foreach ($currentMetrics as $key => $metric) {
            if (! isset($previousMetrics[$key])) {
                $changes[] = 'started reporting '.$metric['name'].' as '.$this->metricValue($metric).'.';

                continue;
            }

            $previous = $previousMetrics[$key];

            if ($this->metricValue($previous) !== $this->metricValue($metric)) {
                $changes[] = $metric['name'].' changed from '.$this->metricValue($previous).' to '.$this->metricValue($metric).'.';
            }
        }

        foreach ($previousMetrics as $key => $metric) {
            if (! isset($currentMetrics[$key])) {
                $changes[] = 'stopped reporting '.$metric['name'].'.';
            }
        }

        return $changes;
    }

    protected function metricsByKey(array $service): array
    {
        return collect($service['public_metrics'] ?? [])
            ->keyBy('key')
            ->all();
    }

    protected function metricValue(array $metric): string
    {
        if (($metric['lastvalue'] ?? '') === '') {
            return 'No value';
        }

        return ($metric['display_value'] ?? $metric['lastvalue']).($metric['units'] ?? '');
    }
}
