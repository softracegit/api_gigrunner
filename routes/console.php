<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Shared-hosting friendly: one cron (`schedule:run` every minute) keeps
| Pusher job updates alive without a long-running `queue:work` process.
|
| 1) Drain the database queue (PollAudioJobStatus chains, create jobs, …)
| 2) Safety-net refresh of any stuck audio jobs → broadcasts via Pusher
|
| On a VPS, prefer Supervisor + continuous `php artisan queue:work` instead
| (finer ~3s cadence). This schedule still works as a backup.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('audio:refresh-active')->everyMinute();
