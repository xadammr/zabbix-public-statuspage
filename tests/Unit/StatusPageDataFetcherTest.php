<?php

namespace Tests\Unit;

use App\Services\StatusPageDataFetcher;
use App\Services\StatusPageMacroParser;
use App\Services\ZabbixClient;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class StatusPageDataFetcherTest extends TestCase
{
    public function test_empty_identifiers_do_not_make_zabbix_requests(): void
    {
        $zabbix = Mockery::mock(ZabbixClient::class);
        $zabbix->shouldNotReceive('request');
        $fetcher = new StatusPageDataFetcher($zabbix, new StatusPageMacroParser);

        $this->assertSame([], $fetcher->triggers([]));
        $this->assertSame([], $fetcher->problems([]));
        $this->assertSame([], $fetcher->itemsByKey([], 'health'));
        $this->assertSame([], $fetcher->availableItems([]));
        $this->assertSame([], $fetcher->publicMetricItems([], collect()));
        $this->assertSame([], $fetcher->macros([]));
        $this->assertSame([], $fetcher->latencyItems(collect(), collect()));
        $this->assertSame([], $fetcher->latencySeries([], 60));
    }

    public function test_latency_items_are_fetched_as_web_items_and_scoped_to_each_host_key(): void
    {
        $zabbix = Mockery::mock(ZabbixClient::class);
        $zabbix->shouldReceive('request')
            ->once()
            ->with('item.get', Mockery::on(fn (array $params) => $params['hostids'] === ['1', '2']
                && $params['webitems'] === true
                && $params['filter']['key_'] === ['latency.a', 'latency.b']))
            ->andReturn([
                ['itemid' => '10', 'hostid' => '1', 'key_' => 'latency.a'],
                ['itemid' => '11', 'hostid' => '1', 'key_' => 'latency.b'],
                ['itemid' => '12', 'hostid' => '2', 'key_' => 'latency.b'],
            ]);
        $fetcher = new StatusPageDataFetcher($zabbix, new StatusPageMacroParser);
        $hosts = collect([['hostid' => '1'], ['hostid' => '2']]);
        $macros = collect([
            ['hostid' => '1', 'macro' => '{$PUBLIC_LATENCY_ITEM_KEY}', 'value' => 'latency.a'],
            ['hostid' => '2', 'macro' => '{$PUBLIC_LATENCY_ITEM_KEY}', 'value' => 'latency.b'],
        ]);

        $this->assertSame(['10', '12'], collect($fetcher->latencyItems($hosts, $macros))->pluck('itemid')->all());
    }

    public function test_public_metrics_include_web_scenario_items(): void
    {
        $zabbix = Mockery::mock(ZabbixClient::class);
        $zabbix->shouldReceive('request')
            ->once()
            ->with('item.get', Mockery::on(fn (array $params) => $params['hostids'] === ['1']
                && $params['webitems'] === true
                && $params['filter']['key_'] === [
                    'web.test.time[Calendar,Team,resp]',
                    'web.test.time[Calendar,Venue,resp]',
                ]))
            ->andReturn([]);
        $fetcher = new StatusPageDataFetcher($zabbix, new StatusPageMacroParser);
        $macros = collect([[
            'hostid' => '1',
            'macro' => '{$PUBLIC_METRICS}',
            'value' => 'web.test.time[Calendar,Team,resp],web.test.time[Calendar,Venue,resp]',
        ]]);

        $this->assertSame([], $fetcher->publicMetricItems(['1'], $macros));
    }

    public function test_latency_history_remains_one_batched_request_with_the_combined_limit(): void
    {
        Carbon::setTestNow('2026-11-01 12:00:00');
        $zabbix = Mockery::mock(ZabbixClient::class);
        $zabbix->shouldReceive('request')
            ->once()
            ->with('history.get', Mockery::on(fn (array $params) => $params['itemids'] === ['10', '20']
                && $params['limit'] === 120
                && $params['time_from'] === now()->subMinutes(60)->timestamp))
            ->andReturn([
                ['itemid' => '10', 'clock' => '100', 'value' => '0.25'],
                ['itemid' => '20', 'clock' => '101', 'value' => '31'],
            ]);
        $fetcher = new StatusPageDataFetcher($zabbix, new StatusPageMacroParser);

        try {
            $series = $fetcher->latencySeries(['10', '20'], 60);

            $this->assertSame(250, $series['10'][0]['milliseconds']);
            $this->assertArrayNotHasKey('20', $series);
        } finally {
            Carbon::setTestNow();
        }
    }
}
