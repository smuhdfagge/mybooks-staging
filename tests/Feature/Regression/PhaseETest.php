<?php

namespace Tests\Feature\Regression;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Round 3, Phase E: platform upgrade.
 */
class PhaseETest extends TestCase
{
    public function test_e_runs_on_laravel_13_livewire_4_and_spatie_8(): void
    {
        $this->assertTrue(version_compare(app()->version(), '13.0.0', '>='), app()->version());
        $this->assertTrue(version_compare(InstalledVersions::getVersion('livewire/livewire'), '4.0.0', '>='));
        $this->assertTrue(version_compare(InstalledVersions::getVersion('spatie/laravel-permission'), '8.0.0', '>='));
    }

    public function test_e_the_cache_never_rebuilds_objects(): void
    {
        // Laravel 13 hardening: a cache entry can't be turned back into an
        // object, so a tampered cache can't inject one.
        config(['cache.default' => 'file']);
        Cache::put('phase-e-object', new \ArrayObject(['a' => 1]), 60);

        $this->assertNotInstanceOf(\ArrayObject::class, Cache::get('phase-e-object'));
        Cache::forget('phase-e-object');
    }

    public function test_e_cached_dashboard_figures_still_load_from_the_cache(): void
    {
        config(['cache.default' => 'file']);
        Cache::flush();
        $this->createAuthenticatedUser(['view dashboard', 'total-revenue dashboard-widgets', 'outstanding-receivables dashboard-widgets', 'monthly-expenses dashboard-widgets']);

        $first = $this->get(route('dashboard'))->assertOk()->getContent();
        $second = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertSame(substr_count($first, '₦'), substr_count($second, '₦'));
        Cache::flush();
    }
}
