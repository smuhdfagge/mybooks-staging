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
        'App\Http\Controllers\InvoiceController@markAsPaid',
        'App\Http\Controllers\SalesReceiptController@pdf',
        'App\Http\Controllers\SettingsController@index',
    ];

    private const KNOWN_MISSING_VIEWS = [
        // Unfinished modules (N4)
        'credit-notes.apply', 'credit-notes.create', 'credit-notes.index', 'credit-notes.show',
        'delivery-notes.create', 'delivery-notes.index', 'delivery-notes.print', 'delivery-notes.show',
        'inventory.assembly.create', 'inventory.assembly.index', 'inventory.assembly.show',
        'inventory.bom.create', 'inventory.bom.edit', 'inventory.bom.index', 'inventory.bom.show',
        'inventory.transfers.create', 'inventory.transfers.index', 'inventory.transfers.show',
        'inventory.valuation',
        'inventory.warehouses.create', 'inventory.warehouses.edit', 'inventory.warehouses.index', 'inventory.warehouses.show',
        'quotations.create', 'quotations.edit', 'quotations.index', 'quotations.print', 'quotations.show',
        // Older gaps (N9)
        'departments.edit', 'departments.show',
        'exports.show',
        'invoices.refunds.index',
        'leave-types.create', 'leave-types.edit', 'leave-types.index', 'leave-types.show',
        // Unrouted TenantManagementController (L3, to be deleted)
        'tenants.index', 'tenants.list', 'tenants.show',
    ];

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
            preg_match_all("/\\bview\\(\\s*['\"]([a-z0-9_.\\-]+)['\"]/i", $file->getContents(), $m);
            foreach ($m[1] as $name) {
                if (! View::exists($name)) {
                    $missing[] = $name;
                }
            }
        }

        $missing = array_values(array_unique($missing));
        sort($missing);

        $this->assertSame([], array_values(array_diff($missing, self::KNOWN_MISSING_VIEWS)),
            'Code renders views that do not exist.');
        $this->assertSame([], array_values(array_diff(self::KNOWN_MISSING_VIEWS, $missing)),
            'These views exist now: remove them from KNOWN_MISSING_VIEWS.');
    }
}
