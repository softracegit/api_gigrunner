<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MusicCreateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'audio.providers.elevenlabs.api_key' => 'test-elevenlabs-key',
            'audio.providers.elevenlabs.process_sync' => true,
            'audio.credit_cost.create' => 2,
        ]);
    }

    public function test_create_music_from_prompt(): void
    {
        Storage::fake('local');

        Http::fake([
            'api.elevenlabs.io/v1/music*' => Http::response(
                'FAKE_MP3_BYTES',
                200,
                [
                    'Content-Type' => 'audio/mpeg',
                    'song-id' => 'song-abc',
                ]
            ),
        ]);

        $user = $this->licensedUserWithCredits(5);
        $token = $user->createToken('test')->plainTextToken;

        $create = $this->postJson('/api/v1/music/create', [
            'prompt' => 'upbeat indie rock about chasing sunsets',
            'duration' => 12,
            'force_instrumental' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $create->assertCreated()
            ->assertJsonPath('job.endpoint', 'music/create')
            ->assertJsonPath('job.status', 'complete')
            ->assertJsonPath('job.options.force_instrumental', true)
            ->assertJsonPath('credits.ai', 3); // 5 - 2

        $uuid = $create->json('job.id');

        $result = $this->getJson('/api/v1/music/create/'.$uuid.'/result', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('result.provider', 'elevenlabs')
            ->assertJsonPath('result.song_id', 'song-abc')
            ->assertJsonPath('result.duration_ms', 12000);

        $audioUrl = $result->json('result.audio_url');
        $this->assertNotEmpty($audioUrl);

        $this->get($audioUrl)
            ->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/music')
                && $request->hasHeader('xi-api-key', 'test-elevenlabs-key')
                && ($request['prompt'] ?? null) === 'upbeat indie rock about chasing sunsets'
                && ($request['music_length_ms'] ?? null) === 12000
                && ($request['force_instrumental'] ?? null) === true;
        });
    }

    public function test_rejects_without_api_key(): void
    {
        config(['audio.providers.elevenlabs.api_key' => '']);

        $user = $this->licensedUserWithCredits(5);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/music/create', [
            'prompt' => 'calm piano',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(503);
    }

    public function test_does_not_call_analyze(): void
    {
        Storage::fake('local');

        Http::fake([
            'api.elevenlabs.io/v1/music*' => Http::response('x', 200, ['Content-Type' => 'audio/mpeg']),
            'magic-chords.dev/*' => Http::response(['unexpected' => true], 500),
        ]);

        $user = $this->licensedUserWithCredits(5);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/music/create', [
            'prompt' => 'lofi beat',
            'duration' => 10,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated()
            ->assertJsonPath('job.status', 'complete');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'magic-chords'));
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
