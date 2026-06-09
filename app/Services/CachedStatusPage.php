<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CachedStatusPage
{
    public function __construct(
        protected StatusPageBuilder $builder,
    ) {}

    public function current(): array
    {
        $snapshot = $this->cached();

        if (! $snapshot) {
            return $this->refresh();
        }

        return $snapshot;
    }

    public function cached(): ?array
    {
        $snapshot = Cache::get($this->cacheKey());

        if (! $snapshot) {
            return null;
        }

        return $this->hydrate($snapshot);
    }

    public function refresh(): array
    {
        $startedAt = hrtime(true);
        $statusPage = $this->builder->build();
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $refreshedAt = now();
        $pollInterval = $this->pollInterval();

        $statusPage['cache'] = [
            'refreshed_at' => $refreshedAt,
            'next_refresh_at' => $refreshedAt->copy()->addSeconds($pollInterval),
            'poll_interval' => $pollInterval,
            'stale_after' => $this->staleAfter(),
            'is_stale' => false,
            'age_seconds' => 0,
            'duration_ms' => $durationMs,
            'duration' => $this->formatDuration($durationMs),
        ];

        Cache::forever($this->cacheKey(), $this->normalize($statusPage));

        return $statusPage;
    }

    public function refreshIfDue(): array
    {
        $snapshot = $this->cached();

        if (! $snapshot) {
            return $this->refresh();
        }

        $statusPage = $snapshot;

        if ($statusPage['cache']['next_refresh_at']->isPast()) {
            return $this->refresh();
        }

        return $statusPage;
    }

    public function pollInterval(): int
    {
        return max(15, (int) config('zabbix.statuspage_poll_interval', 60));
    }

    public function staleAfter(): int
    {
        return max(60, (int) config('zabbix.statuspage_stale_after', 120));
    }

    protected function cacheKey(): string
    {
        return config('zabbix.statuspage_cache_key', 'statuspage.snapshot');
    }

    protected function formatDuration(float $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return round($milliseconds).'ms';
        }

        return number_format($milliseconds / 1000, 2).'s';
    }

    protected function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item) => $this->normalize($item))
                ->all();
        }

        return $value;
    }

    protected function hydrate(array $statusPage): array
    {
        $statusPage['generated_at'] = Carbon::parse($statusPage['generated_at']);

        if (isset($statusPage['cache']['refreshed_at'])) {
            $statusPage['cache']['refreshed_at'] = Carbon::parse($statusPage['cache']['refreshed_at']);
        }

        if (isset($statusPage['cache']['next_refresh_at'])) {
            $statusPage['cache']['next_refresh_at'] = Carbon::parse($statusPage['cache']['next_refresh_at']);
        }

        if (isset($statusPage['cache']['refreshed_at'])) {
            $ageSeconds = $statusPage['cache']['refreshed_at']->diffInSeconds(now());
            $staleAfter = (int) ($statusPage['cache']['stale_after'] ?? $this->staleAfter());

            $statusPage['cache']['age_seconds'] = $ageSeconds;
            $statusPage['cache']['stale_after'] = $staleAfter;
            $statusPage['cache']['is_stale'] = $ageSeconds > $staleAfter;
        }

        return $statusPage;
    }
}
