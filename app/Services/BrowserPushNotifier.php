<?php

namespace App\Services;

use App\Models\PushSubscription as PushSubscriptionModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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

    public function notifyChanges(?array $before, array $after): void
    {
        if (! $this->enabled() || ! $before) {
            return;
        }

        $events = $this->events($before, $after);

        if ($events === []) {
            return;
        }

        $subscriptions = PushSubscriptionModel::query()->get();

        if ($subscriptions->isEmpty()) {
            return;
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

            foreach ($events as $event) {
                foreach ($subscriptions as $subscription) {
                    $webPush->queueNotification(
                        Subscription::create([
                            'endpoint' => $subscription->endpoint,
                            'publicKey' => $subscription->public_key,
                            'authToken' => $subscription->auth_token,
                            'contentEncoding' => $subscription->content_encoding,
                        ]),
                        json_encode($event, JSON_THROW_ON_ERROR),
                    );
                }
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscriptionModel::query()
                        ->where('endpoint_hash', hash('sha256', $report->getEndpoint()))
                        ->delete();
                }

                if (! $report->isSuccess()) {
                    Log::warning('statuspage:web_push_failed', [
                        'endpoint' => $report->getEndpoint(),
                        'reason' => $report->getReason(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('statuspage:web_push_error', [
                'message' => $exception->getMessage(),
            ]);
        }
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
