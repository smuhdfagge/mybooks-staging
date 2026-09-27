<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_404_page_renders(): void
    {
        $response = $this->get('/this-page-does-not-exist-' . uniqid());
        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Page Not Found');
    }

    public function test_403_page_renders(): void
    {
        // Access a protected route without auth
        $this->createAuthenticatedUser();

        // Force a 403 by calling abort
        $response = $this->get('/test-forbidden-page');
        // Route doesn't exist so we get 404. Instead test view directly.
        $view = $this->view('errors.403', ['exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(403)]);
        $view->assertSee('403');
    }

    public function test_error_views_exist(): void
    {
        $this->assertTrue(view()->exists('errors.403'));
        $this->assertTrue(view()->exists('errors.404'));
        $this->assertTrue(view()->exists('errors.419'));
        $this->assertTrue(view()->exists('errors.429'));
        $this->assertTrue(view()->exists('errors.500'));
        $this->assertTrue(view()->exists('errors.503'));
    }

    public function test_419_view_renders(): void
    {
        $view = $this->view('errors.419', ['exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(419)]);
        $view->assertSee('419');
        $view->assertSee('Page Expired');
    }

    public function test_429_view_renders(): void
    {
        $view = $this->view('errors.429', ['exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(429)]);
        $view->assertSee('429');
        $view->assertSee('Too Many Requests');
    }

    public function test_500_view_renders(): void
    {
        $view = $this->view('errors.500', ['exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(500)]);
        $view->assertSee('500');
        $view->assertSee('Server Error');
    }

    public function test_503_view_renders(): void
    {
        $view = $this->view('errors.503', ['exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(503)]);
        $view->assertSee('503');
        $view->assertSee('Service Unavailable');
    }
}
