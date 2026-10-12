<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Base64Url\Base64Url;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\VAPID;
use Throwable;

class DiagnoseWebPushCommand extends Command
{
    protected $signature = 'webpush:diagnose';

    protected $description = 'Show browser push notification configuration diagnostics';

    public function handle(): int
    {
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
            VAPID::validate(compact('subject', 'publicKey', 'privateKey'));
            $this->info('VAPID key shape: valid');
        } catch (Throwable $exception) {
            $this->error('VAPID key shape: invalid - '.$exception->getMessage());
        }

        if (! Schema::hasTable('push_subscriptions')) {
            $this->warn('Push subscription table is missing.');

            return self::FAILURE;
        }

        $subscriptions = PushSubscription::query()->get();
        $this->line('Subscribers: '.$subscriptions->count());
        foreach ($subscriptions->groupBy(fn ($subscription) => parse_url($subscription->endpoint, PHP_URL_HOST) ?: 'unknown') as $host => $group) {
            $this->line(' - '.$host.': '.$group->count());
        }

        return self::SUCCESS;
    }
}
