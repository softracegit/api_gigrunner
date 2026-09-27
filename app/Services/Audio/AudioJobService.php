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
        if (in_array($job->status, [AudioJob::STATUS_COMPLETE, AudioJob::STATUS_FAILED], true)) {
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
            $job->message = $status['message'] ?? $job->message;

            if ($mapped === AudioJob::STATUS_COMPLETE && empty($job->result)) {
                $job->result = $this->magicChords->result($job->external_job_id);
                $job->progress = 100;
                $job->message = $job->message ?: 'Concluído';
            }

            if ($mapped === AudioJob::STATUS_FAILED) {
                $job->error = $status['message'] ?? 'Job falhou no provider.';
            }

            $job->save();
        } catch (\Throwable $e) {
            $job->message = 'Erro ao consultar provider: '.$e->getMessage();
            $job->save();
        }

        return $job->fresh();
    }

    public function result(AudioJob $job): array
    {
        $job = $this->refresh($job);

        if ($job->status !== AudioJob::STATUS_COMPLETE) {
            throw new RuntimeException('O job ainda não está completo.');
        }

        if (empty($job->result) && $job->external_job_id) {
            $job->result = $this->magicChords->result($job->external_job_id);
            $job->save();
        }

        return $job->result ?? [];
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
