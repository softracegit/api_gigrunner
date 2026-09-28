<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioJobApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_analyze_job_from_url_and_poll_result(): void
    {
        Http::fake([
            'https://magic-chords.dev/api/v1/analyze/url' => Http::response([
                'job_id' => 'ext-job-1',
                'status' => 'processing',
            ], 200),
            'https://magic-chords.dev/api/v1/jobs/ext-job-1' => Http::sequence()
                ->push([
                    'job_id' => 'ext-job-1',
                    'status' => 'processing',
                    'progress_percentage' => 40,
                    'message' => 'Detecting tempo…',
                ], 200)
                ->push([
                    'job_id' => 'ext-job-1',
                    'status' => 'complete',
                    'progress_percentage' => 100,
                    'message' => 'Done',
                ], 200),
            'https://magic-chords.dev/api/v1/jobs/ext-job-1/result' => Http::response([
                'job_id' => 'ext-job-1',
                'status' => 'complete',
                'tempo' => 120,
                'key' => 'C',
                'segments' => [
                    ['label' => 'C', 'start' => 0, 'end' => 2, 'confidence' => 1],
                    ['label' => 'Am', 'start' => 2, 'end' => 4, 'confidence' => 1],
                ],
                'words' => [
                    ['word' => 'Hello', 'start' => 0.5, 'end' => 1.0],
                ],
            ], 200),
        ]);

        $user = $this->licensedUserWithCredits(5);
        $token = $user->createToken('test')->plainTextToken;

        $create = $this->postJson('/api/v1/audio/jobs', [
            'url' => 'https://example.com/song.mp3',
            'kind' => 'analyze',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $create->assertCreated()
            ->assertJsonPath('job.status', 'processing')
            ->assertJsonPath('credits.ai', 4);

        $uuid = $create->json('job.id');

        $this->getJson('/api/v1/audio/jobs/'.$uuid, [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('job.progress', 40);

        $this->getJson('/api/v1/audio/jobs/'.$uuid.'/result', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('job.status', 'complete')
            ->assertJsonPath('result.format', 1)
            ->assertJsonPath('result.meta.bpm', 120)
            ->assertJsonPath('result.meta.key', 'C')
            ->assertJsonPath('result.cues.0.kind', 'chord')
            ->assertJsonPath('result.cues.0.name', 'C')
            ->assertJsonPath('result.cues.0.timeMs', 0)
            ->assertJsonPath('result.cues.0.durationMs', 2000)
            ->assertJsonPath('result.cues.0.channel', 1)
            ->assertJsonPath('result.cues.1.name', 'Hello')
            ->assertJsonPath('result.cues.1.kind', 'lyric');
    }

    public function test_lists_user_audio_jobs(): void
    {
        Http::fake([
            'https://magic-chords.dev/api/v1/analyze/url' => Http::response([
                'job_id' => 'ext-list-1',
                'status' => 'processing',
            ], 200),
        ]);

        $user = $this->licensedUserWithCredits(5);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/audio/jobs', [
            'url' => 'https://example.com/my-song.mp3',
            'kind' => 'analyze',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated();

        $this->getJson('/api/v1/audio/jobs', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('jobs.0.source.type', 'url')
            ->assertJsonPath('jobs.0.source.name', 'my-song.mp3')
            ->assertJsonPath('jobs.0.source.url', 'https://example.com/my-song.mp3');
    }

    public function test_rejects_without_license(): void
    {
        $user = User::factory()->create();
        // credits but no license
        $this->postJson('/api/v1/credits/purchase-test', [
            'pack' => 'ai_50',
        ], [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
        ]);

        $token = $user->createToken('test2')->plainTextToken;

        $this->postJson('/api/v1/audio/jobs', [
            'url' => 'https://example.com/song.mp3',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertForbidden();
    }

    public function test_create_from_upload(): void
    {
        Storage::fake('local');

        Http::fake([
            'https://magic-chords.dev/api/v1/analyze' => Http::response([
                'job_id' => 'ext-upload-1',
                'status' => 'processing',
            ], 200),
        ]);

        $user = $this->licensedUserWithCredits(3);
        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->create('demo.mp3', 100, 'audio/mpeg');

        $this->post('/api/v1/audio/jobs', [
            'kind' => 'analyze',
            'file' => $file,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->assertCreated()
            ->assertJsonPath('job.kind', 'analyze')
            ->assertJsonPath('credits.ai', 2);
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

        // Purchase enough AI via pack then adjust if needed
        $token = $user->createToken('seed')->plainTextToken;
        $this->postJson('/api/v1/credits/purchase-test', [
            'pack' => 'ai_50',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated();

        if ($aiCredits < 50) {
            // burn extras for predictable assertions
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
