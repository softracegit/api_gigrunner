<?php

namespace App\Services\Audio;

use App\Models\AudioJob;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class AudioJobService
{
    public function __construct(
        private MagicChordsClient $magicChords,
        private CreditService $credits,
        private SongResultNormalizer $normalizer,
    ) {}

    public function createFromUpload(User $user, UploadedFile $file, string $kind): AudioJob
    {
        $this->assertKind($kind);
        $this->assertLicensed($user);

        $cost = $this->creditCost($kind);
        $this->credits->consume($user, 'ai', $cost, 'audio_'.$kind, null, [
            'source' => 'upload',
            'filename' => $file->getClientOriginalName(),
        ]);

        $path = $file->store('audio_uploads/'.$user->id, config('audio.upload.disk'));

        $job = AudioJob::create([
            'user_id' => $user->id,
            'kind' => $kind,
            'provider' => 'magic_chords',
            'status' => AudioJob::STATUS_QUEUED,
            'source_type' => 'file',
            'source_path' => $path,
            'source_name' => $file->getClientOriginalName(),
            'credits_spent' => $cost,
            'message' => 'A submeter ao provider…',
        ]);

        try {
            $absolute = Storage::disk(config('audio.upload.disk'))->path($path);
            $external = $this->magicChords->submitFile($absolute, $kind);
            $this->applyExternalSubmission($job, $external);
        } catch (\Throwable $e) {
            $this->markFailed($job, $e->getMessage());
            // Best-effort refund
            $this->credits->credit($user, 'ai', $cost, 'audio_refund', $job->uuid, [
                'reason' => 'provider_submit_failed',
            ]);
            $job->credits_spent = 0;
            $job->save();
        }

        return $job->fresh();
    }

    public function createFromUrl(User $user, string $url, string $kind): AudioJob
    {
        $this->assertKind($kind);
        $this->assertLicensed($user);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL inválida.');
        }

        $cost = $this->creditCost($kind);
        $this->credits->consume($user, 'ai', $cost, 'audio_'.$kind, null, [
            'source' => 'url',
            'url' => $url,
        ]);

        $job = AudioJob::create([
            'user_id' => $user->id,
            'kind' => $kind,
            'provider' => 'magic_chords',
            'status' => AudioJob::STATUS_QUEUED,
            'source_type' => 'url',
            'source_url' => $url,
            'source_name' => basename(parse_url($url, PHP_URL_PATH) ?: $url) ?: $url,
            'credits_spent' => $cost,
            'message' => 'A submeter ao provider…',
        ]);

        try {
            $external = $this->magicChords->submitUrl($url, $kind);
            $this->applyExternalSubmission($job, $external);
        } catch (\Throwable $e) {
            $this->markFailed($job, $e->getMessage());
            $this->credits->credit($user, 'ai', $cost, 'audio_refund', $job->uuid, [
                'reason' => 'provider_submit_failed',
            ]);
            $job->credits_spent = 0;
            $job->save();
        }

        return $job->fresh();
    }

    public function refresh(AudioJob $job): AudioJob
    {
        if ($job->status === AudioJob::STATUS_COMPLETE) {
            return $this->ensureCompletePresentation($job);
        }

        if ($job->status === AudioJob::STATUS_FAILED) {
            return $job;
        }

        if (! $job->external_job_id) {
            return $job;
        }

        try {
            $status = $this->magicChords->status($job->external_job_id);
            $mapped = $this->mapStatus((string) ($status['status'] ?? 'processing'));
            $job->status = $mapped;
            $job->progress = isset($status['progress_percentage'])
                ? (int) round((float) $status['progress_percentage'])
                : $job->progress;

            if ($mapped === AudioJob::STATUS_COMPLETE) {
                if (empty($job->result)) {
                    $raw = $this->magicChords->result($job->external_job_id);
                    $job->result = $this->normalizedResult($job, $raw);
                }
                $job->progress = 100;
                $job->message = 'Concluído';
            } elseif ($mapped === AudioJob::STATUS_FAILED) {
                $job->error = $status['message'] ?? 'Job falhou no provider.';
                $job->message = $status['message'] ?? 'Falhou';
            } else {
                $job->message = $status['message'] ?? $job->message;
            }

            $job->save();
        } catch (\Throwable $e) {
            $job->message = 'Erro ao consultar provider: '.$e->getMessage();
            $job->save();
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

        if (empty($job->result) && $job->external_job_id) {
            $raw = $this->magicChords->result($job->external_job_id);
            $job->result = $this->normalizedResult($job, $raw);
            $job->save();
        }

        return $this->publicResult(
            $job->result ?? [],
            $job->provider,
            $job->kind,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizedResult(AudioJob $job, array $raw): array
    {
        $normalized = $this->normalizer->normalize(
            $job->provider ?: 'magic_chords',
            $raw,
            $job->kind,
        );

        // Keep raw payload for debugging / future remapping; not exposed by default.
        $normalized['provider_payload'] = $raw;

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{format: int, cues: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function publicResult(array $stored, ?string $provider = null, ?string $kind = null): array
    {
        // Prefer remapping from raw provider payload (e.g. regroup words → phrases).
        if (! empty($stored['provider_payload']) && is_array($stored['provider_payload'])) {
            $normalized = $this->normalizer->normalize(
                $provider ?: (string) ($stored['meta']['provider'] ?? 'magic_chords'),
                $stored['provider_payload'],
                $kind ?? ($stored['meta']['kind'] ?? null),
            );

            return [
                'format' => $normalized['format'],
                'cues' => $normalized['cues'],
                'meta' => $normalized['meta'],
            ];
        }

        // Legacy rows: raw Magic Chords payload without cues — normalize on the fly.
        if (! isset($stored['cues']) || ! is_array($stored['cues'])) {
            $normalized = $this->normalizer->normalize(
                $provider ?: 'magic_chords',
                $stored,
                $kind,
            );

            return [
                'format' => $normalized['format'],
                'cues' => $normalized['cues'],
                'meta' => $normalized['meta'],
            ];
        }

        return [
            'format' => (int) ($stored['format'] ?? 1),
            'cues' => array_values($stored['cues']),
            'meta' => is_array($stored['meta'] ?? null) ? $stored['meta'] : [],
        ];
    }

    private function applyExternalSubmission(AudioJob $job, array $external): void
    {
        $job->external_job_id = $external['job_id'] ?? null;
        $job->status = $this->mapStatus((string) ($external['status'] ?? 'processing'));
        $job->message = $external['message'] ?? 'Em processamento no provider…';
        $job->progress = 1;
        $job->save();
    }

    private function markFailed(AudioJob $job, string $error): void
    {
        $job->status = AudioJob::STATUS_FAILED;
        $job->error = $error;
        $job->message = 'Falha ao submeter';
        $job->save();
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

    private function creditCost(string $kind): int
    {
        return max(1, (int) config("audio.credit_cost.{$kind}", 1));
    }

    private function assertKind(string $kind): void
    {
        if (! in_array($kind, [AudioJob::KIND_ANALYZE, AudioJob::KIND_TRANSCRIBE], true)) {
            throw new InvalidArgumentException('kind inválido. Use analyze ou transcribe.');
        }
    }

    private function assertLicensed(User $user): void
    {
        if (! $user->hasValidLicense()) {
            throw new RuntimeException('Licença inválida. Activa uma licença antes de usar análise de áudio.');
        }
    }
}
