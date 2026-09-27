<?php

namespace Tests\Feature;

use Tests\TestCase;

class ImportPermissionTest extends TestCase
{
    public function test_import_route_requires_import_data_permission(): void
    {
        // User with only 'export reports' should be denied
        $this->createAuthenticatedUser(['export reports']);

        $response = $this->get(route('imports.index'));
        $response->assertForbidden();
    }

    public function test_import_route_allows_user_with_import_data_permission(): void
    {
        $this->createAuthenticatedUser(['import data']);

        $response = $this->get(route('imports.index'));
        $response->assertOk();
    }

    public function test_export_permission_does_not_grant_import_access(): void
    {
        $this->createAuthenticatedUser(['export data']);

        $response = $this->get(route('imports.index'));
        $response->assertForbidden();
    }
}
