<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class MusicAnalyzeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_with_chords_and_lyrics(): void
    {
        Http::fake([
            'https://magic-chords.dev/api/v1/analyze/url' => Http::response([
                'job_id' => 'mc-analyze',
                'status' => 'processing',
            ], 200),
            'https://magic-chords.dev/api/v1/transcribe/url' => Http::response([
                'job_id' => 'mc-transcribe',
                'status' => 'processing',
            ], 200),
            'https://magic-chords.dev/api/v1/jobs/mc-analyze' => Http::response([
                'job_id' => 'mc-analyze',
                'status' => 'complete',
                'progress_percentage' => 100,
            ], 200),
            'https://magic-chords.dev/api/v1/jobs/mc-transcribe' => Http::response([
                'job_id' => 'mc-transcribe',
                'status' => 'complete',
                'progress_percentage' => 100,
            ], 200),
            'https://magic-chords.dev/api/v1/jobs/mc-analyze/result' => Http::response([
                'tempo' => 110,
                'key' => 'Am',
                'segments' => [
                    ['label' => 'Am', 'start' => 0, 'end' => 2],
                ],
            ], 200),
            'https://magic-chords.dev/api/v1/jobs/mc-transcribe/result' => Http::response([
                'segments' => [
                    ['text' => 'Hello world', 'start' => 0.5, 'end' => 2.0],
                ],
            ], 200),
        ]);

        $user = $this->licensedUserWithCredits(5);
        $token = $user->createToken('test')->plainTextToken;

        $create = $this->postJson('/api/v1/music/analyze', [
            'url' => 'https://example.com/song.mp3',
            'options' => [
                'chords' => true,
                'lyrics' => true,
                'lyrics_granularity' => 'phrase',
            ],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $create->assertCreated()
            ->assertJsonPath('job.endpoint', 'music/analyze')
            ->assertJsonPath('job.options.chords', true)
            ->assertJsonPath('job.options.lyrics', true)
            ->assertJsonPath('credits.ai', 3);

        $uuid = $create->json('job.id');

        $result = $this->getJson('/api/v1/music/analyze/'.$uuid.'/result', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $names = array_column($result->json('result.cues'), 'name');
        $this->assertContains('Am', $names);
        $this->assertContains('Hello world', $names);
    }

    public function test_stems_only_via_elevenlabs(): void
    {
        Storage::fake('local');
        config([
            'audio.providers.elevenlabs.api_key' => 'test-key',
            'audio.credit_cost.stems' => 2,
        ]);

        Http::fake([
            'https://example.com/song.mp3' => Http::response('SOURCE_MP3', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
            'api.elevenlabs.io/v1/music/stem-separation*' => Http::response(
                $this->fakeStemZip(['vocals.mp3' => 'VOX', 'drums.mp3' => 'DRM', 'bass.mp3' => 'BAS']),
                200,
                ['Content-Type' => 'application/zip']
            ),
        ]);

        $user = $this->licensedUserWithCredits(5);
        $token = $user->createToken('test')->plainTextToken;

        $create = $this->postJson('/api/v1/music/analyze', [
            'url' => 'https://example.com/song.mp3',
            'options' => [
                'separate_stems' => ['vocals', 'drums'],
            ],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $create->assertCreated()
            ->assertJsonPath('job.options.chords', false)
            ->assertJsonPath('job.options.lyrics', false)
            ->assertJsonPath('job.task_status.stems.status', 'complete')
            ->assertJsonPath('credits.ai', 3); // 5 - 2

        $result = $this->getJson('/api/v1/music/analyze/'.$create->json('job.id').'/result', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $stems = $result->json('result.stems');
        $this->assertArrayHasKey('vocals', $stems);
        $this->assertArrayHasKey('drums', $stems);
        $this->assertArrayNotHasKey('bass', $stems);
        $this->assertNotEmpty($stems['vocals']['url']);

        $this->get($stems['vocals']['url'])->assertOk();
    }

    public function test_requires_at_least_one_option(): void
    {
        $user = $this->licensedUserWithCredits(3);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/music/analyze', [
            'url' => 'https://example.com/song.mp3',
            'options' => [
                'chords' => false,
                'lyrics' => false,
            ],
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(422);
    }

    public function test_chords_only(): void
    {
        Http::fake([
            'https://magic-chords.dev/api/v1/analyze/url' => Http::response([
                'job_id' => 'mc-only',
                'status' => 'complete',
            ], 200),
            'https://magic-chords.dev/api/v1/jobs/mc-only' => Http::response([
                'status' => 'complete',
                'progress_percentage' => 100,
            ], 200),
            'https://magic-chords.dev/api/v1/jobs/mc-only/result' => Http::response([
                'segments' => [
                    ['label' => 'C', 'start' => 0, 'end' => 1],
                ],
            ], 200),
        ]);

        $user = $this->licensedUserWithCredits(3);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/music/analyze', [
            'url' => 'https://example.com/song.mp3',
            'options' => [
                'chords' => true,
                'lyrics' => false,
            ],
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated()
            ->assertJsonPath('job.options.chords', true)
            ->assertJsonPath('job.options.lyrics', false)
            ->assertJsonPath('credits.ai', 2);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function fakeStemZip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        $bytes = file_get_contents($path);
        @unlink($path);

        return $bytes ?: '';
    }

    private function licensedUserWithCredits(int $aiCredits): User
    {
        $user = User::factory()->create();
        $user->licenses()->create([
            'plan' => 'standard',
            'status' => License::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => null,
        ]);

        $token = $user->createToken('seed')->plainTextToken;
        $this->postJson('/api/v1/credits/purchase-test', [
            'pack' => 'ai_50',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated();

        if ($aiCredits < 50) {
            $this->postJson('/api/v1/credits/consume', [
                'type' => 'ai',
                'amount' => 50 - $aiCredits,
            ], [
                'Authorization' => 'Bearer '.$token,
            ])->assertOk();
        }

        return $user->fresh();
    }
}
