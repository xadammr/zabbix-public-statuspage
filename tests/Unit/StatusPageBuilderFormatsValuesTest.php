<?php

namespace Tests\Unit;

use App\Services\StatusPageLatencyFormatter;
use App\Services\StatusPageMacroParser;
use App\Services\StatusPageMetricFormatter;
use PHPUnit\Framework\TestCase;

class StatusPageBuilderFormatsValuesTest extends TestCase
{
    protected StatusPageMetricFormatter $metrics;

    protected StatusPageLatencyFormatter $latency;

    protected function setUp(): void
    {
        parent::setUp();

        $macros = new StatusPageMacroParser;
        $this->metrics = new StatusPageMetricFormatter($macros);
        $this->latency = new StatusPageLatencyFormatter($macros);
    }

    public function test_byte_values_are_scaled_like_zabbix_units(): void
    {
        $this->assertSame(
            '14.41T',
            $this->metrics->displayValue('15844288666144', [
                'units' => 'B',
                'value_type' => '3',
            ]),
        );
    }

    public function test_bitrate_values_are_scaled_like_zabbix_units(): void
    {
        $displayValue = $this->metrics->displayValue('1452992', [
            'units' => 'bps',
            'value_type' => '3',
        ]);

        $this->assertSame('1.45M', $displayValue);
        $this->assertSame('1.45 Mbps', $this->metrics->valueWithUnits($displayValue, 'bps'));

        $displayValue = $this->metrics->displayValue('886808', [
            'units' => 'bps',
            'value_type' => '3',
        ]);

        $this->assertSame('886.81K', $displayValue);
        $this->assertSame('886.81 Kbps', $this->metrics->valueWithUnits($displayValue, 'bps'));
    }

    public function test_byte_rate_values_are_scaled_like_zabbix_units(): void
    {
        $displayValue = $this->metrics->displayValue('15206919', [
            'units' => 'B/sec',
            'value_type' => '3',
        ]);

        $this->assertSame('15.21M', $displayValue);
        $this->assertSame('15.21 MB/sec', $this->metrics->valueWithUnits($displayValue, 'B/sec'));
    }

    public function test_lowercase_text_values_are_sentence_cased(): void
    {
        $this->assertSame('Running normally', $this->metrics->displayValue('running normally', [
            'units' => '',
            'value_type' => '1',
        ]));
        $this->assertSame('Degraded', $this->metrics->displayValue('degraded', [
            'units' => '',
            'value_type' => '4',
        ]));
    }

    public function test_text_values_preserve_mixed_case_and_acronyms(): void
    {
        $this->assertSame('HTTP OK', $this->metrics->displayValue('HTTP OK', [
            'units' => '',
            'value_type' => '1',
        ]));
        $this->assertSame('MSSQLServer healthy', $this->metrics->displayValue('MSSQLServer healthy', [
            'units' => '',
            'value_type' => '1',
        ]));
    }

    public function test_zabbix_literal_units_are_cleaned_for_display(): void
    {
        $this->assertSame('vps', $this->metrics->units('!vps'));
        $this->assertSame('ms', $this->metrics->units('ms'));
    }

    public function test_values_and_units_are_spaced_for_display(): void
    {
        $this->assertSame('94.57 vps', $this->metrics->valueWithUnits('94.57', 'vps'));
        $this->assertSame('531.74 KB', $this->metrics->valueWithUnits('531.74K', 'B'));
        $this->assertSame('7.06 MB', $this->metrics->valueWithUnits('7.06M', 'B'));
        $this->assertSame('512 B', $this->metrics->valueWithUnits('512', 'B'));
        $this->assertSame('94.57', $this->metrics->valueWithUnits('94.57', ''));
    }

    public function test_metric_change_ignores_floating_point_noise(): void
    {
        $change = $this->metrics->change('94.56918981481365', '94.5691898148137');

        $this->assertSame('same', $change['direction']);
    }

    public function test_latency_chart_range_uses_observed_values_and_computes_duration(): void
    {
        $startedAt = 1781000000;

        $chart = $this->latency->chart([
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
