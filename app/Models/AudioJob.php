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

    protected $fillable = [
        'uuid',
        'user_id',
        'kind',
        'provider',
        'status',
        'external_job_id',
        'progress',
        'message',
        'source_type',
        'source_path',
        'source_url',
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

    public function toStatusArray(): array
    {
        return [
            'id' => $this->uuid,
            'kind' => $this->kind,
            'status' => $this->status,
            'progress' => $this->progress,
            'message' => $this->message,
            'provider' => $this->provider,
            'credits_spent' => $this->credits_spent,
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
