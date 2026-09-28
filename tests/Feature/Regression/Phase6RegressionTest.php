<?php

namespace Tests\Feature\Regression;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 6: unfinished modules (N4, N5, N7, N8, N9).
 */
class Phase6RegressionTest extends TestCase
{
    public function test_n4_unfinished_modules_are_off_by_default(): void
    {
        foreach (config('mybooks.features') as $feature => $on) {
            $this->assertFalse($on, "{$feature} should be off by default");
        }
    }

    public function test_n4_unfinished_module_urls_answer_404(): void
    {
        $this->createSuperAdmin();
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $features = array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'feature:'));
            if (! $features || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $this->get('/'.ltrim($uri, '/'))->assertNotFound();
            $checked++;
        }

        $this->assertGreaterThanOrEqual(20, $checked);
    }

    public function test_n4_module_works_again_when_switched_on(): void
    {
        config(['mybooks.features.warehouses' => true]);
        $this->createSuperAdmin();

        $this->assertNotSame(404, $this->get('/warehouses/create')->getStatusCode());
    }
}
