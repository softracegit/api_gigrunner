<?php

namespace App\Jobs;

use App\Models\AudioJob;
use App\Services\Audio\MusicCreateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMusicCreateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 360;

    public function __construct(public int $audioJobId) {}

    public function handle(MusicCreateService $creates): void
    {
        $job = AudioJob::query()->find($this->audioJobId);
        if (! $job) {
            return;
        }

        $creates->process($job);
    }
}
