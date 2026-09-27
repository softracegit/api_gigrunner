<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_me_and_logout(): void
    {
        $register = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@gigrunner.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $register->assertCreated()
            ->assertJsonPath('user.email', 'test@gigrunner.test')
            ->assertJsonStructure(['user' => ['id', 'uuid', 'name', 'email'], 'token']);

        $token = $register->json('token');

        $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('user.email', 'test@gigrunner.test');

        $login = $this->postJson('/api/v1/login', [
            'email' => 'test@gigrunner.test',
            'password' => 'password123',
        ]);

        $login->assertOk()->assertJsonStructure(['user', 'token']);

        $this->postJson('/api/v1/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()->assertJsonPath('ok', true);

        // Fresh guard state — token was revoked
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer '.$token,
        ])->assertUnauthorized();
    }

    public function test_login_rejects_bad_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@gigrunner.test',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'test@gigrunner.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }
}
