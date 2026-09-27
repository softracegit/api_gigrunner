<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_standard_license_grants_ai_credits(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/credits/purchase-test', [
            'pack' => 'license_standard',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated()
            ->assertJsonPath('credits.ai', 50)
            ->assertJsonPath('license.valid', true)
            ->assertJsonPath('license.plan', 'standard');

        $this->getJson('/api/v1/credits', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('credits.ai', 50);
    }

    public function test_consume_ai_and_reject_when_empty(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/credits/purchase-test', [
            'pack' => 'ai_50',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated();

        $this->postJson('/api/v1/credits/consume', [
            'type' => 'ai',
            'amount' => 1,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('balance', 49);

        // Drain
        $this->postJson('/api/v1/credits/consume', [
            'type' => 'ai',
            'amount' => 49,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('balance', 0);

        $this->postJson('/api/v1/credits/consume', [
            'type' => 'ai',
            'amount' => 1,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(402)
            ->assertJsonPath('credits.ai', 0);
    }

    public function test_me_includes_credits(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonStructure(['user', 'license', 'credits']);
    }
}
