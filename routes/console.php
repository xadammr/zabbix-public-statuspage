<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command(app()->environment('local') ? 'statuspage:poll' : 'statuspage:poll --quiet')
    ->everyThirtySeconds()
    ->withoutOverlapping();
