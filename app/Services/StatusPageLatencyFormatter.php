<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StatusPageLatencyFormatter
{
    public const HISTORY_MINUTES = 60;

    public const HISTORY_LIMIT = 60;

    public function __construct(protected StatusPageMacroParser $macros) {}

    public function format(?array $item, ?array $historyItem = null, array $thresholds = []): ?array
    {
        if (! $item || ($item['lastvalue'] ?? '') === '') {
            return null;
        }

        $seconds = (float) $item['lastvalue'];

        return [
            'seconds' => $seconds,
            'milliseconds' => (int) round($seconds * 1000),
            'clock' => $item['lastclock'] ?? null,
            'source' => $item['name'] ?? 'Latency',
            'series' => $this->chart($item['series'] ?? $historyItem['series'] ?? [], $thresholds),
        ];
    }

    public function thresholds(Collection $macros): array
    {
        $warning = $this->macros->integer($macros, '{$PUBLIC_HTTP_RESPONSE_WARN}', 200);
        $danger = $this->macros->integer($macros, '{$PUBLIC_HTTP_RESPONSE_HIGH}', 1000);

        return ['warning' => $warning, 'danger' => $danger <= $warning ? $warning + 1 : $danger];
    }

    public function chart(array $series, array $thresholds): ?array
    {
        if (count($series) < 2) {
            return null;
        }

        $warning = $thresholds['warning'];
        $danger = $thresholds['danger'];
        $values = collect($series);
        $minClock = $values->min('clock');
        $maxClock = $values->max('clock');
        $minMs = (int) round($values->min('milliseconds'));
        $maxMs = (int) round($values->max('milliseconds'));
        $scaleMax = max($danger, $maxMs);
        $width = 240;
        $height = 72;
        $padding = 6;
        $innerWidth = $width - ($padding * 2);
        $innerHeight = $height - ($padding * 2);

        $points = $values->map(function (array $point) use ($minClock, $maxClock, $scaleMax, $padding, $innerWidth, $innerHeight): array {
            $x = $padding + ((($point['clock'] - $minClock) / max(1, $maxClock - $minClock)) * $innerWidth);
            $y = $padding + ($innerHeight - (($point['milliseconds'] / $scaleMax) * $innerHeight));

            return ['milliseconds' => (int) $point['milliseconds'], 'point' => round($x, 2).','.round($y, 2)];
        });

        return [
            'segments' => $this->segments($points, $warning, $danger),
            'width' => $width,
            'height' => $height,
            'max_ms' => $maxMs,
            'min_ms' => $minMs,
            'samples' => $values->count(),
            'duration_label' => $this->durationLabel((int) $minClock, (int) $maxClock, $values->count()),
            'thresholds' => collect([$warning, $danger])->map(function (int $threshold) use ($scaleMax, $padding, $innerHeight): array {
                $y = $padding + ($innerHeight - (($threshold / $scaleMax) * $innerHeight));

                return ['value' => $threshold, 'y' => round(max($padding, min($padding + $innerHeight, $y)), 2)];
            })->all(),
        ];
    }

    protected function durationLabel(int $minClock, int $maxClock, int $samples): string
    {
        if ($samples >= self::HISTORY_LIMIT) {
            return self::HISTORY_MINUTES.' min';
        }

        $minutes = max(1, (int) ceil(max(0, $maxClock - $minClock) / 60) + 1);

        return min(self::HISTORY_MINUTES, $minutes).' min';
    }

    protected function segments(Collection $points, int $warning, int $danger): array
    {
        return $points->values()->sliding(2)->map(function (Collection $pair) use ($warning, $danger): array {
            $severity = $pair->max('milliseconds');

            return [
                'class' => match (true) {
                    $severity >= $danger => 'danger',
                    $severity >= $warning => 'warning',
                    default => 'ok',
                },
                'points' => $pair->pluck('point')->implode(' '),
            ];
        })->values()->all();
    }
}
