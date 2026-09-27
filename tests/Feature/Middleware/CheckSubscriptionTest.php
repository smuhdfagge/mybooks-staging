<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\CheckSubscription;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class CheckSubscriptionTest extends TestCase
{
    private function makeRequest(string $routeName = 'dashboard'): Request
    {
        $request = Request::create('/dashboard', 'GET');
        $route = new \Illuminate\Routing\Route('GET', '/dashboard', []);
        $route->name($routeName);
        $request->setRouteResolver(fn () => $route);
        return $request;
    }

    public function test_guest_passes_through(): void
    {
        $middleware = new CheckSubscription();
        $request = $this->makeRequest();

        $response = $middleware->handle($request, fn () => new Response('OK'));
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_user_without_tenant_gets_error(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);
        $this->actingAs($user);

        $middleware = new CheckSubscription();
        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => new Response('OK'));
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_user_without_subscription_gets_redirected(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        $middleware = new CheckSubscription();
        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => new Response('OK'));
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_user_with_active_subscription_passes(): void
    {
        $this->createAuthenticatedUser();

        $middleware = new CheckSubscription();
        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $this->user);

        $response = $middleware->handle($request, fn () => new Response('OK'));
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_user_with_cancelled_subscription_gets_redirected(): void
    {
        $this->createAuthenticatedUser();
        $this->subscription->update(['status' => Subscription::STATUS_CANCELLED]);

        $middleware = new CheckSubscription();
        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $this->user->fresh());

        $response = $middleware->handle($request, fn () => new Response('OK'));
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_exempt_routes_pass_without_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        $middleware = new CheckSubscription();

        $exemptRoutes = ['profile.edit', 'profile.update', 'profile.destroy', 'settings.subscription', 'logout'];

        foreach ($exemptRoutes as $routeName) {
            $request = $this->makeRequest($routeName);
            $request->setUserResolver(fn () => $user);

            $response = $middleware->handle($request, fn () => new Response('OK'));
            $this->assertEquals(
                'OK',
                $response->getContent(),
                "Route '$routeName' should be exempt but was blocked"
            );
        }
    }

    public function test_json_request_gets_json_response(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        $middleware = new CheckSubscription();
        $request = Request::create('/api/invoices', 'GET');
        $request->headers->set('Accept', 'application/json');
        $route = new \Illuminate\Routing\Route('GET', '/api/invoices', []);
        $route->name('api.invoices.index');
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['subscription_required']);
    }
}
