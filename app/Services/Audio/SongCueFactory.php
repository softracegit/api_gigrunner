<?php

namespace App\Services\Audio;

/**
 * Builds GigRunner/Cue Companion cue objects.
 *
 * @phpstan-type Cue array{
 *     id: string,
 *     name: string,
 *     timeMs: int,
 *     kind: string,
 *     channel: int,
 *     number: int,
 *     value: int,
 *     durationMs: int
 * }
 */
class SongCueFactory
{
    public const KIND_CHORD = 'chord';

    public const KIND_LYRIC = 'lyric';

    /**
     * @return Cue
     */
    public static function make(
        string $kind,
        string $name,
        int $timeMs,
        int $durationMs,
        ?string $id = null,
    ): array {
        return [
            'id' => $id ?? self::generateId(),
            'name' => $name,
            'timeMs' => max(0, $timeMs),
            'kind' => $kind,
            'channel' => 1,
            'number' => 60,
            'value' => 100,
            'durationMs' => max(0, $durationMs),
        ];
    }

    public static function generateId(): string
    {
        return sprintf(
            '%d_%u',
            (int) (microtime(true) * 1_000_000),
            random_int(1, 4_294_967_295),
        );
    }

    /**
     * Convert provider time to milliseconds.
     * Values that look like seconds (small floats / modest ranges) are scaled.
     */
    public static function toMs(float|int|string|null $value, bool $assumeSeconds = true): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $n = (float) $value;

        if (! $assumeSeconds) {
            return (int) round($n);
        }

        // Already ms if clearly large integer-ish and not a short song start
        if ($n >= 1000 && abs($n - round($n)) < 0.0001) {
            return (int) round($n);
        }

        return (int) round($n * 1000);
    }
}
