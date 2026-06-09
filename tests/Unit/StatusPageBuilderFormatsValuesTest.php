<?php

namespace Tests\Unit;

use App\Services\StatusPageBuilder;
use App\Services\StatusPageSummary;
use App\Services\ZabbixClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class StatusPageBuilderFormatsValuesTest extends TestCase
{
    public function test_byte_values_are_scaled_like_zabbix_units(): void
    {
        $builder = new StatusPageBuilder($this->createMock(ZabbixClient::class), new StatusPageSummary);
        $formatDisplayValue = new ReflectionMethod($builder, 'formatDisplayValue');

        $this->assertSame(
            '14.41T',
            $formatDisplayValue->invoke($builder, '15844288666144', [
                'units' => 'B',
                'value_type' => '3',
            ]),
        );
    }

    public function test_latency_chart_range_uses_observed_values_and_computes_duration(): void
    {
        $builder = new StatusPageBuilder($this->createMock(ZabbixClient::class), new StatusPageSummary);
        $formatLatencyChart = new ReflectionMethod($builder, 'formatLatencyChart');
        $startedAt = 1781000000;

        $chart = $formatLatencyChart->invoke($builder, [
            [
                'clock' => $startedAt,
                'milliseconds' => 75,
            ],
            [
                'clock' => $startedAt + 60,
                'milliseconds' => 150,
            ],
            [
                'clock' => $startedAt + 120,
                'milliseconds' => 250,
            ],
        ], [
            'warning' => 200,
            'danger' => 1000,
        ]);

        $this->assertSame(75, $chart['min_ms']);
        $this->assertSame(250, $chart['max_ms']);
        $this->assertSame('3 min', $chart['duration_label']);
        $this->assertSame('warning', $chart['segments'][1]['class']);
    }
}
