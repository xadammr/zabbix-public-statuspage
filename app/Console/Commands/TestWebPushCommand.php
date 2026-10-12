<?php

namespace App\Console\Commands;

use App\Services\BrowserPushNotifier;
use Illuminate\Console\Command;

class TestWebPushCommand extends Command
{
    protected $signature = 'webpush:test {--title=Status page test} {--body=Browser push notifications are working.}';

    protected $description = 'Send a test browser push notification to current subscribers';

    public function handle(BrowserPushNotifier $notifier): int
    {
        $stats = $notifier->sendTest((string) $this->option('title'), (string) $this->option('body'));
        if (! $stats['enabled']) {
            $this->warn('Web push is not fully configured. Check WEB_PUSH_ENABLED and VAPID keys.');

            return self::FAILURE;
        }
        if (! $stats['migrated']) {
            $this->warn('Push subscription table is missing. Run php artisan migrate.');

            return self::FAILURE;
        }
        if ($stats['subscribers'] === 0) {
            $this->warn('No browser push subscribers found.');

            return self::FAILURE;
        }

        $this->info('Test push notification queued.');
        foreach (['subscribers' => 'Subscribers', 'sent' => 'Sent', 'failed' => 'Failed', 'expired' => 'Expired removed'] as $key => $label) {
            $this->line($label.': '.$stats[$key]);
        }
        foreach (array_slice($stats['failures'] ?? [], 0, 5) as $failure) {
            $endpoint = $failure['endpoint'] ?? 'unknown endpoint';
            $endpoint = strlen($endpoint) > 72 ? substr($endpoint, 0, 69).'...' : $endpoint;
            $this->warn('Failure: '.$endpoint.' - '.$failure['reason']);
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
