<?php

namespace Tests\Feature\Regression;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Catches routes that point at controller methods that don't exist and
 * controllers that render views that don't exist (findings N4 and N9).
 *
 * The KNOWN_* lists hold the problems that already existed when this test
 * was added. They are fixed in Phase 6 of the fix plan; remove each entry
 * as it is fixed. The test fails if a new problem appears, and also if a
 * listed problem has been fixed but is still on the list.
 */
class RouteIntegrityTest extends TestCase
{
    private const KNOWN_MISSING_METHODS = [
        'App\Http\Controllers\Api\ReportController@payrollRegister',
        'App\Http\Controllers\Api\ReportController@ytdEarnings',
        'App\Http\Controllers\Api\ReportController@taxLiabilityPayroll',
        'App\Http\Controllers\Api\ReportController@employerContributions',
        'App\Http\Controllers\Api\ReportController@bankDisbursement',
        'App\Http\Controllers\Api\ReportController@salaryRevisionHistory',
    ];

    private const KNOWN_MISSING_VIEWS = [
        // Unrouted TenantManagementController (L3, to be deleted)
        'tenants.index', 'tenants.list', 'tenants.show',
    ];

    /**
     * Views of unfinished modules (N4). They may be missing only while the
     * module is switched off by default in config/mybooks.php.
     */
    private const FEATURE_VIEWS = [
        'credit_notes' => ['credit-notes.'],
        'delivery_notes' => ['delivery-notes.'],
        'assembly' => ['inventory.assembly.', 'inventory.bom.'],
        'stock_transfers' => ['inventory.transfers.'],
        'inventory_valuation' => ['inventory.valuation'],
        'warehouses' => ['inventory.warehouses.'],
        'quotations' => ['quotations.'],
    ];

    /** Missing views that belong to a module that is switched off. */
    private function hiddenModuleViews(array $missing): array
    {
        return array_values(array_filter($missing, function ($view) {
            foreach (self::FEATURE_VIEWS as $feature => $prefixes) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($view, $prefix) && ! config("mybooks.features.{$feature}")) {
                        return true;
                    }
                }
            }

            return false;
        }));
    }

    public function test_every_route_points_at_an_existing_controller_method(): void
    {
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action);
            if (! class_exists($class) || ! method_exists($class, $method)) {
                $missing[] = $action;
            }
        }

        $missing = array_values(array_unique($missing));
        sort($missing);

        $this->assertSame([], array_values(array_diff($missing, self::KNOWN_MISSING_METHODS)),
            'New routes point at controller methods that do not exist.');
        $this->assertSame([], array_values(array_diff(self::KNOWN_MISSING_METHODS, $missing)),
            'These are fixed now: remove them from KNOWN_MISSING_METHODS.');
    }

    public function test_every_view_rendered_by_the_app_exists(): void
    {
        $missing = [];

        $files = (new Finder)->files()->in(app_path())->name('*.php');
        foreach ($files as $file) {
            // view('x'), View::make('x') and Pdf::loadView('x')
            preg_match_all("/\\b(?:view|make|loadView)\\(\\s*['\"]([a-z0-9_.\\-]+)['\"]/i", $file->getContents(), $m);
            foreach ($m[1] as $name) {
                if (! View::exists($name)) {
                    $missing[] = $name;
                }
            }
        }

        $missing = array_values(array_unique($missing));
        sort($missing);
        $missing = array_values(array_diff($missing, $this->hiddenModuleViews($missing)));

        $this->assertSame([], array_values(array_diff($missing, self::KNOWN_MISSING_VIEWS)),
            'Code renders views that do not exist.');
        $this->assertSame([], array_values(array_diff(self::KNOWN_MISSING_VIEWS, $missing)),
            'These views exist now: remove them from KNOWN_MISSING_VIEWS.');
    }
}
