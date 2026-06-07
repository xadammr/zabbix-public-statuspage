<?php

namespace Tests\Unit;

use App\Services\StatusPageBuilder;
use App\Services\ZabbixClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ExampleTest extends TestCase
{
    public function test_byte_values_are_scaled_like_zabbix_units(): void
    {
        $builder = new StatusPageBuilder($this->createMock(ZabbixClient::class));
        $formatDisplayValue = new ReflectionMethod($builder, 'formatDisplayValue');

        $this->assertSame(
            '14.41T',
            $formatDisplayValue->invoke($builder, '15844288666144', [
                'units' => 'B',
                'value_type' => '3',
            ]),
        );
    }
}
