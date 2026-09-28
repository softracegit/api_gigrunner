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
                ['text' => "Line one\nLine two", 'start' => 1.5, 'end' => 4.2],
            ],
        ], 'analyze');

        $this->assertSame(1, $out['format']);
        $this->assertSame(118.5, $out['meta']['bpm']);
        $this->assertSame('F#m', $out['meta']['key']);

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

    public function test_groups_words_into_phrase_cues_with_line_breaks(): void
    {
        $out = (new SongResultNormalizer)->normalize('magic_chords', [
            'words' => [
                ['word' => 'Ticking', 'start' => 10.0, 'end' => 10.3],
                ['word' => 'away', 'start' => 10.35, 'end' => 10.6],
                ['word' => 'the', 'start' => 10.65, 'end' => 10.8],
                ['word' => 'moments', 'start' => 10.85, 'end' => 11.3],
                // medium gap → new line inside same phrase
                ['word' => 'That', 'start' => 11.7, 'end' => 11.9],
                ['word' => 'make', 'start' => 11.95, 'end' => 12.1],
                ['word' => 'up', 'start' => 12.15, 'end' => 12.3],
                ['word' => 'a', 'start' => 12.35, 'end' => 12.4],
                ['word' => 'dull', 'start' => 12.45, 'end' => 12.7],
                ['word' => 'day', 'start' => 12.75, 'end' => 13.0],
                // long gap → new phrase
                ['word' => 'Fritter', 'start' => 14.5, 'end' => 14.9],
                ['word' => 'and', 'start' => 14.95, 'end' => 15.1],
                ['word' => 'waste', 'start' => 15.15, 'end' => 15.5],
            ],
        ], 'transcribe');

        $lyrics = array_values(array_filter(
            $out['cues'],
            fn (array $c) => $c['kind'] === 'lyric'
        ));

        $this->assertCount(2, $lyrics);
        $this->assertSame("Ticking away the moments\nThat make up a dull day", $lyrics[0]['name']);
        $this->assertSame(10000, $lyrics[0]['timeMs']);
        $this->assertSame(3000, $lyrics[0]['durationMs']);
        $this->assertSame('Fritter and waste', $lyrics[1]['name']);
    }
}
