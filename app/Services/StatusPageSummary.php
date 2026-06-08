<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StatusPageSummary
{
    protected const SEVERITY_ORDER = ['disaster', 'high', 'average', 'warning', 'information', 'not-classified', 'ok'];

    public function build(Collection|array $services): array
    {
        $services = collect($services);
        $highest = collect(self::SEVERITY_ORDER)
            ->first(fn (string $class) => $services->contains(
                fn (array $service) => ($service['severity']['class'] ?? null) === $class,
            )) ?? 'ok';

        return [
            'total' => $services->count(),
            'ok' => $services->where('severity.class', 'ok')->count(),
            'problem' => $services->where('severity.class', '!=', 'ok')->count(),
            'highest' => $this->forClass($highest),
            'severity_counts' => collect(self::SEVERITY_ORDER)
                ->map(fn (string $class) => [
                    ...$this->forClass($class),
                    'count' => $services->where('severity.class', $class)->count(),
                ])
                ->filter(fn (array $severity) => $severity['count'] > 0)
                ->values()
                ->all(),
        ];
    }

    public function forPriority(int $priority): array
    {
        return match ($priority) {
            0 => $this->forClass('not-classified'),
            1 => $this->forClass('information'),
            2 => $this->forClass('warning'),
            3 => $this->forClass('average'),
            4 => $this->forClass('high'),
            5 => $this->forClass('disaster'),
            default => [
                'label' => 'Unknown',
                'class' => 'unknown',
            ],
        };
    }

    public function forClass(string $class): array
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
