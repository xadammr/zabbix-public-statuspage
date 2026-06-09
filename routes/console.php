<?php

use App\Services\CachedStatusPage;
use App\Services\StatusPageChangeLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('statuspage:poll {--force : Refresh even if the cached snapshot is not due yet}', function (CachedStatusPage $statusPage, StatusPageChangeLog $changeLog): int {
    $before = $statusPage->cached();
    $snapshot = $this->option('force')
        ? $statusPage->refresh()
        : $statusPage->refreshIfDue();
    $refreshed = ! $before
        || ! isset($before['cache']['refreshed_at'])
        || ! $before['cache']['refreshed_at']->equalTo($snapshot['cache']['refreshed_at']);

    $lines = [
        $refreshed ? 'Status page snapshot refreshed.' : 'Status page snapshot is current.',
        'Refreshed: '.$snapshot['cache']['refreshed_at']->toDateTimeString(),
        'Next pull: '.$snapshot['cache']['next_refresh_at']->toDateTimeString(),
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
