<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctumAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'creator@mochyfami.studio',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'creator@mochyfami.studio',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged in successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
                    'token',
                ],
                'message',
            ]);

        $this->assertNotNull($response->json('data.token'));
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'creator@mochyfami.studio',
            'password' => Hash::make('correctpassword'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'creator@mochyfami.studio',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'data' => null,
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_login_validation_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $user = User::factory()->create([
            'name' => 'MochyFami Creator',
            'email' => 'creator@mochyfami.studio',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => 'MochyFami Creator',
                    'email' => 'creator@mochyfami.studio',
                ],
                'message' => null,
            ]);
    }

    public function test_unauthenticated_user_cannot_access_me_endpoint(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);
    }

    public function test_passwords_and_sensitive_fields_are_never_exposed(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secretpassword'),
            'remember_token' => 'secretremembertoken',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $responseData = $response->json('data');

        $this->assertArrayNotHasKey('password', $responseData);
        $this->assertArrayNotHasKey('remember_token', $responseData);
    }
}
