<?php

namespace App\Services;

use App\Models\PushSubscription as PushSubscriptionModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class BrowserPushNotifier
{
    protected const SEVERITY_RANKS = [
        'ok' => 0,
        'not-classified' => 1,
        'information' => 2,
        'warning' => 3,
        'average' => 4,
        'high' => 5,
        'disaster' => 6,
    ];

    public function notifyChanges(?array $before, array $after): array
    {
        if (! $this->enabled() || ! $before) {
            return $this->emptyStats();
        }

        $events = $this->events($before, $after);

        if ($events === []) {
            return $this->emptyStats(['enabled' => true, 'events' => 0]);
        }

        return $this->sendPayloads($events);
    }

    public function sendTest(string $title, string $body): array
    {
        if (! $this->enabled()) {
            return $this->emptyStats();
        }

        return $this->sendPayloads([[
            'title' => $title,
            'body' => $body,
            'url' => url('/'),
            'tag' => 'statuspage-test',
        ]]);
    }

    public function sendPayloads(array $payloads): array
    {
        if (! Schema::hasTable('push_subscriptions')) {
            return $this->emptyStats([
                'enabled' => true,
                'migrated' => false,
                'events' => count($payloads),
            ]);
        }

        $subscriptions = PushSubscriptionModel::query()->get();
        $stats = [
            'enabled' => true,
            'migrated' => true,
            'events' => count($payloads),
            'sent' => 0,
            'failed' => 0,
            'expired' => 0,
            'subscribers' => $subscriptions->count(),
            'failures' => [],
        ];

        if ($subscriptions->isEmpty() || $payloads === []) {
            return $stats;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.web_push.vapid_subject'),
                    'publicKey' => config('services.web_push.vapid_public_key'),
                    'privateKey' => config('services.web_push.vapid_private_key'),
                ],
            ]);
            $webPush->setReuseVAPIDHeaders(true);

            foreach ($payloads as $payload) {
                foreach ($subscriptions as $subscription) {
                    $webPush->queueNotification(
                        Subscription::create([
                            'endpoint' => $subscription->endpoint,
                            'publicKey' => $subscription->public_key,
                            'authToken' => $subscription->auth_token,
                            'contentEncoding' => $subscription->content_encoding,
                        ]),
                        json_encode($payload, JSON_THROW_ON_ERROR),
                    );
                }
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    $stats['expired']++;
                    PushSubscriptionModel::query()
                        ->where('endpoint_hash', hash('sha256', $report->getEndpoint()))
                        ->delete();
                }

                if (! $report->isSuccess()) {
                    $stats['failed']++;
                    $stats['failures'][] = [
                        'endpoint' => $report->getEndpoint(),
                        'reason' => $report->getReason(),
                    ];
                    Log::warning('statuspage:web_push_failed', [
                        'endpoint' => $report->getEndpoint(),
                        'reason' => $report->getReason(),
                    ]);

                    continue;
                }

                $stats['sent']++;
            }
        } catch (Throwable $exception) {
            $stats['failed'] = $subscriptions->count() * count($payloads);
            $stats['failures'][] = [
                'endpoint' => null,
                'reason' => $exception->getMessage(),
            ];
            Log::warning('statuspage:web_push_error', [
                'message' => $exception->getMessage(),
            ]);
        }

        return $stats;
    }

    protected function emptyStats(array $overrides = []): array
    {
        return [
            'enabled' => false,
            'migrated' => Schema::hasTable('push_subscriptions'),
            'events' => 0,
            'sent' => 0,
            'failed' => 0,
            'expired' => 0,
            'subscribers' => Schema::hasTable('push_subscriptions')
                ? PushSubscriptionModel::query()->count()
                : 0,
            'failures' => [],
            ...$overrides,
        ];
    }

    protected function enabled(): bool
    {
        return (bool) config('services.web_push.enabled')
            && (bool) config('services.web_push.vapid_subject')
            && (bool) config('services.web_push.vapid_public_key')
            && (bool) config('services.web_push.vapid_private_key');
    }

    protected function events(array $before, array $after): array
    {
        $events = [];
        $beforeServices = $this->servicesById($before);
        $minSeverity = $this->severityRank(config('services.web_push.min_severity', 'warning'));

        foreach ($this->servicesById($after) as $id => $service) {
            $previous = $beforeServices[$id] ?? null;

            if (! $previous) {
                continue;
            }

            $previousRank = $this->serviceSeverityRank($previous);
            $currentRank = $this->serviceSeverityRank($service);
            $openedTriggers = $this->activeTriggerDescriptions($service)
                ->diff($this->activeTriggerDescriptions($previous))
                ->values();

            if ($openedTriggers->isNotEmpty() && $currentRank >= $minSeverity) {
                $events[] = $this->problemEvent($service, $openedTriggers->first());

                continue;
            }

            if ($currentRank > $previousRank && $currentRank >= $minSeverity) {
                $events[] = $this->problemEvent($service);

                continue;
            }

            if (
                config('services.web_push.notify_recoveries', true)
                && $previousRank >= $minSeverity
                && $currentRank === 0
            ) {
                $events[] = $this->recoveryEvent($service);
            }
        }

        return $events;
    }

    protected function problemEvent(array $service, ?string $trigger = null): array
    {
        $severity = $service['severity']['label'] ?? 'Problem';

        return [
            'title' => $severity.': '.$service['name'],
            'body' => $trigger ?: 'Service status changed to '.$severity.'.',
            'url' => url('/'),
            'tag' => 'statuspage-service-'.$service['hostid'].'-problem',
        ];
    }

    protected function recoveryEvent(array $service): array
    {
        return [
            'title' => 'Recovered: '.$service['name'],
            'body' => 'Service status returned to OK.',
            'url' => url('/'),
            'tag' => 'statuspage-service-'.$service['hostid'].'-recovered',
        ];
    }

    protected function servicesById(array $statusPage): array
    {
        return collect($statusPage['services'] ?? [])
            ->keyBy(fn (array $service) => $service['hostid'] ?? $service['host'] ?? $service['name'])
            ->all();
    }

    protected function activeTriggerDescriptions(array $service): Collection
    {
        return collect($service['triggers'] ?? [])
            ->where('value', '1')
            ->map(fn (array $trigger) => $trigger['description'])
            ->values();
    }

    protected function serviceSeverityRank(array $service): int
    {
        return $this->severityRank($service['severity']['class'] ?? 'ok');
    }

    protected function severityRank(string $severity): int
    {
        return self::SEVERITY_RANKS[$severity] ?? self::SEVERITY_RANKS['warning'];
    }
}
