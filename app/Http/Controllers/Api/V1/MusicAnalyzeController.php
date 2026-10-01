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
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Product-facing music analysis API:
 * - chords / lyrics → Magic Chords
 * - separate_stems → ElevenLabs
 */
class MusicAnalyzeController extends Controller
{
    public function __construct(
        private AudioJobService $audioJobs,
        private CreditService $credits,
    ) {}

    public function store(Request $request): JsonResponse
    {
        try {
            [$tasks, $options, $requested] = $this->resolveAnalyzeRequest($request);

            if (in_array(AudioJob::KIND_STEMS, $tasks, true)
                && ! filled(config('audio.providers.elevenlabs.api_key'))) {
                throw new RuntimeException('ELEVENLABS_API_KEY não está configurada.');
            }

            if ($request->hasFile('file')) {
                $request->validate([
                    'file' => [
                        'required',
                        'file',
                        'max:'.config('audio.upload.max_kb'),
                    ],
                ]);

                $job = $this->audioJobs->createFromUpload(
                    $request->user(),
                    $request->file('file'),
                    null,
                    $tasks,
                    $options,
                );
            } else {
                $request->validate([
                    'url' => ['required', 'url'],
                ]);

                $job = $this->audioJobs->createFromUrl(
                    $request->user(),
                    (string) $request->input('url'),
                    null,
                    $tasks,
                    $options,
                );
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            $status = 403;
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'créditos')) {
                $status = 402;
            } elseif (str_contains($msg, 'elevenlabs_api_key')) {
                $status = 503;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'credits' => $this->credits->balances($request->user()),
            ], $status);
        }

        return response()->json([
            'job' => $this->toMusicJobArray($job, $requested),
            'credits' => $this->credits->balances($request->user()),
        ], 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $job = $this->findOwnedJob($request, $uuid);
        $job = $this->audioJobs->refresh($job);

        return response()->json([
            'job' => $this->toMusicJobArray($job),
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
                'job' => $this->toMusicJobArray($job),
            ], 409);
        }

        return response()->json([
            'job' => $this->toMusicJobArray($job->fresh()),
            'result' => $result,
        ]);
    }

    public function stem(Request $request, string $uuid, string $stem): BinaryFileResponse
    {
        $job = AudioJob::query()->where('uuid', $uuid)->firstOrFail();

        try {
            $absolute = $this->audioJobs->stemAbsolutePath($job, $stem);
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        $canonical = strtolower($stem);
        $meta = $job->task_state[AudioJob::KIND_STEMS]['stems'][$canonical]
            ?? $job->result['stems'][$canonical]
            ?? [];

        return response()->file($absolute, [
            'Content-Type' => $meta['content_type'] ?? 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="'.$canonical.'.mp3"',
        ]);
    }

    /**
     * @return array{0: list<string>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function resolveAnalyzeRequest(Request $request): array
    {
        $request->validate([
            'options' => ['sometimes', 'array'],
            'options.chords' => ['sometimes', 'boolean'],
            'options.lyrics' => ['sometimes', 'boolean'],
            'options.separate_stems' => ['sometimes', 'array'],
            'options.separate_stems.*' => ['string'],
            'options.lyrics_granularity' => ['sometimes', 'in:phrase,word'],
            'chords' => ['sometimes', 'boolean'],
            'lyrics' => ['sometimes', 'boolean'],
            'separate_stems' => ['sometimes', 'array'],
            'separate_stems.*' => ['string'],
            'lyrics_granularity' => ['sometimes', 'in:phrase,word'],
        ]);

        $opts = $request->input('options');
        if (is_string($opts)) {
            $decoded = json_decode($opts, true);
            $opts = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($opts)) {
            $opts = [];
        }

        $hasStemsInput = $request->has('separate_stems')
            || array_key_exists('separate_stems', $opts);

        // Defaults: chords+lyrics on, unless the client only asked for stems.
        $defaultChords = true;
        $defaultLyrics = true;
        if ($hasStemsInput && ! $request->has('chords') && ! array_key_exists('chords', $opts)
            && ! $request->has('lyrics') && ! array_key_exists('lyrics', $opts)) {
            // If they only send separate_stems, don't force chords/lyrics.
            $defaultChords = false;
            $defaultLyrics = false;
        }

        $chords = $this->toBool($request->input('chords', $opts['chords'] ?? null), default: $defaultChords);
        $lyrics = $this->toBool($request->input('lyrics', $opts['lyrics'] ?? null), default: $defaultLyrics);

        $stems = $request->input('separate_stems', $opts['separate_stems'] ?? []);
        if (is_string($stems)) {
            $decoded = json_decode($stems, true);
            $stems = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(',', $stems))));
        }
        if (! is_array($stems)) {
            $stems = [];
        }

        $allowed = config('audio.stems.allowed', []);
        $aliases = config('audio.stems.aliases', []);
        $normalizedStems = [];
        foreach ($stems as $stem) {
            if (! is_string($stem)) {
                continue;
            }
            $key = strtolower(trim($stem));
            if (isset($aliases[$key])) {
                $normalizedStems[] = $aliases[$key];
            } elseif (in_array($key, $allowed, true)) {
                $normalizedStems[] = $key;
            } else {
                throw new InvalidArgumentException(
                    'Stem inválido: '.$stem.'. Usa: '.implode(', ', $allowed)
                );
            }
        }
        $normalizedStems = array_values(array_unique($normalizedStems));

        $granularity = (string) ($request->input('lyrics_granularity')
            ?? $opts['lyrics_granularity']
            ?? AudioJob::GRANULARITY_PHRASE);

        $tasks = [];
        if ($chords) {
            $tasks[] = AudioJob::KIND_ANALYZE;
        }
        if ($lyrics) {
            $tasks[] = AudioJob::KIND_TRANSCRIBE;
        }
        if ($normalizedStems !== []) {
            $tasks[] = AudioJob::KIND_STEMS;
        }

        if ($tasks === []) {
            throw new InvalidArgumentException(
                'Activa pelo menos uma opção: options.chords, options.lyrics ou options.separate_stems.'
            );
        }

        $requested = [
            'chords' => $chords,
            'lyrics' => $lyrics,
            'separate_stems' => $normalizedStems,
            'lyrics_granularity' => $granularity,
        ];

        return [
            $tasks,
            [
                'lyrics_granularity' => $granularity,
                'chords' => $chords,
                'lyrics' => $lyrics,
                'separate_stems' => $normalizedStems,
            ],
            $requested,
        ];
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * @param  array<string, mixed>|null  $requested
     * @return array<string, mixed>
     */
    private function toMusicJobArray(AudioJob $job, ?array $requested = null): array
    {
        $base = $job->toStatusArray();
        $tasks = $job->taskList();
        $stored = is_array($job->options) ? $job->options : [];
        $taskStatus = $base['task_status'] ?? [];

        $options = $requested ?? [
            'chords' => array_key_exists('chords', $stored)
                ? (bool) $stored['chords']
                : in_array(AudioJob::KIND_ANALYZE, $tasks, true),
            'lyrics' => array_key_exists('lyrics', $stored)
                ? (bool) $stored['lyrics']
                : in_array(AudioJob::KIND_TRANSCRIBE, $tasks, true),
            'separate_stems' => is_array($stored['separate_stems'] ?? null)
                ? array_values($stored['separate_stems'])
                : [],
            'lyrics_granularity' => $job->lyricsGranularity(),
        ];

        return [
            'id' => $base['id'],
            'endpoint' => 'music/analyze',
            'status' => $base['status'],
            'progress' => $base['progress'],
            'message' => $base['message'],
            'options' => $options,
            'task_status' => [
                'chords' => $taskStatus[AudioJob::KIND_ANALYZE] ?? null,
                'lyrics' => $taskStatus[AudioJob::KIND_TRANSCRIBE] ?? null,
                'stems' => $taskStatus[AudioJob::KIND_STEMS] ?? null,
            ],
            'credits_spent' => $base['credits_spent'],
            'source' => $base['source'],
            'error' => $base['error'],
            'created_at' => $base['created_at'],
            'updated_at' => $base['updated_at'],
        ];
    }

    private function findOwnedJob(Request $request, string $uuid): AudioJob
    {
        return AudioJob::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
