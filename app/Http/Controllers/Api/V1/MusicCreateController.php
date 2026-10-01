<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AudioJob;
use App\Services\Audio\MusicCreateService;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MusicCreateController extends Controller
{
    public function __construct(
        private MusicCreateService $creates,
        private CreditService $credits,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'min:1', 'max:4000'],
            'duration' => ['sometimes', 'numeric', 'min:3', 'max:600'],
            'duration_ms' => ['sometimes', 'integer', 'min:3000', 'max:600000'],
            'force_instrumental' => ['sometimes', 'boolean'],
            'model_id' => ['sometimes', 'string', 'in:music_v1,music_v2,music_v2_5'],
            'options' => ['sometimes', 'array'],
            'options.duration' => ['sometimes', 'numeric', 'min:3', 'max:600'],
            'options.duration_ms' => ['sometimes', 'integer', 'min:3000', 'max:600000'],
            'options.force_instrumental' => ['sometimes', 'boolean'],
            'options.model_id' => ['sometimes', 'string', 'in:music_v1,music_v2,music_v2_5'],
        ]);

        $options = is_array($data['options'] ?? null) ? $data['options'] : [];

        try {
            $job = $this->creates->create($request->user(), [
                'prompt' => $data['prompt'],
                'duration' => $data['duration'] ?? $options['duration'] ?? null,
                'duration_ms' => $data['duration_ms'] ?? $options['duration_ms'] ?? null,
                'force_instrumental' => $data['force_instrumental']
                    ?? $options['force_instrumental']
                    ?? false,
                'model_id' => $data['model_id'] ?? $options['model_id'] ?? null,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'créditos') ? 402 : 403;
            if (str_contains($e->getMessage(), 'ELEVENLABS_API_KEY')) {
                $status = 503;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'credits' => $this->credits->balances($request->user()),
            ], $status);
        }

        return response()->json([
            'job' => $this->toMusicJobArray($job),
            'credits' => $this->credits->balances($request->user()),
        ], 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $job = $this->findOwnedCreateJob($request, $uuid);
        $job = $this->creates->refresh($job);

        return response()->json([
            'job' => $this->toMusicJobArray($job),
        ]);
    }

    public function result(Request $request, string $uuid): JsonResponse
    {
        $job = $this->findOwnedCreateJob($request, $uuid);

        try {
            $result = $this->creates->result($job);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'job' => $this->toMusicJobArray($job->fresh()),
            ], 409);
        }

        return response()->json([
            'job' => $this->toMusicJobArray($job->fresh()),
            'result' => $result,
        ]);
    }

    /**
     * Public (signed) download so providers / the app can fetch the generated file by URL.
     */
    public function audio(Request $request, string $uuid): BinaryFileResponse
    {
        $job = AudioJob::query()
            ->where('uuid', $uuid)
            ->where('kind', MusicCreateService::KIND_CREATE)
            ->firstOrFail();

        if ($job->status !== AudioJob::STATUS_COMPLETE) {
            abort(409, 'Áudio ainda não disponível.');
        }

        try {
            $absolute = $this->creates->audioAbsolutePath($job);
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        $contentType = $job->result['content_type'] ?? 'audio/mpeg';
        $name = ($job->source_name ?: 'generated').'.mp3';

        return response()->file($absolute, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toMusicJobArray(AudioJob $job): array
    {
        $options = is_array($job->options) ? $job->options : [];
        $taskState = is_array($job->task_state[MusicCreateService::KIND_CREATE] ?? null)
            ? $job->task_state[MusicCreateService::KIND_CREATE]
            : [];

        return [
            'id' => $job->uuid,
            'endpoint' => 'music/create',
            'status' => $job->status,
            'progress' => $job->progress,
            'message' => $job->message,
            'options' => [
                'prompt' => $options['prompt'] ?? null,
                'duration_ms' => $options['duration_ms'] ?? null,
                'force_instrumental' => (bool) ($options['force_instrumental'] ?? false),
                'model_id' => $options['model_id'] ?? null,
            ],
            'task_status' => [
                'create' => [
                    'status' => $taskState['status'] ?? $job->status,
                    'progress' => (int) ($taskState['progress'] ?? $job->progress),
                    'message' => $taskState['message'] ?? $job->message,
                    'error' => $taskState['error'] ?? $job->error,
                ],
            ],
            'credits_spent' => $job->credits_spent,
            'source' => [
                'type' => $job->source_type,
                'name' => $job->displayName(),
            ],
            'error' => $job->error,
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
        ];
    }

    private function findOwnedCreateJob(Request $request, string $uuid): AudioJob
    {
        return AudioJob::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->where('kind', MusicCreateService::KIND_CREATE)
            ->firstOrFail();
    }
}
