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

    public function test_n9_settings_opens_company_settings(): void
    {
        $this->createAuthenticatedUser(['view settings']);

        $this->get('/settings')->assertRedirect(route('settings.company'));
    }

    public function test_n9_there_is_no_mark_paid_shortcut_for_invoices(): void
    {
        $this->assertFalse(Route::has('invoices.mark-paid'));
    }

    public function test_n9_sales_receipt_downloads_as_pdf(): void
    {
        $this->createAuthenticatedUser(['view sales-receipts']);
        $customer = \App\Models\Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $receipt = \App\Models\SalesReceipt::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'receipt_number' => 'SR-000001',
            'receipt_date' => now(), 'payment_method' => 'cash', 'subtotal' => 100, 'tax_amount' => 0, 'total' => 100,
        ]);
        $receipt->items()->create(['description' => 'Rice', 'quantity' => 2, 'unit_price' => 50, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 100]);

        $response = $this->get(route('sales-receipts.pdf', $receipt))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_n9_api_invoice_downloads_as_pdf(): void
    {
        $this->createAuthenticatedUser(['view invoices']);
        $customer = \App\Models\Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = \App\Models\Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000777']);

        $response = $this->actingAs($this->user, 'sanctum')->get("/api/v1/invoices/{$invoice->id}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_n9_department_show_and_edit_pages_work(): void
    {
        $this->createAuthenticatedUser(['view departments', 'edit departments', 'view employees']);
        $parent = \App\Models\Department::create(['tenant_id' => $this->tenant->id, 'name' => 'Operations', 'code' => 'OPS', 'is_active' => true]);
        $child = \App\Models\Department::create(['tenant_id' => $this->tenant->id, 'name' => 'Farm', 'code' => 'FRM', 'parent_id' => $parent->id, 'is_active' => true]);

        $this->get(route('departments.show', $parent))->assertOk()->assertSee('Operations')->assertSee('Farm');
        $this->get(route('departments.edit', $child))->assertOk()->assertSee('value="Farm"', false);

        $this->put(route('departments.update', $child), ['name' => 'Farm Ops', 'parent_id' => $parent->id, 'is_active' => '0'])
            ->assertRedirect(route('departments.index'));
        $this->assertSame('Farm Ops', $child->fresh()->name);
        $this->assertFalse($child->fresh()->is_active);

        // A department can't be put under its own sub-department
        $this->put(route('departments.update', $parent), ['name' => 'Operations', 'parent_id' => $child->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_n9_leave_types_can_be_managed_and_keep_their_days(): void
    {
        $this->createAuthenticatedUser(['view leave-types', 'create leave-types', 'edit leave-types']);

        $this->get(route('leave-types.index'))->assertOk()->assertSee('No leave types yet');
        $this->get(route('leave-types.create'))->assertOk();

        $this->post(route('leave-types.store'), [
            'name' => 'Annual leave', 'code' => 'AL', 'days_per_year' => 21, 'is_paid' => '1', 'is_active' => '1',
        ])->assertRedirect(route('leave-types.index'));

        $type = \App\Models\LeaveType::sole();
        $this->assertSame(21, $type->days_per_year);   // was lost before (days_allowed)
        $this->get(route('leave-types.show', $type))->assertOk()->assertSee('Annual leave');
        $this->get(route('leave-types.edit', $type))->assertOk();

        $this->put(route('leave-types.update', $type), ['name' => 'Annual leave', 'days_per_year' => 24, 'is_active' => '0'])
            ->assertRedirect(route('leave-types.index'));
        $this->assertSame(24, $type->fresh()->days_per_year);
        $this->assertFalse($type->fresh()->is_active);
        $this->assertFalse($type->fresh()->is_paid);
    }

    public function test_n9_export_details_page_works(): void
    {
        $this->createAuthenticatedUser(['export reports']);
        $export = \App\Models\Export::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'customers', 'format' => 'csv',
            'status' => 'failed', 'error_message' => 'Disk full', 'expires_at' => now()->addDays(7),
        ]);

        $this->get(route('exports.show', $export))->assertOk()->assertSee('Disk full')->assertSee('Customers');
    }
}
