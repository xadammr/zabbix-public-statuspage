<?php

namespace Tests\Unit;

use App\Services\BrowserPushNotifier;
use Illuminate\Support\Facades\Config;
use ReflectionMethod;
use Tests\TestCase;

class BrowserPushNotifierTest extends TestCase
{
    public function test_new_active_trigger_creates_problem_event(): void
    {
        Config::set('services.web_push.min_severity', 'warning');

        $events = $this->events([
            'services' => [
                $this->service('1', 'Website', 'ok', 'OK', []),
            ],
        ], [
            'services' => [
                $this->service('1', 'Website', 'high', 'High', ['HTTP check failed']),
            ],
        ]);

        $this->assertCount(1, $events);
        $this->assertSame('High: Website', $events[0]['title']);
        $this->assertSame('HTTP check failed', $events[0]['body']);
    }

    public function test_recovery_event_can_be_created(): void
    {
        Config::set('services.web_push.min_severity', 'warning');
        Config::set('services.web_push.notify_recoveries', true);

        $events = $this->events([
            'services' => [
                $this->service('1', 'Website', 'high', 'High', ['HTTP check failed']),
            ],
        ], [
            'services' => [
                $this->service('1', 'Website', 'ok', 'OK', []),
            ],
        ]);

        $this->assertCount(1, $events);
        $this->assertSame('Recovered: Website', $events[0]['title']);
    }

    protected function events(array $before, array $after): array
    {
        $notifier = new BrowserPushNotifier;
        $events = new ReflectionMethod($notifier, 'events');

        return $events->invoke($notifier, $before, $after);
    }

    protected function service(string $hostId, string $name, string $class, string $label, array $triggers): array
    {
        return [
            'hostid' => $hostId,
            'name' => $name,
            'severity' => [
                'class' => $class,
                'label' => $label,
            ],
            'triggers' => collect($triggers)
                ->map(fn (string $trigger) => [
                    'description' => $trigger,
                    'value' => '1',
                ])
                ->all(),
        ];
    }
}
