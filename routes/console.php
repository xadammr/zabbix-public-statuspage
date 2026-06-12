<?php

use App\Services\BrowserPushNotifier;
use App\Services\CachedStatusPage;
use App\Services\StatusPageChangeLog;
use Base64Url\Base64Url;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\VAPID;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('webpush:keys', function (): int {
    $keys = VAPID::createVapidKeys();

    $this->line('WEB_PUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
    $this->line('WEB_PUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);

    return 0;
})->purpose('Generate VAPID keys for browser push notifications');

Artisan::command('webpush:diagnose', function (): int {
    $publicKey = (string) config('services.web_push.vapid_public_key');
    $privateKey = (string) config('services.web_push.vapid_private_key');
    $subject = (string) config('services.web_push.vapid_subject');

    $this->line('Enabled: '.(config('services.web_push.enabled') ? 'yes' : 'no'));
    $this->line('Subject: '.($subject ?: '(missing)'));
    if ($subject !== '' && ! str_starts_with($subject, 'https://') && ! str_starts_with($subject, 'mailto:')) {
        $this->warn('Subject should be a mailto: address or HTTPS URL. Some push services reject HTTP subjects.');
    }
    $this->line('Public key: '.($publicKey ? substr($publicKey, 0, 10).'...'.substr($publicKey, -6) : '(missing)'));
    $this->line('Private key: '.($privateKey ? substr($privateKey, 0, 6).'...'.substr($privateKey, -6) : '(missing)'));

    try {
        $this->line('Decoded public key bytes: '.strlen(Base64Url::decode($publicKey)));
        $this->line('Decoded private key bytes: '.strlen(Base64Url::decode($privateKey)));
        VAPID::validate([
            'subject' => $subject,
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ]);
        $this->info('VAPID key shape: valid');
    } catch (Throwable $exception) {
        $this->error('VAPID key shape: invalid - '.$exception->getMessage());
    }

    if (! Schema::hasTable('push_subscriptions')) {
        $this->warn('Push subscription table is missing.');

        return 1;
    }

    $subscriptions = \App\Models\PushSubscription::query()->get();
    $this->line('Subscribers: '.$subscriptions->count());

    foreach ($subscriptions->groupBy(fn ($subscription) => parse_url($subscription->endpoint, PHP_URL_HOST) ?: 'unknown') as $host => $group) {
        $this->line(' - '.$host.': '.$group->count());
    }

    return 0;
})->purpose('Show browser push notification configuration diagnostics');

Artisan::command('webpush:test {--title=Status page test} {--body=Browser push notifications are working.}', function (BrowserPushNotifier $notifier): int {
    $stats = $notifier->sendTest(
        (string) $this->option('title'),
        (string) $this->option('body'),
    );

    if (! $stats['enabled']) {
        $this->warn('Web push is not fully configured. Check WEB_PUSH_ENABLED and VAPID keys.');

        return 1;
    }

    if (! $stats['migrated']) {
        $this->warn('Push subscription table is missing. Run php artisan migrate.');

        return 1;
    }

    if ($stats['subscribers'] === 0) {
        $this->warn('No browser push subscribers found.');

        return 1;
    }

    $this->info('Test push notification queued.');
    $this->line('Subscribers: '.$stats['subscribers']);
    $this->line('Sent: '.$stats['sent']);
    $this->line('Failed: '.$stats['failed']);
    $this->line('Expired removed: '.$stats['expired']);

    foreach (array_slice($stats['failures'] ?? [], 0, 5) as $failure) {
        $endpoint = $failure['endpoint'] ?? 'unknown endpoint';

        if (strlen($endpoint) > 72) {
            $endpoint = substr($endpoint, 0, 69).'...';
        }

        $this->warn('Failure: '.$endpoint.' - '.$failure['reason']);
    }

    return $stats['failed'] > 0 ? 1 : 0;
})->purpose('Send a test browser push notification to current subscribers');

Artisan::command('statuspage:poll {--force : Refresh even if the cached snapshot is not due yet}', function (CachedStatusPage $statusPage, StatusPageChangeLog $changeLog): int {
    $before = $statusPage->cached();
    $snapshot = $this->option('force')
        ? $statusPage->refresh()
        : $statusPage->refreshIfDue();
    $refreshed = ! $before
        || ! isset($before['cache']['refreshed_at'])
        || ! $before['cache']['refreshed_at']->equalTo($snapshot['cache']['refreshed_at']);
    $webPush = $snapshot['notifications']['web_push'] ?? null;
    $webPushSummary = $webPush
        ? sprintf(
            '%d event(s), %d subscriber(s), %d sent, %d failed, %d expired',
            $webPush['events'] ?? 0,
            $webPush['subscribers'] ?? 0,
            $webPush['sent'] ?? 0,
            $webPush['failed'] ?? 0,
            $webPush['expired'] ?? 0,
        )
        : 'not run';

    $lines = [
        $refreshed ? 'Status page snapshot refreshed.' : 'Status page snapshot is current.',
        'Refreshed: '.$snapshot['cache']['refreshed_at']->toDateTimeString(),
        'Next pull: '.$snapshot['cache']['next_refresh_at']->toDateTimeString(),
        'Push: '.$webPushSummary,
        ...collect($webPush['failures'] ?? [])
            ->take(3)
            ->map(fn (array $failure) => 'Push failure: '.$failure['reason'])
            ->all(),
        'Changes:',
        ...collect($changeLog->summarize($before, $snapshot))
            ->map(fn (string $change) => ' - '.$change)
            ->all(),
    ];

    foreach ($lines as $index => $line) {
        $index === 0 ? $this->info($line) : $this->line($line);
    }

    if (app()->environment('local')) {
        Log::info('statuspage:poll', [
            'refreshed' => $refreshed,
            'refreshed_at' => $snapshot['cache']['refreshed_at']->toIso8601String(),
            'next_pull_at' => $snapshot['cache']['next_refresh_at']->toIso8601String(),
            'changes' => $changeLog->summarize($before, $snapshot),
        ]);
    }

    return 0;
})->purpose('Refresh the cached Zabbix status page snapshot');

Schedule::command(app()->environment('local') ? 'statuspage:poll' : 'statuspage:poll --quiet')
    ->everyThirtySeconds()
    ->withoutOverlapping();
