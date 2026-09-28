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

        // Lyrics as full phrases (never one cue per word)
        foreach ($this->lyricPhrases($payload) as $phrase) {
            $cues[] = SongCueFactory::make(
                SongCueFactory::KIND_LYRIC,
                $phrase['name'],
                $phrase['timeMs'],
                $phrase['durationMs'],
            );
        }

        unset($kind);

        return $cues;
    }

    /**
     * Build phrase-level lyric cues.
     * Prefers provider lines/lyrics; otherwise groups words into phrases.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{name: string, timeMs: int, durationMs: int}>
     */
    private function lyricPhrases(array $payload): array
    {
        // 1) Explicit line / lyric objects with multi-word text
        foreach (['lines', 'lyrics', 'phrases', 'lyric_lines'] as $key) {
            if (empty($payload[$key]) || ! is_array($payload[$key])) {
                continue;
            }

            $phrases = [];
            foreach ($payload[$key] as $row) {
                if (! is_array($row)) {
                    if (is_string($row) && trim($row) !== '') {
                        $phrases[] = [
                            'name' => $this->normalizeLyricText($row),
                            'timeMs' => 0,
                            'durationMs' => 0,
                        ];
                    }

                    continue;
                }

                $name = $this->firstString($row, ['text', 'lyric', 'name', 'label', 'content']);
                if ($name === null || trim($name) === '') {
                    continue;
                }

                // Skip obvious single-token word arrays mistakenly under "lyrics"
                $wordCount = preg_match_all('/\S+/u', trim($name)) ?: 0;
                if ($wordCount <= 1 && isset($row['word']) && ! isset($row['text'])) {
                    continue;
                }

                $startMs = $this->startMs($row);
                $endMs = $this->endMs($row, $startMs);
                $phrases[] = [
                    'name' => $this->normalizeLyricText($name),
                    'timeMs' => $startMs,
                    'durationMs' => max(0, $endMs - $startMs),
                ];
            }

            if ($phrases !== []) {
                return $phrases;
            }
        }

        // 2) Plain transcript string → one cue per blank-line / newline block
        if (! empty($payload['transcript']) && is_string($payload['transcript'])) {
            $blocks = preg_split("/\n{2,}|\r\n{2,}/", trim($payload['transcript'])) ?: [];
            $phrases = [];
            foreach ($blocks as $block) {
                $text = $this->normalizeLyricText($block);
                if ($text === '') {
                    continue;
                }
                $phrases[] = [
                    'name' => $text,
                    'timeMs' => 0,
                    'durationMs' => 0,
                ];
            }
            if ($phrases !== []) {
                return $phrases;
            }
        }

        // 3) Word-level timings → group into phrases / lines
        $words = $this->wordRows($payload);
        if ($words === []) {
            return [];
        }

        return $this->groupWordsIntoPhrases($words);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function wordRows(array $payload): array
    {
        foreach (['words', 'tokens'] as $key) {
            if (! empty($payload[$key]) && is_array($payload[$key])) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }

        if (! empty($payload['timeline']) && is_array($payload['timeline'])) {
            return array_values(array_filter($payload['timeline'], function ($row) {
                if (! is_array($row)) {
                    return false;
                }
                $kind = strtolower((string) ($row['kind'] ?? $row['type'] ?? ''));

                return in_array($kind, ['word', 'token'], true) || isset($row['word']);
            }));
        }

        return [];
    }

    /**
     * Group timed words into phrase cues.
     * - short gap → same line (space)
     * - medium gap → new line inside same cue (\n)
     * - long gap / punctuation / length limits → new cue
     *
     * @param  list<array<string, mixed>>  $words
     * @return list<array{name: string, timeMs: int, durationMs: int}>
     */
    private function groupWordsIntoPhrases(array $words): array
    {
        $lineGapMs = 320;   // break line within phrase
        $phraseGapMs = 750; // start a new cue
        $maxPhraseMs = 8000;
        $maxLinesPerPhrase = 2;
        $maxWordsPerLine = 10;

        $phrases = [];
        $lines = [];       // list of strings for current phrase
        $currentLine = []; // words in current line
        $phraseStart = null;
        $phraseEnd = null;
        $lastEnd = null;

        $flushPhrase = function () use (&$phrases, &$lines, &$currentLine, &$phraseStart, &$phraseEnd) {
            if ($currentLine !== []) {
                $lines[] = implode(' ', $currentLine);
                $currentLine = [];
            }
            if ($lines === [] || $phraseStart === null) {
                $lines = [];
                $phraseStart = null;
                $phraseEnd = null;

                return;
            }

            $phrases[] = [
                'name' => $this->normalizeLyricText(implode("\n", $lines)),
                'timeMs' => $phraseStart,
                'durationMs' => max(0, ($phraseEnd ?? $phraseStart) - $phraseStart),
            ];
            $lines = [];
            $phraseStart = null;
            $phraseEnd = null;
        };

        foreach ($words as $row) {
            $token = $this->firstString($row, ['word', 'text', 'name', 'token']);
            if ($token === null) {
                continue;
            }
            $token = trim($token);
            if ($token === '' || $token === '[Music]' || $token === '(Music)') {
                continue;
            }

            $startMs = $this->startMs($row);
            $endMs = $this->endMs($row, $startMs);
            if ($endMs < $startMs) {
                $endMs = $startMs;
            }

            $gap = $lastEnd === null ? 0 : max(0, $startMs - $lastEnd);
            $phraseDuration = $phraseStart === null ? 0 : max(0, $startMs - $phraseStart);

            $startsNewPhrase = $phraseStart !== null && (
                $gap >= $phraseGapMs
                || $phraseDuration >= $maxPhraseMs
                || count($lines) >= $maxLinesPerPhrase && $gap >= $lineGapMs
            );

            if ($startsNewPhrase) {
                $flushPhrase();
            } elseif ($phraseStart !== null && $gap >= $lineGapMs && $currentLine !== []) {
                $lines[] = implode(' ', $currentLine);
                $currentLine = [];
            } elseif ($phraseStart !== null && count($currentLine) >= $maxWordsPerLine) {
                $lines[] = implode(' ', $currentLine);
                $currentLine = [];
                if (count($lines) >= $maxLinesPerPhrase) {
                    $flushPhrase();
                }
            }

            if ($phraseStart === null) {
                $phraseStart = $startMs;
            }
            $currentLine[] = $token;
            $phraseEnd = $endMs;
            $lastEnd = $endMs;

            // Punctuation that ends a spoken sentence → close phrase
            if (preg_match('/[.!?…]$/u', $token) === 1) {
                $flushPhrase();
                $lastEnd = $endMs;
            }
        }

        $flushPhrase();

        return $phrases;
    }

    private function normalizeLyricText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n[ \t]+/", "\n", $text) ?? $text;
        $text = preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
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
