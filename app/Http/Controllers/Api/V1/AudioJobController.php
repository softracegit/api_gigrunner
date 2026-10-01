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

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'kind' => ['sometimes', 'in:analyze,transcribe,both'],
            'status' => ['sometimes', 'in:queued,processing,complete,failed'],
        ]);

        $query = AudioJob::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id');

        if (! empty($data['kind'])) {
            if ($data['kind'] === 'both') {
                $query->where('kind', 'both');
            } else {
                $query->where(function ($q) use ($data) {
                    $q->where('kind', $data['kind'])
                        ->orWhereJsonContains('tasks', $data['kind']);
                });
            }
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $paginator = $query->paginate($data['per_page'] ?? 20);

        return response()->json([
            'jobs' => $paginator->getCollection()->map->toStatusArray()->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $options = $this->extractOptions($request);

            if ($request->hasFile('file')) {
                $request->validate([
                    'file' => [
                        'required',
                        'file',
                        'max:'.config('audio.upload.max_kb'),
                    ],
                    'kind' => ['sometimes', 'in:analyze,transcribe,both'],
                    'tasks' => ['sometimes', 'array', 'min:1'],
                    'tasks.*' => ['string', 'in:analyze,transcribe,both'],
                    'lyrics_granularity' => ['sometimes', 'in:phrase,word'],
                    'options' => ['sometimes', 'array'],
                    'options.lyrics_granularity' => ['sometimes', 'in:phrase,word'],
                ]);

                $job = $this->audioJobs->createFromUpload(
                    $request->user(),
                    $request->file('file'),
                    $request->input('kind'),
                    $this->extractTasks($request),
                    $options,
                );
            } else {
                $request->validate([
                    'url' => ['required', 'url'],
                    'kind' => ['sometimes', 'in:analyze,transcribe,both'],
                    'tasks' => ['sometimes', 'array', 'min:1'],
                    'tasks.*' => ['string', 'in:analyze,transcribe,both'],
                    'lyrics_granularity' => ['sometimes', 'in:phrase,word'],
                    'options' => ['sometimes', 'array'],
                    'options.lyrics_granularity' => ['sometimes', 'in:phrase,word'],
                ]);

                $job = $this->audioJobs->createFromUrl(
                    $request->user(),
                    (string) $request->input('url'),
                    $request->input('kind'),
                    $this->extractTasks($request),
                    $options,
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

    /**
     * @return list<string>|null
     */
    private function extractTasks(Request $request): ?array
    {
        if (! $request->has('tasks')) {
            return null;
        }

        $tasks = $request->input('tasks');

        // multipart may send tasks as JSON string or repeated fields
        if (is_string($tasks)) {
            $decoded = json_decode($tasks, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return array_values(array_filter(array_map('trim', explode(',', $tasks))));
        }

        return is_array($tasks) ? $tasks : null;
    }

    /**
     * @return array{lyrics_granularity?: string}
     */
    private function extractOptions(Request $request): array
    {
        $options = $request->input('options');
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($options)) {
            $options = [];
        }

        if ($request->filled('lyrics_granularity')) {
            $options['lyrics_granularity'] = $request->input('lyrics_granularity');
        }

        return $options;
    }

    private function findOwnedJob(Request $request, string $uuid): AudioJob
    {
        return AudioJob::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
