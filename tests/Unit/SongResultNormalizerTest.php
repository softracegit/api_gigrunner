<?php

namespace Tests\Unit;

use App\Services\Audio\SongResultNormalizer;
use PHPUnit\Framework\TestCase;

class SongResultNormalizerTest extends TestCase
{
    public function test_maps_magic_chords_segments_and_words_to_cues(): void
    {
        $out = (new SongResultNormalizer)->normalize('magic_chords', [
            'tempo' => 118.5,
            'key' => 'F#m',
            'segments' => [
                ['label' => 'F#m', 'start' => 1.2, 'end' => 3.5],
                ['label' => 'N', 'start' => 3.5, 'end' => 4.0],
                ['label' => 'E', 'start' => 4.0, 'end' => 6.0],
            ],
            'lines' => [
                ['text' => 'Line one', 'start' => 1.5, 'end' => 4.2],
            ],
        ], 'both');

        $this->assertSame(1, $out['format']);
        $this->assertSame(118.5, $out['meta']['bpm']);
        $this->assertSame('F#m', $out['meta']['key']);
        $this->assertSame('phrase', $out['meta']['lyrics_granularity']);

        $kinds = array_column($out['cues'], 'kind');
        $this->assertContains('chord', $kinds);
        $this->assertContains('lyric', $kinds);
        $this->assertCount(3, $out['cues']); // N skipped

        $first = $out['cues'][0];
        $this->assertSame('chord', $first['kind']);
        $this->assertSame('F#m', $first['name']);
        $this->assertSame(1200, $first['timeMs']);
        $this->assertSame(2300, $first['durationMs']);
        $this->assertSame(1, $first['channel']);
        $this->assertSame(60, $first['number']);
        $this->assertSame(100, $first['value']);
        $this->assertNotEmpty($first['id']);
    }

    public function test_analyze_kind_omits_lyrics(): void
    {
        $out = (new SongResultNormalizer)->normalize('magic_chords', [
            'segments' => [
                ['label' => 'C', 'start' => 0, 'end' => 1],
            ],
            'lines' => [
                ['text' => 'Hello', 'start' => 0.5, 'end' => 1],
            ],
        ], 'analyze');

        $this->assertSame(['chord'], array_column($out['cues'], 'kind'));
    }

    public function test_word_granularity_uses_nested_segment_words(): void
    {
        $out = (new SongResultNormalizer)->normalize('magic_chords', [
            'segments' => [
                [
                    'start' => 1.0,
                    'end' => 2.0,
                    'text' => 'Hey diamond',
                    'words' => [
                        ['start' => 1.0, 'end' => 1.3, 'word' => 'Hey'],
                        ['start' => 1.4, 'end' => 2.0, 'word' => 'diamond'],
                    ],
                ],
            ],
        ], 'transcribe', 'word');

        $lyrics = array_values(array_filter(
            $out['cues'],
            fn (array $c) => $c['kind'] === 'lyric'
        ));

        $this->assertSame(['Hey', 'diamond'], array_column($lyrics, 'name'));
        $this->assertSame(1000, $lyrics[0]['timeMs']);
        $this->assertSame(300, $lyrics[0]['durationMs']);
        $this->assertSame('word', $out['meta']['lyrics_granularity']);
    }

    public function test_uses_magic_chords_whisper_segment_text_as_lyric_cues(): void
    {
        $out = (new SongResultNormalizer)->normalize('magic_chords', [
            'segments' => [
                [
                    'start' => 34.9,
                    'end' => 38.12,
                    'text' => "Hey, I'm a diamond",
                    'words' => [
                        ['start' => 34.9, 'end' => 34.9, 'word' => ' Hey,'],
                        ['start' => 35.62, 'end' => 37.32, 'word' => " I'm"],
                        ['start' => 37.32, 'end' => 37.44, 'word' => ' a'],
                        ['start' => 37.44, 'end' => 38.12, 'word' => ' diamond'],
                    ],
                ],
                [
                    'start' => 40.36,
                    'end' => 43.26,
                    'text' => "I'm spinning clay",
                    'words' => [],
                ],
                [
                    'start' => 43.26,
                    'end' => 47.46,
                    'text' => 'I am the wheel',
                    'words' => [],
                ],
                [
                    'start' => 1.0,
                    'end' => 2.0,
                    'text' => 'Music',
                    'words' => [],
                ],
            ],
            // Flat words must be ignored when phrase segments exist
            'words' => [
                ['word' => 'should', 'start' => 0, 'end' => 1],
                ['word' => 'ignore', 'start' => 1, 'end' => 2],
            ],
        ], 'transcribe');

        $lyrics = array_values(array_filter(
            $out['cues'],
            fn (array $c) => $c['kind'] === 'lyric'
        ));

        $this->assertSame([
            "Hey, I'm a diamond",
            "I'm spinning clay",
            'I am the wheel',
        ], array_column($lyrics, 'name'));

        $this->assertSame(34900, $lyrics[0]['timeMs']);
        $this->assertSame(3220, $lyrics[0]['durationMs']);
        $this->assertSame(1, $lyrics[0]['channel']);
        $this->assertSame(60, $lyrics[0]['number']);
        $this->assertSame(100, $lyrics[0]['value']);
    }

    public function test_groups_words_into_full_lyric_lines_as_fallback(): void
    {
        $out = (new SongResultNormalizer)->normalize('magic_chords', [
            'words' => [
                ['word' => 'Music', 'start' => 0.0, 'end' => 0.4],
                ['word' => 'Hey,', 'start' => 1.0, 'end' => 1.2],
                ['word' => "I'm", 'start' => 1.25, 'end' => 1.4],
                ['word' => 'a', 'start' => 1.45, 'end' => 1.5],
                ['word' => 'diamond', 'start' => 1.55, 'end' => 2.0],
                ['word' => "I'm", 'start' => 2.7, 'end' => 2.85],
                ['word' => 'spinning', 'start' => 2.9, 'end' => 3.3],
                ['word' => 'clay', 'start' => 3.35, 'end' => 3.7],
            ],
        ], 'transcribe');

        $lyrics = array_values(array_filter(
            $out['cues'],
            fn (array $c) => $c['kind'] === 'lyric'
        ));

        $this->assertSame([
            "Hey, I'm a diamond",
            "I'm spinning clay",
        ], array_column($lyrics, 'name'));
    }
}
