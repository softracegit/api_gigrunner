<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class License extends Model
{
    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'starts_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function toApiArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'status' => $this->status,
            'plan' => $this->plan,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }

    public static function inactivePayload(): array
    {
        return [
            'valid' => false,
            'status' => self::STATUS_INACTIVE,
            'plan' => null,
            'starts_at' => null,
            'expires_at' => null,
        ];
    }
}
