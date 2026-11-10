<?php

namespace App\Console\Commands;

use App\Jobs\PollAudioJobStatus;
use App\Models\AudioJob;
use App\Services\Audio\AudioJobService;
use App\Services\Audio\MusicCreateService;
use Illuminate\Console\Command;

class RefreshActiveAudioJobsCommand extends Command
{
    protected $signature = 'audio:refresh-active
                            {--dispatch : Queue PollAudioJobStatus instead of refreshing inline}
                            {--limit=50 : Max jobs to process}';

    protected $description = 'Refresh queued/processing audio jobs (safety net + shared-hosting cron)';

    public function handle(AudioJobService $audioJobs, MusicCreateService $creates): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dispatch = (bool) $this->option('dispatch');

        $jobs = AudioJob::query()
            ->whereIn('status', [AudioJob::STATUS_QUEUED, AudioJob::STATUS_PROCESSING])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('No active audio jobs.');

            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            if ($dispatch) {
                PollAudioJobStatus::dispatch($job->id);
                $this->line("Queued poll #{$job->id} ({$job->uuid})");

                continue;
            }

            try {
                if ($job->kind === AudioJob::KIND_CREATE) {
                    $creates->refresh($job);
                } else {
                    $audioJobs->refresh($job);
                }
                $fresh = $job->fresh();
                $this->line(sprintf(
                    '#%d %s → %s %d%%',
                    $job->id,
                    $job->uuid,
                    $fresh?->status ?? '?',
                    (int) ($fresh?->progress ?? 0),
                ));
            } catch (\Throwable $e) {
                $this->error("#{$job->id} failed: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
