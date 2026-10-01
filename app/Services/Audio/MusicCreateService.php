<?php

namespace App\Services\Audio;

use App\Models\AudioJob;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use RuntimeException;

class MusicCreateService
{
    public const KIND_CREATE = 'create';

    public function __construct(
        private ElevenLabsClient $elevenLabs,
        private CreditService $credits,
    ) {}

    /**
     * @param  array{prompt: string, duration?: int|float|null, duration_ms?: int|null, force_instrumental?: bool, model_id?: string}  $input
     */
    public function create(User $user, array $input): AudioJob
    {
        $this->assertLicensed($user);

        if (! filled(config('audio.providers.elevenlabs.api_key'))) {
            throw new RuntimeException('ELEVENLABS_API_KEY não está configurada.');
        }

        $prompt = trim((string) ($input['prompt'] ?? ''));
        if ($prompt === '') {
            throw new InvalidArgumentException('prompt é obrigatório.');
        }
        if (mb_strlen($prompt) > 4000) {
            throw new InvalidArgumentException('prompt demasiado longo (máx. 4000 caracteres).');
        }

        $durationMs = $this->resolveDurationMs($input);
        $forceInstrumental = (bool) ($input['force_instrumental'] ?? false);
        $modelId = isset($input['model_id']) ? (string) $input['model_id'] : null;

        $cost = max(1, (int) config('audio.credit_cost.create', 2));
        $this->credits->consume($user, 'ai', $cost, 'music_create', null, [
            'prompt' => mb_substr($prompt, 0, 120),
            'duration_ms' => $durationMs,
        ]);

        $options = array_filter([
            'prompt' => $prompt,
            'duration_ms' => $durationMs,
            'force_instrumental' => $forceInstrumental,
            'model_id' => $modelId,
        ], fn ($v) => $v !== null);

        $job = AudioJob::create([
            'user_id' => $user->id,
            'kind' => self::KIND_CREATE,
            'tasks' => [self::KIND_CREATE],
            'task_state' => [
                self::KIND_CREATE => [
                    'status' => AudioJob::STATUS_QUEUED,
                    'progress' => 0,
                    'message' => 'Na fila para gerar…',
                    'error' => null,
                ],
            ],
            'options' => $options,
            'provider' => 'elevenlabs',
            'status' => AudioJob::STATUS_QUEUED,
            'source_type' => 'prompt',
            'source_name' => mb_substr($prompt, 0, 80),
            'credits_spent' => $cost,
            'message' => 'Na fila para gerar…',
            'progress' => 0,
        ]);

        if (config('audio.providers.elevenlabs.process_sync', true)) {
            $this->process($job);
        } else {
            \App\Jobs\ProcessMusicCreateJob::dispatch($job->id);
        }

        return $job->fresh();
    }

    public function process(AudioJob $job): AudioJob
    {
        if ($job->kind !== self::KIND_CREATE) {
            throw new RuntimeException('Job não é music/create.');
        }

        if ($job->status === AudioJob::STATUS_COMPLETE && ! empty($job->result['audio_path'])) {
            return $job;
        }

        $job->status = AudioJob::STATUS_PROCESSING;
        $job->progress = 10;
        $job->message = 'A gerar música no ElevenLabs…';
        $job->task_state = [
            self::KIND_CREATE => [
                'status' => AudioJob::STATUS_PROCESSING,
                'progress' => 10,
                'message' => $job->message,
                'error' => null,
            ],
        ];
        $job->save();

        try {
            $options = is_array($job->options) ? $job->options : [];
            $composed = $this->elevenLabs->composeMusic([
                'prompt' => (string) ($options['prompt'] ?? ''),
                'music_length_ms' => isset($options['duration_ms']) ? (int) $options['duration_ms'] : null,
                'force_instrumental' => (bool) ($options['force_instrumental'] ?? false),
                'model_id' => $options['model_id'] ?? null,
            ]);

            $ext = str_contains((string) $composed['output_format'], 'pcm') ? 'wav' : 'mp3';
            $relative = 'music_creates/'.$job->user_id.'/'.$job->uuid.'.'.$ext;
            Storage::disk(config('audio.upload.disk', 'local'))->put($relative, $composed['bytes']);

            $audioUrl = URL::temporarySignedRoute(
                'music.create.audio',
                now()->addDays((int) config('audio.providers.elevenlabs.signed_url_days', 7)),
                ['uuid' => $job->uuid],
            );

            $job->source_path = $relative;
            $job->external_job_id = $composed['song_id'];
            $job->status = AudioJob::STATUS_COMPLETE;
            $job->progress = 100;
            $job->message = 'Concluído';
            $job->error = null;
            $job->result = [
                'provider' => 'elevenlabs',
                'kind' => self::KIND_CREATE,
                'audio_path' => $relative,
                'audio_url' => $audioUrl,
                'content_type' => $composed['content_type'],
                'output_format' => $composed['output_format'],
                'song_id' => $composed['song_id'],
                'duration_ms' => $options['duration_ms'] ?? null,
                'prompt' => $options['prompt'] ?? null,
                'bytes' => strlen($composed['bytes']),
            ];
            $job->task_state = [
                self::KIND_CREATE => [
                    'status' => AudioJob::STATUS_COMPLETE,
                    'progress' => 100,
                    'message' => 'Concluído',
                    'error' => null,
                ],
            ];
            $job->save();
        } catch (\Throwable $e) {
            $this->markFailedAndRefund($job, $e->getMessage());
        }

        return $job->fresh();
    }

    public function refresh(AudioJob $job): AudioJob
    {
        if ($job->status === AudioJob::STATUS_QUEUED && config('audio.providers.elevenlabs.process_sync', true) === false) {
            // Queue worker will pick it up; nothing to poll externally.
            return $job;
        }

        if ($job->status === AudioJob::STATUS_COMPLETE) {
            // Refresh signed URL if we still have the file.
            if (! empty($job->result['audio_path']) && empty($job->result['audio_url'])) {
                $result = $job->result;
                $result['audio_url'] = $this->freshAudioUrl($job);
                $job->result = $result;
                $job->save();
            }

            return $job->fresh();
        }

        return $job;
    }

    /**
     * @return array<string, mixed>
     */
    public function result(AudioJob $job): array
    {
        $job = $this->refresh($job);

        if ($job->status !== AudioJob::STATUS_COMPLETE) {
            throw new RuntimeException('O job ainda não está completo.');
        }

        $stored = is_array($job->result) ? $job->result : [];
        $audioUrl = $this->freshAudioUrl($job);

        return [
            'provider' => 'elevenlabs',
            'kind' => self::KIND_CREATE,
            'audio_url' => $audioUrl,
            'content_type' => $stored['content_type'] ?? 'audio/mpeg',
            'output_format' => $stored['output_format'] ?? null,
            'song_id' => $stored['song_id'] ?? $job->external_job_id,
            'duration_ms' => $stored['duration_ms'] ?? ($job->options['duration_ms'] ?? null),
            'bytes' => $stored['bytes'] ?? null,
            'prompt' => $stored['prompt'] ?? ($job->options['prompt'] ?? null),
            'options' => [
                'duration_ms' => $job->options['duration_ms'] ?? null,
                'force_instrumental' => (bool) ($job->options['force_instrumental'] ?? false),
            ],
        ];
    }

    public function audioAbsolutePath(AudioJob $job): string
    {
        $path = $job->source_path ?: ($job->result['audio_path'] ?? null);
        if (! $path) {
            throw new RuntimeException('Áudio ainda não disponível.');
        }

        $disk = Storage::disk(config('audio.upload.disk', 'local'));
        if (! $disk->exists($path)) {
            throw new RuntimeException('Ficheiro de áudio não encontrado.');
        }

        return $disk->path($path);
    }

    public function freshAudioUrl(AudioJob $job): string
    {
        return URL::temporarySignedRoute(
            'music.create.audio',
            now()->addDays((int) config('audio.providers.elevenlabs.signed_url_days', 7)),
            ['uuid' => $job->uuid],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveDurationMs(array $input): ?int
    {
        if (isset($input['duration_ms']) && is_numeric($input['duration_ms'])) {
            $ms = (int) round((float) $input['duration_ms']);
        } elseif (isset($input['duration']) && is_numeric($input['duration'])) {
            // Treat as seconds (colleague's diagram: duration NUMBER).
            $ms = (int) round((float) $input['duration'] * 1000);
        } else {
            return null;
        }

        if ($ms < 3000 || $ms > 600000) {
            throw new InvalidArgumentException('duration deve estar entre 3 e 600 segundos (ou duration_ms 3000–600000).');
        }

        return $ms;
    }

    private function markFailedAndRefund(AudioJob $job, string $error): void
    {
        $cost = (int) $job->credits_spent;
        $job->status = AudioJob::STATUS_FAILED;
        $job->error = $error;
        $job->message = 'Falha ao gerar';
        $job->progress = 0;
        $job->task_state = [
            self::KIND_CREATE => [
                'status' => AudioJob::STATUS_FAILED,
                'progress' => 0,
                'message' => 'Falha ao gerar',
                'error' => $error,
            ],
        ];

        if ($cost > 0 && $job->user) {
            $this->credits->credit($job->user, 'ai', $cost, 'music_create_refund', $job->uuid, [
                'reason' => 'provider_create_failed',
            ]);
            $job->credits_spent = 0;
        }

        $job->save();
    }

    private function assertLicensed(User $user): void
    {
        if (! $user->hasValidLicense()) {
            throw new RuntimeException('Licença inválida. Activa uma licença antes de gerar música.');
        }
    }
}
