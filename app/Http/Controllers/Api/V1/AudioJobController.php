<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AudioJob;
use App\Services\Audio\AudioJobService;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class AudioJobController extends Controller
{
    public function __construct(
        private AudioJobService $audioJobs,
        private CreditService $credits,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $kind = $request->input('kind', AudioJob::KIND_ANALYZE);

        try {
            if ($request->hasFile('file')) {
                $request->validate([
                    'file' => [
                        'required',
                        'file',
                        'max:'.config('audio.upload.max_kb'),
                    ],
                    'kind' => ['sometimes', 'in:analyze,transcribe'],
                ]);

                $job = $this->audioJobs->createFromUpload(
                    $request->user(),
                    $request->file('file'),
                    $kind,
                );
            } else {
                $data = $request->validate([
                    'url' => ['required', 'url'],
                    'kind' => ['sometimes', 'in:analyze,transcribe'],
                ]);

                $job = $this->audioJobs->createFromUrl(
                    $request->user(),
                    $data['url'],
                    $data['kind'] ?? $kind,
                );
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'créditos') ? 402 : 403;

            return response()->json([
                'message' => $e->getMessage(),
                'credits' => $this->credits->balances($request->user()),
            ], $status);
        }

        return response()->json([
            'job' => $job->toStatusArray(),
            'credits' => $this->credits->balances($request->user()),
        ], 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $job = $this->findOwnedJob($request, $uuid);
        $job = $this->audioJobs->refresh($job);

        return response()->json([
            'job' => $job->toStatusArray(),
        ]);
    }

    public function result(Request $request, string $uuid): JsonResponse
    {
        $job = $this->findOwnedJob($request, $uuid);

        try {
            $result = $this->audioJobs->result($job);
        } catch (RuntimeException $e) {
            $job = $job->fresh();

            return response()->json([
                'message' => $e->getMessage(),
                'job' => $job->toStatusArray(),
            ], 409);
        }

        return response()->json([
            'job' => $job->fresh()->toStatusArray(),
            'result' => $result,
        ]);
    }

    private function findOwnedJob(Request $request, string $uuid): AudioJob
    {
        return AudioJob::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
