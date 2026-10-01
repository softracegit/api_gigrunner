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
    public function normalize(
        string $provider,
        array $providerPayload,
        ?string $kind = null,
        string $lyricsGranularity = 'phrase',
    ): array {
        $granularity = in_array($lyricsGranularity, ['phrase', 'word'], true)
            ? $lyricsGranularity
            : 'phrase';

        $cues = match ($provider) {
            'magic_chords' => $this->fromMagicChords($providerPayload, $kind, $granularity),
            default => $this->fromMagicChords($providerPayload, $kind, $granularity),
        };

        usort($cues, fn (array $a, array $b) => $a['timeMs'] <=> $b['timeMs']);

        return [
            'format' => 1,
            'cues' => array_values($cues),
            'meta' => [
                'provider' => $provider,
                'kind' => $kind,
                'lyrics_granularity' => $granularity,
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
    private function fromMagicChords(array $payload, ?string $kind, string $lyricsGranularity): array
    {
        $cues = [];
        $includeChords = $kind === null
            || $kind === 'analyze'
            || $kind === 'both';
        $includeLyrics = $kind === null
            || $kind === 'transcribe'
            || $kind === 'both';

        if ($includeChords) {
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
        }

        if ($includeLyrics) {
            $lyrics = $lyricsGranularity === 'word'
                ? $this->lyricWords($payload)
                : $this->lyricPhrases($payload);

            foreach ($lyrics as $lyric) {
                $cues[] = SongCueFactory::make(
                    SongCueFactory::KIND_LYRIC,
                    $lyric['name'],
                    $lyric['timeMs'],
                    $lyric['durationMs'],
                );
            }
        }

        return $cues;
    }

    /**
     * One lyric cue per timed word (from top-level words or nested segment words).
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{name: string, timeMs: int, durationMs: int}>
     */
    private function lyricWords(array $payload): array
    {
        $words = $this->wordRowsIncludingNested($payload);
        if ($words === []) {
            // Fall back to phrase segments split by whitespace (no timings per word)
            $phrases = $this->lyricPhrases($payload);
            $out = [];
            foreach ($phrases as $phrase) {
                $tokens = preg_split('/\s+/u', $phrase['name']) ?: [];
                foreach ($tokens as $token) {
                    $token = trim($token);
                    if ($token === '' || $this->isNoiseLyricToken($token)) {
                        continue;
                    }
                    $out[] = [
                        'name' => $token,
                        'timeMs' => $phrase['timeMs'],
                        'durationMs' => 0,
                    ];
                }
            }

            return $out;
        }

        $out = [];
        foreach ($words as $row) {
            $token = $this->firstString($row, ['word', 'text', 'name', 'token']);
            if ($token === null) {
                continue;
            }
            $token = trim($token);
            if ($token === '' || $this->isNoiseLyricToken($token)) {
                continue;
            }

            $startMs = $this->startMs($row);
            $endMs = $this->endMs($row, $startMs);
            $out[] = [
                'name' => $token,
                'timeMs' => $startMs,
                'durationMs' => max(0, $endMs - $startMs),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function wordRowsIncludingNested(array $payload): array
    {
        $top = $this->wordRows($payload);
        if ($top !== []) {
            return $top;
        }

        $nested = [];
        foreach ($this->whisperStyleSegments($payload) as $segment) {
            if (empty($segment['words']) || ! is_array($segment['words'])) {
                continue;
            }
            foreach ($segment['words'] as $word) {
                if (is_array($word)) {
                    $nested[] = $word;
                }
            }
        }

        return $nested;
    }

    /**
     * Build phrase-level lyric cues from Magic Chords / Whisper segments.
     * Prefer segment.text (full phrase); only fall back to grouping words.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{name: string, timeMs: int, durationMs: int}>
     */
    private function lyricPhrases(array $payload): array
    {
        // 1) Whisper-style segments already phrased (Magic Chords transcribe)
        $segments = $this->whisperStyleSegments($payload);
        if ($segments !== []) {
            $phrases = [];
            foreach ($segments as $row) {
                $name = $this->firstString($row, ['text', 'lyric', 'content']);
                if ($name === null) {
                    continue;
                }
                $name = $this->normalizeLyricText($name);
                if ($name === '' || $this->isNoiseLyricToken($name)) {
                    continue;
                }

                $startMs = $this->startMs($row);
                $endMs = $this->endMs($row, $startMs);
                $phrases[] = [
                    'name' => $name,
                    'timeMs' => $startMs,
                    'durationMs' => max(0, $endMs - $startMs),
                ];
            }

            if ($phrases !== []) {
                return $phrases;
            }
        }

        // 2) Plain transcript string → one cue per paragraph
        if (! empty($payload['transcript']) && is_string($payload['transcript'])) {
            $blocks = preg_split("/\n{2,}|\r\n{2,}/", trim($payload['transcript'])) ?: [];
            $phrases = [];
            foreach ($blocks as $block) {
                $text = $this->normalizeLyricText($block);
                if ($text === '' || $this->isNoiseLyricToken($text)) {
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

        // 3) Last resort: flat words → group into lines
        $words = $this->wordRows($payload);
        if ($words === []) {
            return [];
        }

        return $this->groupWordsIntoPhrases($words);
    }

    /**
     * Magic Chords / Whisper phrase segments: { start, end, text, words? }.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function whisperStyleSegments(array $payload): array
    {
        $candidates = [];

        foreach (['lines', 'lyrics', 'phrases', 'lyric_lines', 'utterances', 'speech_segments', 'segments'] as $key) {
            if (! empty($payload[$key]) && is_array($payload[$key])) {
                $candidates[] = $payload[$key];
            }
        }

        if (! empty($payload['transcription']) && is_array($payload['transcription'])) {
            $nested = $payload['transcription'];
            if (! empty($nested['segments']) && is_array($nested['segments'])) {
                $candidates[] = $nested['segments'];
            } elseif (array_is_list($nested)) {
                $candidates[] = $nested;
            }
        }

        foreach ($candidates as $rows) {
            $filtered = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                // Must be a phrase segment with text (not a chord label segment)
                if (! isset($row['text']) || ! is_string($row['text'])) {
                    continue;
                }
                // Skip chord-shaped rows that happen to include text
                if (isset($row['label']) || isset($row['chord'])) {
                    continue;
                }
                $filtered[] = $row;
            }

            if ($filtered !== []) {
                return array_values($filtered);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function wordRows(array $payload): array
    {
        // Prefer top-level words only when we are in fallback mode.
        // Nested words inside whisper segments are ignored here on purpose.
        foreach (['words', 'tokens'] as $key) {
            if (! empty($payload[$key]) && is_array($payload[$key])) {
                // If entries look like Whisper word objects but parent also has
                // phrase segments, whisperStyleSegments already won.
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
     * Group timed words into one lyric cue per sung line.
     *
     * @param  list<array<string, mixed>>  $words
     * @return list<array{name: string, timeMs: int, durationMs: int}>
     */
    private function groupWordsIntoPhrases(array $words): array
    {
        $lineBreakMs = 480;
        $tokens = [];

        foreach ($words as $row) {
            $token = $this->firstString($row, ['word', 'text', 'name', 'token']);
            if ($token === null) {
                continue;
            }
            $token = trim($token);
            if ($token === '' || $this->isNoiseLyricToken($token)) {
                continue;
            }

            $startMs = $this->startMs($row);
            $endMs = $this->endMs($row, $startMs);
            if ($endMs < $startMs) {
                $endMs = $startMs;
            }

            $tokens[] = [
                'text' => $token,
                'startMs' => $startMs,
                'endMs' => $endMs,
            ];
        }

        if ($tokens === []) {
            return [];
        }

        $rawLines = [];
        $current = [];
        $lineStart = null;
        $lineEnd = null;
        $lastEnd = null;

        foreach ($tokens as $token) {
            $gap = $lastEnd === null ? 0 : max(0, $token['startMs'] - $lastEnd);
            $shouldBreak = $current !== [] && (
                $gap >= $lineBreakMs
                || preg_match('/[.!?…]$/u', $current[array_key_last($current)]['text']) === 1
            );

            if ($shouldBreak) {
                $rawLines[] = [
                    'words' => $current,
                    'startMs' => $lineStart,
                    'endMs' => $lineEnd,
                ];
                $current = [];
                $lineStart = null;
                $lineEnd = null;
            }

            if ($lineStart === null) {
                $lineStart = $token['startMs'];
            }
            $current[] = $token;
            $lineEnd = $token['endMs'];
            $lastEnd = $token['endMs'];
        }

        if ($current !== []) {
            $rawLines[] = [
                'words' => $current,
                'startMs' => $lineStart,
                'endMs' => $lineEnd,
            ];
        }

        $lines = $this->coalesceShortLyricLines($rawLines);

        $phrases = [];
        foreach ($lines as $line) {
            $name = $this->normalizeLyricText(implode(' ', array_column($line['words'], 'text')));
            if ($name === '' || $this->isNoiseLyricToken($name)) {
                continue;
            }

            $phrases[] = [
                'name' => $name,
                'timeMs' => (int) $line['startMs'],
                'durationMs' => max(0, (int) $line['endMs'] - (int) $line['startMs']),
            ];
        }

        return $phrases;
    }

    /**
     * Merge stub lines ("Hey,", "I'm", "Music") into the following line.
     *
     * @param  list<array{words: list<array{text: string, startMs: int, endMs: int}>, startMs: int|null, endMs: int|null}>  $lines
     * @return list<array{words: list<array{text: string, startMs: int, endMs: int}>, startMs: int, endMs: int}>
     */
    private function coalesceShortLyricLines(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $out = [];
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            $line = $lines[$i];
            $text = trim(implode(' ', array_column($line['words'], 'text')));

            if ($this->isNoiseLyricToken($text)) {
                $i++;

                continue;
            }

            while (
                $i + 1 < $n
                && $this->shouldMergeLyricLineIntoNext($line)
            ) {
                $next = $lines[$i + 1];
                $line = [
                    'words' => array_merge($line['words'], $next['words']),
                    'startMs' => $line['startMs'],
                    'endMs' => $next['endMs'],
                ];
                $i++;
            }

            $out[] = [
                'words' => $line['words'],
                'startMs' => (int) $line['startMs'],
                'endMs' => (int) $line['endMs'],
            ];
            $i++;
        }

        return $out;
    }

    /**
     * @param  array{words: list<array{text: string, startMs: int, endMs: int}>, startMs: int|null, endMs: int|null}  $line
     */
    private function shouldMergeLyricLineIntoNext(array $line): bool
    {
        $words = $line['words'];
        $count = count($words);
        if ($count === 0) {
            return true;
        }

        $text = trim(implode(' ', array_column($words, 'text')));
        if ($this->isNoiseLyricToken($text)) {
            return true;
        }

        // "Hey," / "Oh," / single word stubs
        if ($count <= 2 && preg_match('/[,;:\-–—]$/u', $text) === 1) {
            return true;
        }

        if ($count === 1) {
            return true;
        }

        // Very short incomplete fragments
        if ($count <= 2 && preg_match('/[.!?…]$/u', $text) !== 1) {
            return true;
        }

        return false;
    }

    private function isNoiseLyricToken(string $token): bool
    {
        $normalized = strtolower(trim($token, " \t\n\r\0\x0B[](){}♪♫*\"'"));

        return in_array($normalized, [
            '',
            'music',
            'instrumental',
            'applause',
            'laughter',
            'silence',
            'inaudible',
            'foreign',
            '...',
            '…',
        ], true);
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
        foreach (['chords', 'chord_segments', 'segments', 'timeline'] as $key) {
            if (empty($payload[$key]) || ! is_array($payload[$key])) {
                continue;
            }

            $rows = array_values(array_filter($payload[$key], function ($row) use ($key) {
                if (! is_array($row)) {
                    return false;
                }

                // Whisper lyric segments live under "segments" too — skip those.
                if (isset($row['text']) && is_string($row['text']) && ! isset($row['label']) && ! isset($row['chord'])) {
                    return false;
                }

                if ($key === 'timeline') {
                    $kind = strtolower((string) ($row['kind'] ?? $row['type'] ?? 'chord'));

                    return in_array($kind, ['chord', 'chords', 'segment'], true)
                        || isset($row['chord']) || isset($row['label']);
                }

                return isset($row['label']) || isset($row['chord']) || isset($row['symbol'])
                    || ($key !== 'segments');
            }));

            if ($rows !== []) {
                return $rows;
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
