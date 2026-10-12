<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ClearWebPushSubscriptionsCommand extends Command
{
    protected $signature = 'webpush:clear {--force : Clear subscribers without asking for confirmation}';

    protected $description = 'Remove all browser push notification subscribers';

    public function handle(): int
    {
        if (! Schema::hasTable('push_subscriptions')) {
            $this->warn('Push subscription table is missing. Run php artisan migrate.');

            return self::FAILURE;
        }
        $subscribers = PushSubscription::query()->count();
        if ($subscribers === 0) {
            $this->info('No browser push subscribers found.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm("Remove {$subscribers} browser push subscriber(s)?")) {
            $this->line('No subscribers removed.');

            return self::SUCCESS;
        }

        PushSubscription::query()->delete();
        $this->info("Removed {$subscribers} browser push subscriber(s).");

        return self::SUCCESS;
    }
}
