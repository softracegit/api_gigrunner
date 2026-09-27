<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_inactive_by_default(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->getJson('/api/v1/license', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('license.valid', false)
            ->assertJsonPath('license.status', 'inactive');
    }

    public function test_license_valid_when_active(): void
    {
        $user = User::factory()->create();
        $user->licenses()->create([
            'plan' => 'trial',
            'status' => License::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->getJson('/api/v1/license', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('license.valid', true)
            ->assertJsonPath('license.plan', 'trial');
    }

    public function test_expired_license_is_not_valid(): void
    {
        $user = User::factory()->create();
        $user->licenses()->create([
            'plan' => 'standard',
            'status' => License::STATUS_ACTIVE,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->getJson('/api/v1/license', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('license.valid', false);
    }

    public function test_login_includes_license(): void
    {
        $user = User::factory()->create([
            'email' => 'licensed@gigrunner.test',
            'password' => 'password123',
        ]);
        $user->licenses()->create([
            'plan' => 'standard',
            'status' => License::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => null,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'licensed@gigrunner.test',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('license.valid', true)
            ->assertJsonStructure(['user', 'license', 'token']);
    }

    public function test_activate_test_license(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/license/activate-test', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated()
            ->assertJsonPath('license.valid', true)
            ->assertJsonPath('license.plan', 'trial');

        $this->postJson('/api/v1/license/revoke', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('license.valid', false)
            ->assertJsonPath('license.status', 'revoked');
    }
}
