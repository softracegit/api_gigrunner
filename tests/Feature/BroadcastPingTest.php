<?php

namespace Tests\Feature;

use App\Events\AudioJobStatusUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BroadcastPingTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_ping_creates_job_and_dispatches_event(): void
    {
        Event::fake([AudioJobStatusUpdated::class]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/broadcast/ping')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['channel', 'event', 'job' => ['id', 'status']]);

        Event::assertDispatched(AudioJobStatusUpdated::class);
    }

    public function test_private_channel_auth_allows_owner(): void
    {
        Event::fake([AudioJobStatusUpdated::class]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $ping = $this->postJson('/api/v1/broadcast/ping')->assertOk();
        $uuid = $ping->json('job.id');

        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-audio-jobs.'.$uuid,
            'socket_id' => '1234.5678',
        ])->assertOk();
    }
}
