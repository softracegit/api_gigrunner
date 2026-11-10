<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Safety net: if a PollAudioJobStatus chain dies, cron still refreshes active jobs
| once per minute (and broadcasts via model updated → Pusher).
| Prefer a continuous `php artisan queue:work` for ~3s updates.
*/
Schedule::command('audio:refresh-active')->everyMinute();
