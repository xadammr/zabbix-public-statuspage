<?php

namespace Tests\Unit;

use App\Services\StatusPageSummary;
use Tests\TestCase;

class StatusPageSummaryTest extends TestCase
{
    public function test_not_classified_priority_is_labeled_as_note(): void
    {
        $severity = app(StatusPageSummary::class)->forPriority(0);

        $this->assertSame('Note', $severity['label']);
        $this->assertSame('not-classified', $severity['class']);
    }
}
