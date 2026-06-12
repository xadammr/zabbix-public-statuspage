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

    public function test_bitrate_values_are_scaled_like_zabbix_units(): void
    {
        $builder = new StatusPageBuilder($this->createMock(ZabbixClient::class), new StatusPageSummary);
        $formatDisplayValue = new ReflectionMethod($builder, 'formatDisplayValue');
        $formatValueWithUnits = new ReflectionMethod($builder, 'formatValueWithUnits');

        $displayValue = $formatDisplayValue->invoke($builder, '1452992', [
            'units' => 'bps',
            'value_type' => '3',
        ]);

        $this->assertSame('1.45M', $displayValue);
        $this->assertSame('1.45 Mbps', $formatValueWithUnits->invoke($builder, $displayValue, 'bps'));

        $displayValue = $formatDisplayValue->invoke($builder, '886808', [
            'units' => 'bps',
            'value_type' => '3',
        ]);

        $this->assertSame('886.81K', $displayValue);
        $this->assertSame('886.81 Kbps', $formatValueWithUnits->invoke($builder, $displayValue, 'bps'));
    }

    public function test_byte_rate_values_are_scaled_like_zabbix_units(): void
    {
        $builder = new StatusPageBuilder($this->createMock(ZabbixClient::class), new StatusPageSummary);
        $formatDisplayValue = new ReflectionMethod($builder, 'formatDisplayValue');
        $formatValueWithUnits = new ReflectionMethod($builder, 'formatValueWithUnits');

        $displayValue = $formatDisplayValue->invoke($builder, '15206919', [
            'units' => 'B/sec',
            'value_type' => '3',
        ]);

        $this->assertSame('15.21M', $displayValue);
        $this->assertSame('15.21 MB/sec', $formatValueWithUnits->invoke($builder, $displayValue, 'B/sec'));
    }

    public function test_lowercase_text_values_are_sentence_cased(): void
    {
        $builder = new StatusPageBuilder($this->createMock(ZabbixClient::class), new StatusPageSummary);
        $formatDisplayValue = new ReflectionMethod($builder, 'formatDisplayValue');

        $this->assertSame('Running normally', $formatDisplayValue->invoke($builder, 'running normally', [
            'units' => '',
            'value_type' => '1',
        ]));
        $this->assertSame('Degraded', $formatDisplayValue->invoke($builder, 'degraded', [
            'units' => '',
            'value_type' => '4',
        ]));
    }

    public function test_text_values_preserve_mixed_case_and_acronyms(): void
    {
        $builder = new StatusPageBuilder($this->createMock(ZabbixClient::class), new StatusPageSummary);
        $formatDisplayValue = new ReflectionMethod($builder, 'formatDisplayValue');

        $this->assertSame('HTTP OK', $formatDisplayValue->invoke($builder, 'HTTP OK', [
            'units' => '',
            'value_type' => '1',
        ]));
        $this->assertSame('MSSQLServer healthy', $formatDisplayValue->invoke($builder, 'MSSQLServer healthy', [
            'units' => '',
            'value_type' => '1',
        ]));
    }

    public function test_zabbix_literal_units_are_cleaned_for_display(): void
    {
        $builder = new StatusPageBuilder($this->createMock(ZabbixClient::class), new StatusPageSummary);
        $formatUnits = new ReflectionMethod($builder, 'formatUnits');

        $this->assertSame('vps', $formatUnits->invoke($builder, '!vps'));
        $this->assertSame('ms', $formatUnits->invoke($builder, 'ms'));
    }

    public function test_values_and_units_are_spaced_for_display(): void
    {
        $builder = new StatusPageBuilder($this->createMock(ZabbixClient::class), new StatusPageSummary);
        $formatValueWithUnits = new ReflectionMethod($builder, 'formatValueWithUnits');

        $this->assertSame('94.57 vps', $formatValueWithUnits->invoke($builder, '94.57', 'vps'));
        $this->assertSame('531.74 KB', $formatValueWithUnits->invoke($builder, '531.74K', 'B'));
        $this->assertSame('7.06 MB', $formatValueWithUnits->invoke($builder, '7.06M', 'B'));
        $this->assertSame('512 B', $formatValueWithUnits->invoke($builder, '512', 'B'));
        $this->assertSame('94.57', $formatValueWithUnits->invoke($builder, '94.57', ''));
    }

    public function test_metric_change_ignores_floating_point_noise(): void
    {
        $builder = new StatusPageBuilder($this->createMock(ZabbixClient::class), new StatusPageSummary);
        $formatMetricChange = new ReflectionMethod($builder, 'formatMetricChange');

        $change = $formatMetricChange->invoke($builder, '94.56918981481365', '94.5691898148137');

        $this->assertSame('same', $change['direction']);
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
