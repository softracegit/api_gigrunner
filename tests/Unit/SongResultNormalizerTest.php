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
}
