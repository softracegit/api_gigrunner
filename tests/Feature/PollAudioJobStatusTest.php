<?php

namespace Tests\Feature;

use App\Jobs\PollAudioJobStatus;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollAudioJobStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_analyze_job_queues_status_poller(): void
    {
        Queue::fake();
        config(['audio.poll.enabled' => true]);

        Http::fake([
            'https://magic-chords.dev/api/v1/analyze/url' => Http::response([
                'job_id' => 'ext-1',
                'status' => 'processing',
                'message' => 'ok',
            ], 200),
        ]);

        $user = $this->licensedUserWithCredits(5);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/music/analyze', [
            'url' => 'https://example.com/song.mp3',
            'options' => ['chords' => true, 'lyrics' => false],
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated();

        Queue::assertPushed(PollAudioJobStatus::class);
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
