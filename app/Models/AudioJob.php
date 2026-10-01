<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AudioJob extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETE = 'complete';

    public const STATUS_FAILED = 'failed';

    public const KIND_ANALYZE = 'analyze';

    public const KIND_TRANSCRIBE = 'transcribe';

    public const KIND_STEMS = 'stems';

    public const KIND_CREATE = 'create';

    public const GRANULARITY_PHRASE = 'phrase';

    public const GRANULARITY_WORD = 'word';

    protected $fillable = [
        'uuid',
        'user_id',
        'kind',
        'tasks',
        'task_state',
        'options',
        'provider',
        'status',
        'external_job_id',
        'progress',
        'message',
        'source_type',
        'source_path',
        'source_url',
        'source_name',
        'credits_spent',
        'result',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'credits_spent' => 'integer',
            'result' => 'array',
            'tasks' => 'array',
            'task_state' => 'array',
            'options' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AudioJob $job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<string>
     */
    public function taskList(): array
    {
        if (is_array($this->tasks) && $this->tasks !== []) {
            return array_values(array_unique($this->tasks));
        }

        return [$this->kind ?: self::KIND_ANALYZE];
    }

    public function lyricsGranularity(): string
    {
        $value = $this->options['lyrics_granularity'] ?? self::GRANULARITY_PHRASE;

        return in_array($value, [self::GRANULARITY_PHRASE, self::GRANULARITY_WORD], true)
            ? $value
            : self::GRANULARITY_PHRASE;
    }

    public function toStatusArray(): array
    {
        $tasks = $this->taskList();
        $taskState = is_array($this->task_state) ? $this->task_state : [];

        $tasksOut = [];
        foreach ($tasks as $task) {
            $state = is_array($taskState[$task] ?? null) ? $taskState[$task] : [];
            $tasksOut[$task] = [
                'status' => $state['status'] ?? $this->status,
                'progress' => (int) ($state['progress'] ?? $this->progress),
                'message' => $state['message'] ?? $this->message,
                'error' => $state['error'] ?? null,
            ];
        }

        return [
            'id' => $this->uuid,
            'kind' => count($tasks) === 1 ? $tasks[0] : 'both',
            'tasks' => $tasks,
            'task_status' => $tasksOut,
            'status' => $this->status,
            'progress' => $this->progress,
            'message' => $this->message,
            'options' => [
                'lyrics_granularity' => $this->lyricsGranularity(),
            ],
            'credits_spent' => $this->credits_spent,
            'source' => [
                'type' => $this->source_type,
                'name' => $this->displayName(),
                'url' => $this->source_type === 'url' ? $this->source_url : null,
            ],
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function displayName(): string
    {
        if (filled($this->source_name)) {
            return (string) $this->source_name;
        }

        if ($this->source_type === 'url' && filled($this->source_url)) {
            $path = parse_url((string) $this->source_url, PHP_URL_PATH);

            return $path ? basename($path) : (string) $this->source_url;
        }

        if (filled($this->source_path)) {
            return basename((string) $this->source_path);
        }

        return 'Áudio';
    }
}
