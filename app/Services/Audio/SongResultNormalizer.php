<?php

namespace App\Services\Audio;

/**
 * Maps any provider payload into the GigRunner song cue list.
 */
class SongResultNormalizer
{
    /**
     * @param  array<string, mixed>  $providerPayload
     * @return array{format: int, cues: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function normalize(string $provider, array $providerPayload, ?string $kind = null): array
    {
        $cues = match ($provider) {
            'magic_chords' => $this->fromMagicChords($providerPayload, $kind),
            default => $this->fromMagicChords($providerPayload, $kind),
        };

        usort($cues, fn (array $a, array $b) => $a['timeMs'] <=> $b['timeMs']);

        return [
            'format' => 1,
            'cues' => array_values($cues),
            'meta' => [
                'provider' => $provider,
                'kind' => $kind,
                'bpm' => $this->extractBpm($providerPayload),
                'key' => $this->extractKey($providerPayload),
                'durationMs' => $this->extractDurationMs($providerPayload, $cues),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function fromMagicChords(array $payload, ?string $kind): array
    {
        $cues = [];

        // Chord segments (analyze)
        foreach ($this->chordRows($payload) as $row) {
            $name = $this->firstString($row, ['label', 'chord', 'name', 'symbol', 'value']);
            if ($name === null || trim($name) === '') {
                continue;
            }
            $name = trim($name);
            if (in_array(strtoupper($name), ['N', 'N.C.', 'NC', 'NONE', 'X'], true)) {
                continue;
            }

            $startMs = $this->startMs($row);
            $endMs = $this->endMs($row, $startMs);
            $cues[] = SongCueFactory::make(
                SongCueFactory::KIND_CHORD,
                $name,
                $startMs,
                max(0, $endMs - $startMs),
            );
        }

        // Lyrics / words (transcribe)
        foreach ($this->lyricRows($payload) as $row) {
            $name = $this->firstString($row, ['text', 'word', 'name', 'lyric', 'label']);
            if ($name === null || trim($name) === '') {
                continue;
            }

            $startMs = $this->startMs($row);
            $endMs = $this->endMs($row, $startMs);
            $cues[] = SongCueFactory::make(
                SongCueFactory::KIND_LYRIC,
                trim((string) $name),
                $startMs,
                max(0, $endMs - $startMs),
            );
        }

        // If kind is analyze and we only got lyrics somehow, still fine.
        // If empty and kind hints only one type, return empty list.
        unset($kind);

        return $cues;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function chordRows(array $payload): array
    {
        foreach (['segments', 'chords', 'chord_segments', 'timeline'] as $key) {
            if (! empty($payload[$key]) && is_array($payload[$key])) {
                $rows = $payload[$key];
                // timeline may mix types
                if ($key === 'timeline') {
                    return array_values(array_filter($rows, function ($row) {
                        if (! is_array($row)) {
                            return false;
                        }
                        $kind = strtolower((string) ($row['kind'] ?? $row['type'] ?? 'chord'));

                        return in_array($kind, ['chord', 'chords', 'segment'], true)
                            || isset($row['chord']) || isset($row['label']);
                    }));
                }

                return array_values(array_filter($rows, 'is_array'));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function lyricRows(array $payload): array
    {
        foreach (['lines', 'lyrics', 'words', 'transcript'] as $key) {
            if (empty($payload[$key]) || ! is_array($payload[$key])) {
                continue;
            }

            $rows = $payload[$key];

            // transcript may be a string
            if ($key === 'transcript' && isset($rows[0]) && is_string($rows[0])) {
                continue;
            }

            if ($key === 'transcript' && is_string($payload[$key])) {
                continue;
            }

            // Prefer line-level over raw words when both exist — handled by key order
            return array_values(array_filter($rows, 'is_array'));
        }

        if (! empty($payload['timeline']) && is_array($payload['timeline'])) {
            return array_values(array_filter($payload['timeline'], function ($row) {
                if (! is_array($row)) {
                    return false;
                }
                $kind = strtolower((string) ($row['kind'] ?? $row['type'] ?? ''));

                return in_array($kind, ['lyric', 'lyrics', 'word', 'line'], true)
                    || isset($row['word']) || isset($row['text']);
            }));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function startMs(array $row): int
    {
        foreach (['timeMs', 'start_ms', 'startMs'] as $key) {
            if (isset($row[$key])) {
                return SongCueFactory::toMs($row[$key], assumeSeconds: false);
            }
        }

        foreach (['start', 'start_time', 'from', 'begin', 'time'] as $key) {
            if (isset($row[$key])) {
                return SongCueFactory::toMs($row[$key], assumeSeconds: true);
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function endMs(array $row, int $startMs): int
    {
        foreach (['end_ms', 'endMs'] as $key) {
            if (isset($row[$key])) {
                return SongCueFactory::toMs($row[$key], assumeSeconds: false);
            }
        }

        if (isset($row['durationMs'])) {
            return $startMs + SongCueFactory::toMs($row['durationMs'], assumeSeconds: false);
        }

        if (isset($row['duration'])) {
            return $startMs + SongCueFactory::toMs($row['duration'], assumeSeconds: true);
        }

        foreach (['end', 'end_time', 'to', 'finish'] as $key) {
            if (isset($row[$key])) {
                return SongCueFactory::toMs($row[$key], assumeSeconds: true);
            }
        }

        return $startMs;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function firstString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && (is_string($row[$key]) || is_numeric($row[$key]))) {
                return (string) $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractBpm(array $payload): ?float
    {
        foreach (['tempo', 'bpm', 'beats_per_minute'] as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return round((float) $payload[$key], 2);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractKey(array $payload): ?string
    {
        foreach (['key', 'tonality', 'musical_key'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $cues
     */
    private function extractDurationMs(array $payload, array $cues): ?int
    {
        foreach (['duration_ms', 'durationMs'] as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return (int) round((float) $payload[$key]);
            }
        }

        if (isset($payload['duration']) && is_numeric($payload['duration'])) {
            return SongCueFactory::toMs($payload['duration'], assumeSeconds: true);
        }

        if ($cues === []) {
            return null;
        }

        $last = end($cues);

        return ($last['timeMs'] ?? 0) + ($last['durationMs'] ?? 0);
    }
}
