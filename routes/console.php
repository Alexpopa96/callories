<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('fit:send-reminders')->hourlyAt(0);
Schedule::command('fit:review-calorie-goals')->weeklyOn(1, '07:00');
