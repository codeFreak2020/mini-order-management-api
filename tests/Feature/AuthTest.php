<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '2015550123',
            'username' => 'johndoe',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'john@example.com')
            ->assertJsonPath('data.user.phone', '2015550123')
            ->assertJsonPath('data.user.username', 'johndoe')
            ->assertJsonStructure(['data' => ['user', 'token', 'refresh_token']]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'phone' => '2015550123',
            'username' => 'johndoe',
        ]);
    }

    public function test_registration_validates_input(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'phone' => '',
            'username' => '',
            'password' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $response = $this->postJson('/api/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['user', 'token', 'refresh_token']]);
    }

    public function test_user_can_login_with_phone(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $response = $this->postJson('/api/auth/login', [
            'identifier' => $user->phone,
            'password' => 'password',
        ]);
        $response->assertStatus(200)->assertJsonPath('data.user.id', $user->id);
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $response = $this->postJson('/api/auth/login', [
            'identifier' => $user->username,
            'password' => 'password',
        ]);
        $response->assertStatus(200)->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        $user = User::factory()->create();
        $response = $this->postJson('/api/auth/login', [
            'identifier' => $user->email,
            'password' => 'wrong-password',
        ]);
        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);
        $response = $this->withToken($token)->postJson('/api/auth/logout');
        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_user_can_refresh_token_pair(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $login = $this->postJson('/api/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ])->assertStatus(200);
        $refreshToken = $login->json('data.refresh_token');
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['user', 'token', 'refresh_token']]);
        $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_refresh_rejects_access_token(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $login = $this->postJson('/api/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ])->assertStatus(200);

        $this->postJson('/api/auth/refresh', [
            'refresh_token' => $login->json('data.token'),
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_refresh_requires_a_token(): void
    {
        $this->postJson('/api/auth/refresh', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/products')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'identifier' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }
        // The 6th request exceeds the auth limiter (5/min per IP).
        $this->postJson('/api/auth/login', [
            'identifier' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
