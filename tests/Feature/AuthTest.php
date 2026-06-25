<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Amr Ahmed',
            'email' => 'amr@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'User registered successfully.')
            ->assertJsonPath('data.user.email', 'amr@example.com')
            ->assertJsonStructure([
                'data' => [
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'amr@example.com',
        ]);
    }

    public function test_user_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'amr@example.com',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Amr Ahmed',
            'email' => 'amr@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'amr@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'amr@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonStructure([
                'data' => [
                    'token',
                ],
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'amr@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'amr@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_protected_endpoints_reject_unauthenticated_requests(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_profile_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'amr@example.com',
        ]);

        $token = auth('api')->login($user);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'amr@example.com');

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');
    }
}
