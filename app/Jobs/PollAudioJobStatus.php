<?php

namespace App\Jobs;

use App\Models\AudioJob;
use App\Services\Audio\AudioJobService;
use App\Services\Audio\MusicCreateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Polls provider status for an audio job and re-queues itself until complete/failed.
 * Each save that changes status broadcasts via Pusher (AudioJobStatusUpdated).
 */
class PollAudioJobStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $audioJobId,
        public int $attempt = 0,
    ) {}

    public function handle(AudioJobService $audioJobs, MusicCreateService $creates): void
    {
        if (! config('audio.poll.enabled', true)) {
            return;
        }

        $job = AudioJob::query()->find($this->audioJobId);
        if (! $job) {
            return;
        }

        if (in_array($job->status, [AudioJob::STATUS_COMPLETE, AudioJob::STATUS_FAILED], true)) {
            return;
        }

        $maxAttempts = max(1, (int) config('audio.poll.max_attempts', 300));
        if ($this->attempt >= $maxAttempts) {
            Log::warning('Audio job poll max attempts reached', [
                'audio_job_id' => $job->id,
                'uuid' => $job->uuid,
                'attempts' => $this->attempt,
            ]);

            return;
        }

        try {
            if ($job->kind === AudioJob::KIND_CREATE) {
                $creates->refresh($job);
            } else {
                $audioJobs->refresh($job);
            }
        } catch (\Throwable $e) {
            Log::warning('Audio job poll refresh failed', [
                'audio_job_id' => $job->id,
                'uuid' => $job->uuid,
                'error' => $e->getMessage(),
            ]);
        }

        $job = $job->fresh();
        if (! $job || in_array($job->status, [AudioJob::STATUS_COMPLETE, AudioJob::STATUS_FAILED], true)) {
            return;
        }

        $interval = max(1, (int) config('audio.poll.interval_seconds', 3));

        self::dispatch($this->audioJobId, $this->attempt + 1)
            ->delay(now()->addSeconds($interval));
    }
}
