<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * An unauthenticated API request has one answer: 401 with a JSON body.
 *
 * The bug this file exists for was that a request without an
 * Accept: application/json header did not get that answer. The Authenticate
 * middleware redirects to route('login') when it has no redirect target, this
 * application has no login route, and resolving that route threw. The result was
 * a 500 that, with APP_DEBUG on, returned a stack trace for what was only a
 * request without a token.
 *
 * Most cases here therefore send no Accept header at all, which is the case that
 * failed, and assert the 401 alongside the ones that already worked.
 */
class UnauthenticatedApiResponseTest extends TestCase
{
    public static function protectedEndpointProvider(): array
    {
        return [
            'dashboard' => ['get', '/api/v1/dashboard'],
            'categories' => ['get', '/api/v1/categories'],
            'projects' => ['get', '/api/v1/projects'],
            'ideas' => ['get', '/api/v1/ideas'],
            'assets of a project' => ['get', '/api/v1/projects/1/assets'],
            'one asset' => ['get', '/api/v1/projects/1/assets/1'],
            'create an asset' => ['post', '/api/v1/projects/1/assets'],
            'update an asset' => ['patch', '/api/v1/projects/1/assets/1'],
            'transition an asset' => ['patch', '/api/v1/projects/1/assets/1/status'],
            'upload an asset file' => ['post', '/api/v1/projects/1/assets/1/file'],
            'analyze an asset' => ['post', '/api/v1/projects/1/assets/1/analyze'],
            'preview an asset' => ['get', '/api/v1/projects/1/assets/1/preview'],
            'delete an asset' => ['delete', '/api/v1/projects/1/assets/1'],
            'who am i' => ['get', '/api/v1/auth/me'],
            'log out' => ['post', '/api/v1/auth/logout'],
            'project status' => ['patch', '/api/v1/projects/1/status'],
            'research' => ['get', '/api/v1/projects/1/research'],
            'production specification' => ['get', '/api/v1/projects/1/script/versions/1/visual-plan/production-spec'],
        ];
    }

    #[DataProvider('protectedEndpointProvider')]
    public function test_an_endpoint_without_a_token_answers_401_json_without_an_accept_header(string $method, string $uri): void
    {
        // call() rather than json(), because json() adds Accept: application/json
        // and would hide the exact case that used to answer 500.
        $response = $this->call(strtoupper($method), $uri);

        $response->assertStatus(401);
        $response->assertHeader('content-type', 'application/json');
        $this->assertSame('Unauthenticated.', $response->json('message'));
    }

    #[DataProvider('protectedEndpointProvider')]
    public function test_an_endpoint_without_a_token_answers_the_same_401_with_an_accept_header(string $method, string $uri): void
    {
        $response = $this->json($method, $uri, [], ['Accept' => 'application/json']);

        $response->assertStatus(401);
        $response->assertHeader('content-type', 'application/json');
        $this->assertSame('Unauthenticated.', $response->json('message'));
    }

    #[Test]
    public function test_an_unauthenticated_401_leaks_no_debug_output(): void
    {
        $content = $this->get('/api/v1/dashboard')->assertStatus(401)->getContent();

        // With APP_DEBUG on, an unhandled exception returns the trace and the
        // frame list. Neither may appear in a response to a caller who simply
        // has not logged in.
        $this->assertStringNotContainsString('vendor/laravel', $content);
        $this->assertStringNotContainsString('Illuminate\\', $content);
        $this->assertStringNotContainsString('route(', $content);
        $this->assertStringNotContainsString('#0 ', $content);
    }

    #[Test]
    public function test_the_401_body_names_the_problem_and_does_not_pretend_to_be_a_resource(): void
    {
        $response = $this->get('/api/v1/dashboard')->assertStatus(401);

        $this->assertSame(['message' => 'Unauthenticated.'], $response->json());
    }

    #[Test]
    public function test_a_public_endpoint_stays_reachable_without_a_token(): void
    {
        $this->getJson('/api/v1/health')->assertOk();

        // Login is how a caller obtains a token in the first place, so the
        // middleware answer must not be able to reach it. Refused credentials
        // answer 401 with the controller's own message, which is a different
        // failure from a request that never had a chance to authenticate.
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    #[Test]
    public function test_a_web_path_is_not_answered_with_a_401(): void
    {
        // The API contract does not extend to the web shell. A web request that
        // reached the handler without a session still has to be the shell, not a
        // JSON error about a token this application never issues.
        $this->withoutVite();

        $this->get('/dashboard')->assertOk();
        $this->get('/up')->assertOk();
    }
}
