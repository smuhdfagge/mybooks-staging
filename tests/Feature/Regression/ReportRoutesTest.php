<?php

namespace Tests\Feature\Regression;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Finding L4 split ReportController into one controller per report family.
 * Every report page and export still has to open.
 */
class ReportRoutesTest extends TestCase
{
    public function test_every_report_page_and_export_opens(): void
    {
        $this->createSuperAdmin();
        $failed = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'reports.') || ! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
                continue;
            }

            $status = $this->get('/'.$route->uri())->getStatusCode();
            if ($status >= 500) {
                $failed[] = "{$name} ({$status})";
            }
            $checked++;
        }

        $this->assertSame([], $failed);
        $this->assertGreaterThan(40, $checked);
    }
}
