<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The web side of the studio is a single page application, so the server's only
 * job for a web path is to return the shell React then resolves on the client.
 *
 * These tests cover that contract rather than React itself: the paths the
 * frontend routes to must come back as the shell instead of a 404, and an
 * unmatched API path must stay a 404 rather than being handed HTML.
 *
 * Vite is disabled throughout. The shell is produced by the build, not by
 * routing, and requiring a built manifest would make these tests depend on
 * whether npm has been run.
 */
class SpaFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function spaPathProvider(): array
    {
        return [
            'root' => ['/'],
            'dashboard' => ['/dashboard'],
            'ideas' => ['/ideas'],
            'categories' => ['/categories'],
            'projects' => ['/projects'],
            'a project detail page' => ['/projects/1'],
            'a nested project page' => ['/projects/1/assets'],
            'an asset detail page' => ['/projects/1/assets/1'],
            'assets' => ['/assets'],
            'login' => ['/login'],
            'register' => ['/register'],
            'settings' => ['/settings'],
            'a path with a trailing slash' => ['/dashboard/'],
            'an unknown path' => ['/this-does-not-exist'],
            'a deeply nested unknown path' => ['/one/two/three/four'],
        ];
    }

    #[DataProvider('spaPathProvider')]
    public function test_a_web_path_is_answered_with_the_application_shell(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('id="app"', $response->getContent());
    }

    #[DataProvider('spaPathProvider')]
    public function test_a_web_path_is_answered_with_the_same_shell_as_the_root(string $path): void
    {
        // React resolves the path itself, so every path has to arrive at the
        // identical shell. A path that rendered even slightly differently would
        // be a second page the frontend does not know about.
        $this->assertSame($this->get('/')->getContent(), $this->get($path)->getContent());
    }

    #[Test]
    public function test_an_unmatched_api_path_is_still_a_json_404(): void
    {
        $response = $this->getJson('/api/v1/there-is-no-such-endpoint');

        $response->assertNotFound();
        $this->assertStringNotContainsString('id="app"', $response->getContent());
    }

    #[Test]
    public function test_an_unmatched_nested_api_path_is_still_a_json_404(): void
    {
        $this->getJson('/api/v1/projects/1/assets/1/nope')->assertNotFound();
        $this->getJson('/api/v2/anything')->assertNotFound();
    }

    #[Test]
    public function test_an_unmatched_api_path_is_a_404_even_without_a_json_accept_header(): void
    {
        // Without the Accept header this request is not treated as wanting JSON,
        // so it is the case where an HTML shell would slip through unnoticed.
        $response = $this->get('/api/v1/there-is-no-such-endpoint');

        $response->assertNotFound();
        $this->assertStringNotContainsString('id="app"', $response->getContent());
    }

    #[Test]
    public function test_the_health_endpoint_is_not_shadowed_by_the_fallback(): void
    {
        $this->get('/up')->assertOk();
    }

    #[Test]
    public function test_the_fallback_answers_the_method_the_frontend_uses_for_navigation(): void
    {
        // A refresh and an in-app link are both GETs, but a form post to an
        // unknown web path must not be turned into a page either: it has no
        // route to handle it and answering 200 would hide that.
        $this->post('/not-a-real-page')->assertStatus(405);
    }

    #[Test]
    public function test_a_head_request_gets_the_same_answer_as_a_get(): void
    {
        // Symfony matched this route for HEAD by falling back to the GET verbs,
        // so the handler has to agree that a HEAD is a read. Refusing it would
        // break a link checker or a monitoring probe on a page that is
        // perfectly reachable, and would contradict the routing decision.
        $head = $this->call('HEAD', '/dashboard');

        $this->assertSame(
            $this->get('/dashboard')->getStatusCode(),
            $head->getStatusCode()
        );
        $this->assertNotSame(405, $head->getStatusCode());
    }

    #[Test]
    public function test_the_fallback_is_excluded_from_csrf_verification(): void
    {
        // Asserted as route configuration because it cannot be asserted through a
        // request: PreventRequestForgery skips verification whenever the app is
        // running tests, so an unmatched API POST answers 404 here and 419 on a
        // real server. Only the route itself records the difference.
        $fallback = collect(Route::getRoutes()->getRoutes())
            ->first(fn (IlluminateRoute $route) => $route->isFallback);

        $this->assertNotNull($fallback, 'No fallback route is registered.');
        $this->assertContains(
            PreventRequestForgery::class,
            $fallback->excludedMiddleware() ?? [],
            'CSRF verification runs on the fallback and turns a missing endpoint into a 419.'
        );
    }

    #[Test]
    public function test_the_fallback_is_registered_for_every_method(): void
    {
        $fallback = collect(Route::getRoutes()->getRoutes())
            ->first(fn (IlluminateRoute $route) => $route->isFallback);

        $this->assertNotNull($fallback);

        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $this->assertContains(
                $method,
                $fallback->methods(),
                "The fallback does not answer {$method}, so a missing endpoint answers 405 {$method} instead of 404."
            );
        }
    }

    #[DataProvider('apiMethodProvider')]
    public function test_an_unmatched_api_path_is_404_for_every_method(string $method): void
    {
        // Symfony answers 405 with an Allow header naming the methods a path does
        // accept. For a path this server does not have at all, that would claim
        // a read is possible where none is, so the API keeps answering 404.
        $this->json($method, '/api/v1/there-is-no-such-endpoint')->assertNotFound();
        $this->json($method, '/api/v1/projects/1/assets/1/there-is-no-such-endpoint')->assertNotFound();
    }

    public static function apiMethodProvider(): array
    {
        return [
            'get' => ['get'],
            'head' => ['head'],
            'post' => ['post'],
            'put' => ['put'],
            'patch' => ['patch'],
            'delete' => ['delete'],
            'options' => ['options'],
        ];
    }
}
