<?php

namespace App\Console\Commands;

use App\Services\CachedStatusPage;
use App\Services\StatusPageChangeLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollStatusPageCommand extends Command
{
    protected $signature = 'statuspage:poll {--force : Refresh even if the cached snapshot is not due yet}';

    protected $description = 'Refresh the cached Zabbix status page snapshot';

    public function handle(CachedStatusPage $statusPage, StatusPageChangeLog $changeLog): int
    {
        $before = $statusPage->cached();
        $snapshot = $this->option('force') ? $statusPage->refresh() : $statusPage->refreshIfDue();
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
            ...collect($webPush['failures'] ?? [])->take(3)->map(fn (array $failure) => 'Push failure: '.$failure['reason'])->all(),
            'Changes:',
            ...collect($changeLog->summarize($before, $snapshot))->map(fn (string $change) => ' - '.$change)->all(),
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

        return self::SUCCESS;
    }
}
