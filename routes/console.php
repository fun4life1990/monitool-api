<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('domains:dispatch-checks')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
