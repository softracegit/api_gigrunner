<?php

namespace App\Services\Audio;

use App\Models\AudioJob;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class AudioJobService
{
    public function __construct(
        private MagicChordsClient $magicChords,
        private ElevenLabsClient $elevenLabs,
        private CreditService $credits,
        private SongResultNormalizer $normalizer,
    ) {}

    /**
     * @param  list<string>|null  $tasks
     * @param  array{lyrics_granularity?: string}  $options
     */
    public function createFromUpload(
        User $user,
        UploadedFile $file,
        ?string $kind = null,
        ?array $tasks = null,
        array $options = [],
    ): AudioJob {
        $tasks = $this->resolveTasks($kind, $tasks);
        $options = $this->normalizeOptions($options);
        $this->assertLicensed($user);

        $cost = $this->creditCostForTasks($tasks);
        $this->credits->consume($user, 'ai', $cost, 'audio_'.implode('_', $tasks), null, [
            'source' => 'upload',
            'filename' => $file->getClientOriginalName(),
            'tasks' => $tasks,
            'options' => $options,
        ]);

        $path = $file->store('audio_uploads/'.$user->id, config('audio.upload.disk'));

        $job = $this->makeJob($user, $tasks, $options, $cost, [
            'source_type' => 'file',
            'source_path' => $path,
            'source_name' => $file->getClientOriginalName(),
        ]);

        try {
            $absolute = Storage::disk(config('audio.upload.disk'))->path($path);
            $this->submitAllTasks($job, function (string $task) use ($absolute) {
                return $this->magicChords->submitFile($absolute, $task);
            });
        } catch (\Throwable $e) {
            $this->failSubmitAndRefund($job, $user, $cost, $e->getMessage());
        }

        return $job->fresh();
    }

    /**
     * @param  list<string>|null  $tasks
     * @param  array{lyrics_granularity?: string}  $options
     */
    public function createFromUrl(
        User $user,
        string $url,
        ?string $kind = null,
        ?array $tasks = null,
        array $options = [],
    ): AudioJob {
        $tasks = $this->resolveTasks($kind, $tasks);
        $options = $this->normalizeOptions($options);
        $this->assertLicensed($user);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL inválida.');
        }

        $cost = $this->creditCostForTasks($tasks);
        $this->credits->consume($user, 'ai', $cost, 'audio_'.implode('_', $tasks), null, [
            'source' => 'url',
            'url' => $url,
            'tasks' => $tasks,
            'options' => $options,
        ]);

        $job = $this->makeJob($user, $tasks, $options, $cost, [
            'source_type' => 'url',
            'source_url' => $url,
            'source_name' => basename(parse_url($url, PHP_URL_PATH) ?: $url) ?: $url,
        ]);

        try {
            $this->submitAllTasks($job, function (string $task) use ($url) {
                return $this->magicChords->submitUrl($url, $task);
            });
        } catch (\Throwable $e) {
            $this->failSubmitAndRefund($job, $user, $cost, $e->getMessage());
        }

        return $job->fresh();
    }

    public function refresh(AudioJob $job): AudioJob
    {
        if ($job->status === AudioJob::STATUS_COMPLETE) {
            return $this->ensureCompletePresentation($job);
        }

        if ($job->status === AudioJob::STATUS_FAILED && empty($job->task_state)) {
            return $job;
        }

        $tasks = $job->taskList();
        $taskState = is_array($job->task_state) ? $job->task_state : [];
        $changed = false;

        foreach ($tasks as $task) {
            $state = is_array($taskState[$task] ?? null) ? $taskState[$task] : [];
            $status = $state['status'] ?? $job->status;

            if (in_array($status, [AudioJob::STATUS_COMPLETE, AudioJob::STATUS_FAILED], true)) {
                continue;
            }

            // Stems: long-running ElevenLabs call — process via deferred job / refresh, not inline on poll spam.
            if ($task === AudioJob::KIND_STEMS) {
                $status = $state['status'] ?? AudioJob::STATUS_QUEUED;
                if ($status === AudioJob::STATUS_PROCESSING && ! empty($state['started_at'])) {
                    $started = strtotime((string) $state['started_at']);
                    // Still running (afterResponse / lock held) — wait.
                    if ($started !== false && (time() - $started) < 90) {
                        continue;
                    }
                }

                $this->processStems($job);
                $job->refresh();
                $taskState = is_array($job->task_state) ? $job->task_state : $taskState;
                $changed = true;

                continue;
            }

            $externalId = $state['external_job_id'] ?? null;
            if ($externalId === null && count($tasks) === 1 && $job->external_job_id) {
                $externalId = $job->external_job_id;
            }

            if (! $externalId) {
                continue;
            }

            try {
                $providerStatus = $this->magicChords->status($externalId);
                $mapped = $this->mapStatus((string) ($providerStatus['status'] ?? 'processing'));
                $state['status'] = $mapped;
                $state['progress'] = isset($providerStatus['progress_percentage'])
                    ? (int) round((float) $providerStatus['progress_percentage'])
                    : (int) ($state['progress'] ?? 0);
                $state['message'] = $providerStatus['message'] ?? ($state['message'] ?? null);
                $state['external_job_id'] = $externalId;

                if ($mapped === AudioJob::STATUS_COMPLETE) {
                    $state['progress'] = 100;
                    $state['message'] = 'Concluído';
                    if (empty($state['provider_payload'])) {
                        $state['provider_payload'] = $this->magicChords->result($externalId);
                    }
                } elseif ($mapped === AudioJob::STATUS_FAILED) {
                    $state['error'] = $providerStatus['message'] ?? 'Job falhou no provider.';
                    $state['message'] = $state['error'];
                }

                $taskState[$task] = $state;
                $changed = true;
            } catch (\Throwable $e) {
                $state['message'] = 'Erro ao consultar provider: '.$e->getMessage();
                $taskState[$task] = $state;
                $changed = true;
            }
        }

        if ($changed || empty($job->task_state)) {
            $job->task_state = $taskState;
            $this->rollupJobStatus($job);
            $job->save();
        }

        if ($job->status === AudioJob::STATUS_COMPLETE) {
            return $this->ensureCompletePresentation($job->fresh());
        }

        return $job->fresh();
    }

    /**
     * Jobs already marked complete may still have a stale in-progress message.
     */
    private function ensureCompletePresentation(AudioJob $job): AudioJob
    {
        $needsFix = $job->progress < 100
            || $job->message === null
            || $job->message === ''
            || preg_match('/\d+\s*%/', (string) $job->message) === 1
            || preg_match('/detecting|processing|queued|submeter|analys/i', (string) $job->message) === 1;

        if (! $needsFix) {
            return $job;
        }

        $job->progress = 100;
        $job->message = 'Concluído';
        $job->save();

        return $job->fresh();
    }

    public function result(AudioJob $job): array
    {
        $job = $this->refresh($job);

        if ($job->status !== AudioJob::STATUS_COMPLETE) {
            throw new RuntimeException('O job ainda não está completo.');
        }

        $job->result = $this->buildMergedResult($job);
        $job->save();

        return $this->publicResult($job, $job->result ?? []);
    }

    /**
     * Absolute path for a stored stem file.
     */
    public function stemAbsolutePath(AudioJob $job, string $stem): string
    {
        $stem = $this->canonicalizeStemName($stem) ?? strtolower($stem);
        $taskState = is_array($job->task_state) ? $job->task_state : [];
        $stems = $taskState[AudioJob::KIND_STEMS]['stems'] ?? ($job->result['stems'] ?? []);
        if (! is_array($stems) || empty($stems[$stem]['path'])) {
            throw new RuntimeException('Stem não encontrado: '.$stem);
        }

        $disk = Storage::disk(config('audio.upload.disk', 'local'));
        $path = (string) $stems[$stem]['path'];
        if (! $disk->exists($path)) {
            throw new RuntimeException('Ficheiro do stem não encontrado: '.$stem);
        }

        return $disk->path($path);
    }

    public function freshStemUrl(AudioJob $job, string $stem): string
    {
        return URL::temporarySignedRoute(
            'music.analyze.stem',
            now()->addDays((int) config('audio.providers.elevenlabs.signed_url_days', 7)),
            ['uuid' => $job->uuid, 'stem' => $stem],
        );
    }

    /**
     * Separate audio into stems (ElevenLabs). Safe to call from queue / afterResponse / refresh.
     */
    public function processStems(AudioJob $job): AudioJob
    {
        if (! in_array(AudioJob::KIND_STEMS, $job->taskList(), true)) {
            return $job;
        }

        $lock = null;
        $acquired = true;
        try {
            $lock = \Illuminate\Support\Facades\Cache::lock('audio-stems-'.$job->id, 600);
            $acquired = $lock->get();
        } catch (\Throwable) {
            // array/cache drivers without atomic locks (e.g. tests)
            $lock = null;
            $acquired = true;
        }

        if (! $acquired) {
            return $job->fresh();
        }

        try {
            $job->refresh();
            $taskState = is_array($job->task_state) ? $job->task_state : [];
            $state = is_array($taskState[AudioJob::KIND_STEMS] ?? null) ? $taskState[AudioJob::KIND_STEMS] : [];

            if (($state['status'] ?? null) === AudioJob::STATUS_COMPLETE && ! empty($state['stems'])) {
                return $job;
            }

            try {
                $this->runStemsTask($job, $taskState);
            } catch (\Throwable $e) {
                $taskState[AudioJob::KIND_STEMS] = [
                    'status' => AudioJob::STATUS_FAILED,
                    'progress' => 0,
                    'message' => 'Falha nos stems',
                    'error' => $e->getMessage(),
                ];
            }

            $job->task_state = $taskState;
            $this->rollupJobStatus($job);
            $job->save();
        } finally {
            optional($lock)->release();
        }

        return $job->fresh();
    }

    /**
     * @param  list<string>  $tasks
     * @param  array{lyrics_granularity: string}  $options
     * @param  array<string, mixed>  $source
     */
    private function makeJob(User $user, array $tasks, array $options, int $cost, array $source): AudioJob
    {
        $taskState = [];
        foreach ($tasks as $task) {
            $taskState[$task] = [
                'status' => AudioJob::STATUS_QUEUED,
                'progress' => 0,
                'message' => 'A submeter ao provider…',
                'external_job_id' => null,
                'error' => null,
            ];
        }

        return AudioJob::create(array_merge([
            'user_id' => $user->id,
            'kind' => $this->kindLabelForTasks($tasks),
            'tasks' => $tasks,
            'task_state' => $taskState,
            'options' => $options,
            'provider' => $this->providerLabelForTasks($tasks),
            'status' => AudioJob::STATUS_QUEUED,
            'credits_spent' => $cost,
            'message' => 'A submeter ao provider…',
        ], $source));
    }

    /**
     * @param  list<string>  $tasks
     */
    private function kindLabelForTasks(array $tasks): string
    {
        if (count($tasks) === 1) {
            return $tasks[0];
        }

        $mc = array_values(array_intersect($tasks, [AudioJob::KIND_ANALYZE, AudioJob::KIND_TRANSCRIBE]));

        return count($mc) === count($tasks) ? 'both' : 'multi';
    }

    /**
     * @param  list<string>  $tasks
     */
    private function providerLabelForTasks(array $tasks): string
    {
        $hasMc = in_array(AudioJob::KIND_ANALYZE, $tasks, true)
            || in_array(AudioJob::KIND_TRANSCRIBE, $tasks, true);
        $hasStems = in_array(AudioJob::KIND_STEMS, $tasks, true);

        if ($hasMc && $hasStems) {
            return 'multi';
        }

        return $hasStems ? 'elevenlabs' : 'magic_chords';
    }

    /**
     * @param  callable(string): array<string, mixed>  $submit
     */
    private function submitAllTasks(AudioJob $job, callable $submit): void
    {
        $taskState = is_array($job->task_state) ? $job->task_state : [];
        $firstExternal = null;
        $errors = [];

        foreach ($job->taskList() as $task) {
            try {
                if ($task === AudioJob::KIND_STEMS) {
                    $taskState[$task] = [
                        'status' => AudioJob::STATUS_QUEUED,
                        'progress' => 0,
                        'message' => 'Stems na fila…',
                        'error' => null,
                    ];
                    $job->task_state = $taskState;
                    $job->save();
                    $this->scheduleStemsProcessing($job);
                    $job->refresh();
                    $taskState = is_array($job->task_state) ? $job->task_state : $taskState;

                    continue;
                }

                $external = $submit($task);
                $externalId = $external['job_id'] ?? null;
                $mapped = $this->mapStatus((string) ($external['status'] ?? 'processing'));

                $taskState[$task] = [
                    'status' => $mapped,
                    'progress' => 1,
                    'message' => $external['message'] ?? 'Em processamento no provider…',
                    'external_job_id' => $externalId,
                    'error' => null,
                ];

                if ($firstExternal === null && $externalId) {
                    $firstExternal = $externalId;
                }
            } catch (\Throwable $e) {
                $taskState[$task] = [
                    'status' => AudioJob::STATUS_FAILED,
                    'progress' => 0,
                    'message' => 'Falha ao submeter',
                    'external_job_id' => null,
                    'error' => $e->getMessage(),
                ];
                $errors[] = $task.': '.$e->getMessage();
            }
        }

        $job->task_state = $taskState;
        $job->external_job_id = $firstExternal;
        $this->rollupJobStatus($job);

        if ($errors !== [] && $this->allTasksFailed($taskState)) {
            $job->error = implode('; ', $errors);
            $job->status = AudioJob::STATUS_FAILED;
            $job->message = 'Falha ao submeter';
        }

        $job->save();

        if ($this->allTasksFailed($taskState)) {
            throw new RuntimeException($job->error ?: 'Falha ao submeter ao provider.');
        }
    }

    private function failSubmitAndRefund(AudioJob $job, User $user, int $cost, string $error): void
    {
        $job->status = AudioJob::STATUS_FAILED;
        $job->error = $error;
        $job->message = 'Falha ao submeter';
        $job->credits_spent = 0;
        $job->save();

        $this->credits->credit($user, 'ai', $cost, 'audio_refund', $job->uuid, [
            'reason' => 'provider_submit_failed',
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $taskState
     */
    private function rollupJobStatus(AudioJob $job): void
    {
        $tasks = $job->taskList();
        $taskState = is_array($job->task_state) ? $job->task_state : [];

        $statuses = [];
        $progressSum = 0;
        $messages = [];

        foreach ($tasks as $task) {
            $state = is_array($taskState[$task] ?? null) ? $taskState[$task] : [];
            $status = $state['status'] ?? AudioJob::STATUS_QUEUED;
            $statuses[] = $status;
            $progressSum += (int) ($state['progress'] ?? 0);
            if (! empty($state['message'])) {
                $messages[] = $task.': '.$state['message'];
            }
        }

        $count = max(1, count($tasks));
        $job->progress = (int) round($progressSum / $count);

        $allComplete = $statuses !== [] && count(array_filter(
            $statuses,
            fn (string $s) => $s === AudioJob::STATUS_COMPLETE
        )) === count($statuses);

        $allFailed = $this->allTasksFailed($taskState);
        $anyActive = in_array(AudioJob::STATUS_PROCESSING, $statuses, true)
            || in_array(AudioJob::STATUS_QUEUED, $statuses, true);
        $anyComplete = in_array(AudioJob::STATUS_COMPLETE, $statuses, true);

        if ($allComplete) {
            $job->status = AudioJob::STATUS_COMPLETE;
            $job->progress = 100;
            $job->message = 'Concluído';
            $job->error = null;
            if (empty($job->result) || (empty($job->result['cues']) && empty($job->result['stems']))) {
                $job->result = $this->buildMergedResult($job);
            }
        } elseif ($allFailed) {
            $job->status = AudioJob::STATUS_FAILED;
            $job->message = 'Falhou';
            $errors = [];
            foreach ($tasks as $task) {
                if (! empty($taskState[$task]['error'])) {
                    $errors[] = $task.': '.$taskState[$task]['error'];
                }
            }
            $job->error = $errors !== [] ? implode('; ', $errors) : $job->error;
        } elseif (! $anyActive && $anyComplete) {
            // Partial success: treat as complete so /result can return available cues.
            $job->status = AudioJob::STATUS_COMPLETE;
            $job->progress = 100;
            $job->message = 'Concluído (parcial)';
            $job->result = $this->buildMergedResult($job);
        } elseif ($anyActive) {
            $job->status = in_array(AudioJob::STATUS_PROCESSING, $statuses, true)
                ? AudioJob::STATUS_PROCESSING
                : AudioJob::STATUS_QUEUED;
            $job->message = $messages[0] ?? $job->message;
        }
    }

    /**
     * @param  array<string, mixed>  $taskState
     */
    private function allTasksFailed(array $taskState): bool
    {
        if ($taskState === []) {
            return false;
        }

        foreach ($taskState as $state) {
            if (! is_array($state)) {
                return false;
            }
            if (($state['status'] ?? null) !== AudioJob::STATUS_FAILED) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMergedResult(AudioJob $job): array
    {
        $taskState = is_array($job->task_state) ? $job->task_state : [];
        $tasks = $job->taskList();
        $granularity = $job->lyricsGranularity();
        $providerPayloads = [];
        $allCues = [];
        $bpm = null;
        $key = null;
        $durationMs = null;
        $stems = [];

        foreach ($tasks as $task) {
            $state = is_array($taskState[$task] ?? null) ? $taskState[$task] : [];

            if ($task === AudioJob::KIND_STEMS) {
                if (is_array($state['stems'] ?? null)) {
                    $stems = $state['stems'];
                }

                continue;
            }

            $raw = $state['provider_payload'] ?? null;

            // Legacy single-task: payload may live only on job.result
            if (! is_array($raw) && count($tasks) === 1 && is_array($job->result['provider_payload'] ?? null)) {
                $raw = $job->result['provider_payload'];
            }

            if (! is_array($raw)) {
                $externalId = $state['external_job_id'] ?? $job->external_job_id;
                if ($externalId && ($state['status'] ?? null) === AudioJob::STATUS_COMPLETE) {
                    try {
                        $raw = $this->magicChords->result($externalId);
                        $state['provider_payload'] = $raw;
                        $taskState[$task] = $state;
                    } catch (\Throwable) {
                        continue;
                    }
                } else {
                    continue;
                }
            }

            $providerPayloads[$task] = $raw;

            $normalized = $this->normalizer->normalize(
                'magic_chords',
                $raw,
                $task,
                $granularity,
            );

            foreach ($normalized['cues'] as $cue) {
                $allCues[] = $cue;
            }

            if ($bpm === null && isset($normalized['meta']['bpm'])) {
                $bpm = $normalized['meta']['bpm'];
            }
            if ($key === null && isset($normalized['meta']['key'])) {
                $key = $normalized['meta']['key'];
            }
            if (isset($normalized['meta']['durationMs'])) {
                $durationMs = max((int) $durationMs, (int) $normalized['meta']['durationMs']);
            }
        }

        $job->task_state = $taskState;

        usort($allCues, fn (array $a, array $b) => $a['timeMs'] <=> $b['timeMs']);

        return [
            'format' => 1,
            'cues' => array_values($allCues),
            'stems' => $stems,
            'meta' => [
                'provider' => $job->provider ?: 'magic_chords',
                'kind' => $this->kindLabelForTasks($tasks),
                'tasks' => $tasks,
                'lyrics_granularity' => $granularity,
                'bpm' => $bpm,
                'key' => $key,
                'durationMs' => $durationMs ?: null,
                'separate_stems' => $job->options['separate_stems'] ?? [],
            ],
            'provider_payload' => count($providerPayloads) === 1
                ? reset($providerPayloads)
                : $providerPayloads,
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{format: int, cues: list<array<string, mixed>>, stems: array<string, mixed>, meta: array<string, mixed>}
     */
    private function publicResult(AudioJob $job, array $stored): array
    {
        $granularity = $job->lyricsGranularity();
        $tasks = $job->taskList();
        $stemsOut = $this->publicStems($job, is_array($stored['stems'] ?? null) ? $stored['stems'] : []);

        // Remap from stored provider payload(s) so granularity changes apply.
        if (! empty($stored['provider_payload'])) {
            $payloads = $stored['provider_payload'];

            // Multi-task: { analyze: {...}, transcribe: {...} }
            if (is_array($payloads) && $this->looksLikeTaskPayloadMap($payloads, $tasks)) {
                $allCues = [];
                $bpm = null;
                $key = null;
                $durationMs = null;

                foreach ($tasks as $task) {
                    if ($task === AudioJob::KIND_STEMS) {
                        continue;
                    }
                    if (empty($payloads[$task]) || ! is_array($payloads[$task])) {
                        continue;
                    }
                    $normalized = $this->normalizer->normalize(
                        'magic_chords',
                        $payloads[$task],
                        $task,
                        $granularity,
                    );
                    foreach ($normalized['cues'] as $cue) {
                        $allCues[] = $cue;
                    }
                    $bpm ??= $normalized['meta']['bpm'] ?? null;
                    $key ??= $normalized['meta']['key'] ?? null;
                    if (isset($normalized['meta']['durationMs'])) {
                        $durationMs = max((int) $durationMs, (int) $normalized['meta']['durationMs']);
                    }
                }

                usort($allCues, fn (array $a, array $b) => $a['timeMs'] <=> $b['timeMs']);

                return [
                    'format' => 1,
                    'cues' => array_values($allCues),
                    'stems' => $stemsOut,
                    'meta' => [
                        'provider' => $job->provider ?: 'magic_chords',
                        'kind' => $this->kindLabelForTasks($tasks),
                        'tasks' => $tasks,
                        'lyrics_granularity' => $granularity,
                        'bpm' => $bpm,
                        'key' => $key,
                        'durationMs' => $durationMs ?: null,
                        'separate_stems' => $job->options['separate_stems'] ?? [],
                    ],
                ];
            }

            if (is_array($payloads) && ! $this->looksLikeTaskPayloadMap($payloads, $tasks)) {
                $mcTask = null;
                if (in_array(AudioJob::KIND_ANALYZE, $tasks, true)) {
                    $mcTask = AudioJob::KIND_ANALYZE;
                } elseif (in_array(AudioJob::KIND_TRANSCRIBE, $tasks, true)) {
                    $mcTask = AudioJob::KIND_TRANSCRIBE;
                }

                if ($mcTask !== null) {
                    $normalized = $this->normalizer->normalize(
                        'magic_chords',
                        $payloads,
                        $mcTask,
                        $granularity,
                    );

                    return [
                        'format' => $normalized['format'],
                        'cues' => $normalized['cues'],
                        'stems' => $stemsOut,
                        'meta' => array_merge($normalized['meta'], [
                            'tasks' => $tasks,
                            'lyrics_granularity' => $granularity,
                            'kind' => $this->kindLabelForTasks($tasks),
                            'separate_stems' => $job->options['separate_stems'] ?? [],
                        ]),
                    ];
                }
            }
        }

        if ((! isset($stored['cues']) || ! is_array($stored['cues'])) && $stemsOut === []) {
            $normalized = $this->normalizer->normalize(
                'magic_chords',
                $stored,
                in_array(AudioJob::KIND_STEMS, $tasks, true) && count($tasks) === 1 ? null : ($tasks[0] ?? null),
                $granularity,
            );

            return [
                'format' => $normalized['format'],
                'cues' => $normalized['cues'],
                'stems' => $stemsOut,
                'meta' => array_merge($normalized['meta'], [
                    'tasks' => $tasks,
                    'lyrics_granularity' => $granularity,
                    'separate_stems' => $job->options['separate_stems'] ?? [],
                ]),
            ];
        }

        $meta = is_array($stored['meta'] ?? null) ? $stored['meta'] : [];
        $meta['tasks'] = $tasks;
        $meta['lyrics_granularity'] = $granularity;
        $meta['separate_stems'] = $job->options['separate_stems'] ?? [];

        return [
            'format' => (int) ($stored['format'] ?? 1),
            'cues' => array_values(is_array($stored['cues'] ?? null) ? $stored['cues'] : []),
            'stems' => $stemsOut,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $stems
     * @return array<string, array{url: string, content_type: string, bytes?: int}>
     */
    private function publicStems(AudioJob $job, array $stems): array
    {
        $out = [];
        foreach ($stems as $name => $meta) {
            if (! is_string($name) || ! is_array($meta) || empty($meta['path'])) {
                continue;
            }
            $out[$name] = [
                'url' => $this->freshStemUrl($job, $name),
                'content_type' => $meta['content_type'] ?? 'audio/mpeg',
                'bytes' => isset($meta['bytes']) ? (int) $meta['bytes'] : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payloads
     * @param  list<string>  $tasks
     */
    private function looksLikeTaskPayloadMap(array $payloads, array $tasks): bool
    {
        foreach ($tasks as $task) {
            if (array_key_exists($task, $payloads)) {
                return true;
            }
        }

        return false;
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'complete', 'completed', 'done', 'ready' => AudioJob::STATUS_COMPLETE,
            'failed', 'error' => AudioJob::STATUS_FAILED,
            'queued', 'pending' => AudioJob::STATUS_QUEUED,
            default => AudioJob::STATUS_PROCESSING,
        };
    }

    /**
     * @param  list<string>|null  $tasks
     * @return list<string>
     */
    private function resolveTasks(?string $kind, ?array $tasks): array
    {
        $allowed = [AudioJob::KIND_ANALYZE, AudioJob::KIND_TRANSCRIBE, AudioJob::KIND_STEMS];

        if (is_array($tasks) && $tasks !== []) {
            $resolved = [];
            foreach ($tasks as $task) {
                if (! is_string($task)) {
                    continue;
                }
                $task = strtolower(trim($task));
                if ($task === 'both') {
                    $resolved[] = AudioJob::KIND_ANALYZE;
                    $resolved[] = AudioJob::KIND_TRANSCRIBE;

                    continue;
                }
                if (! in_array($task, $allowed, true)) {
                    throw new InvalidArgumentException('tasks inválidas. Use analyze, transcribe e/ou stems.');
                }
                $resolved[] = $task;
            }

            $resolved = array_values(array_unique($resolved));
            if ($resolved === []) {
                throw new InvalidArgumentException('tasks inválidas. Use analyze, transcribe e/ou stems.');
            }

            return $resolved;
        }

        $kind = strtolower(trim((string) ($kind ?: AudioJob::KIND_ANALYZE)));
        if ($kind === 'both') {
            return [AudioJob::KIND_ANALYZE, AudioJob::KIND_TRANSCRIBE];
        }

        if (! in_array($kind, $allowed, true)) {
            throw new InvalidArgumentException('kind inválido. Use analyze, transcribe, stems ou both.');
        }

        return [$kind];
    }

    /**
     * @param  array<string, mixed>  $taskState
     */
    private function runStemsTask(AudioJob $job, array &$taskState): void
    {
        if (! filled(config('audio.providers.elevenlabs.api_key'))) {
            throw new RuntimeException('ELEVENLABS_API_KEY não está configurada.');
        }

        // Stem separation often exceeds PHP's default 60s limit.
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }
        @ini_set('max_execution_time', '600');

        $taskState[AudioJob::KIND_STEMS] = [
            'status' => AudioJob::STATUS_PROCESSING,
            'progress' => 20,
            'message' => 'A separar stems no ElevenLabs…',
            'error' => null,
            'started_at' => now()->toIso8601String(),
        ];
        $job->task_state = $taskState;
        $job->status = AudioJob::STATUS_PROCESSING;
        $job->message = 'A separar stems no ElevenLabs…';
        $job->save();

        $absolute = $this->resolveLocalAudioPath($job);
        $requested = $this->normalizeRequestedStems($job->options['separate_stems'] ?? []);
        $variation = $this->pickStemVariation($requested);

        $separated = $this->elevenLabs->separateStems([
            'absolute_path' => $absolute,
            'filename' => $job->displayName() ?: 'audio.mp3',
            'stem_variation_id' => $variation,
        ]);

        $extracted = $this->extractStemZip($job, $separated['bytes'], $requested);

        $taskState[AudioJob::KIND_STEMS] = [
            'status' => AudioJob::STATUS_COMPLETE,
            'progress' => 100,
            'message' => 'Concluído',
            'error' => null,
            'stem_variation_id' => $variation,
            'stems' => $extracted,
        ];
        $job->task_state = $taskState;
    }

    private function scheduleStemsProcessing(AudioJob $job): void
    {
        // Unit/feature tests and explicit sync mode run inline (with raised time limit).
        if (app()->runningUnitTests() || ! config('audio.providers.elevenlabs.stems_defer', true)) {
            $this->processStems($job);

            return;
        }

        $jobId = $job->id;
        dispatch(function () use ($jobId) {
            $job = AudioJob::query()->find($jobId);
            if ($job) {
                app(AudioJobService::class)->processStems($job);
            }
        })->afterResponse();
    }

    /**
     * Download URL / use uploaded file so ElevenLabs can receive a local path.
     */
    private function resolveLocalAudioPath(AudioJob $job): string
    {
        $disk = Storage::disk(config('audio.upload.disk', 'local'));

        if ($job->source_type === 'file' && filled($job->source_path) && $disk->exists($job->source_path)) {
            return $disk->path($job->source_path);
        }

        if ($job->source_type === 'url' && filled($job->source_url)) {
            $response = Http::timeout(120)->get((string) $job->source_url);
            if (! $response->successful() || $response->body() === '') {
                throw new RuntimeException('Não foi possível descarregar o áudio para stems.');
            }

            $ext = pathinfo(parse_url((string) $job->source_url, PHP_URL_PATH) ?: 'audio.mp3', PATHINFO_EXTENSION) ?: 'mp3';
            $relative = 'audio_uploads/'.$job->user_id.'/stems_src_'.$job->uuid.'.'.$ext;
            $disk->put($relative, $response->body());
            if (! filled($job->source_path)) {
                $job->source_path = $relative;
                $job->save();
            }

            return $disk->path($relative);
        }

        throw new RuntimeException('Fonte de áudio indisponível para stems.');
    }

    /**
     * @param  list<string>  $requested
     * @return array<string, array{path: string, content_type: string, bytes: int}>
     */
    private function extractStemZip(AudioJob $job, string $zipBytes, array $requested): array
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'stems_').'.zip';
        file_put_contents($tmpZip, $zipBytes);

        $zip = new ZipArchive;
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);
            throw new RuntimeException('ZIP de stems inválido.');
        }

        $disk = Storage::disk(config('audio.upload.disk', 'local'));
        $found = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }

            $canonical = $this->classifyStemFilename($name);
            if ($canonical === null) {
                continue;
            }

            if ($requested !== [] && ! in_array($canonical, $requested, true)) {
                continue;
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false || $contents === '') {
                continue;
            }

            $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'mp3';
            $relative = 'music_stems/'.$job->user_id.'/'.$job->uuid.'/'.$canonical.'.'.$ext;
            $disk->put($relative, $contents);
            $found[$canonical] = [
                'path' => $relative,
                'content_type' => $ext === 'wav' ? 'audio/wav' : 'audio/mpeg',
                'bytes' => strlen($contents),
            ];
        }

        $zip->close();
        @unlink($tmpZip);

        if ($found === []) {
            throw new RuntimeException('Nenhum stem reconhecido no ZIP do ElevenLabs.');
        }

        return $found;
    }

    private function classifyStemFilename(string $filename): ?string
    {
        $base = strtolower(pathinfo(str_replace('\\', '/', $filename), PATHINFO_FILENAME));
        $base = basename($base);

        $aliases = config('audio.stems.aliases', []);
        $allowed = config('audio.stems.allowed', []);

        foreach ($aliases as $alias => $canonical) {
            if (str_contains($base, (string) $alias)) {
                return (string) $canonical;
            }
        }

        foreach ($allowed as $name) {
            if (str_contains($base, (string) $name)) {
                return (string) $name;
            }
        }

        return null;
    }

    private function canonicalizeStemName(string $name): ?string
    {
        $name = strtolower(trim($name));
        $aliases = config('audio.stems.aliases', []);
        if (isset($aliases[$name])) {
            return (string) $aliases[$name];
        }

        $allowed = config('audio.stems.allowed', []);

        return in_array($name, $allowed, true) ? $name : null;
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function normalizeRequestedStems(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $canonical = $this->canonicalizeStemName($item);
            if ($canonical !== null) {
                $out[] = $canonical;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $requested
     */
    private function pickStemVariation(array $requested): string
    {
        $onlyTwo = $requested !== []
            && count(array_diff($requested, ['vocals', 'instrumental'])) === 0;

        if ($onlyTwo) {
            return 'two_stems_v1';
        }

        return (string) config('audio.providers.elevenlabs.stem_variation_id', 'six_stems_v1');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{lyrics_granularity: string, chords?: bool, lyrics?: bool, separate_stems?: list<string>}
     */
    private function normalizeOptions(array $options): array
    {
        $granularity = strtolower((string) ($options['lyrics_granularity'] ?? AudioJob::GRANULARITY_PHRASE));
        if (! in_array($granularity, [AudioJob::GRANULARITY_PHRASE, AudioJob::GRANULARITY_WORD], true)) {
            throw new InvalidArgumentException('lyrics_granularity inválida. Use phrase ou word.');
        }

        $normalized = [
            'lyrics_granularity' => $granularity,
        ];

        if (array_key_exists('chords', $options)) {
            $normalized['chords'] = (bool) $options['chords'];
        }
        if (array_key_exists('lyrics', $options)) {
            $normalized['lyrics'] = (bool) $options['lyrics'];
        }
        if (isset($options['separate_stems']) && is_array($options['separate_stems'])) {
            $normalized['separate_stems'] = $this->normalizeRequestedStems($options['separate_stems']);
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $tasks
     */
    private function creditCostForTasks(array $tasks): int
    {
        $total = 0;
        foreach ($tasks as $task) {
            $total += max(1, (int) config("audio.credit_cost.{$task}", 1));
        }

        return max(1, $total);
    }

    private function assertLicensed(User $user): void
    {
        if (! $user->hasValidLicense()) {
            throw new RuntimeException('Licença inválida. Activa uma licença antes de usar análise de áudio.');
        }
    }
}
