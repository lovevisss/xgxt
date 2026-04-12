<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sync:passes-from-middata --days=3')->cron('0 1 */2 * *');
Schedule::command('sync:reconcile-student-passes')->cron('20 1 */2 * *');
