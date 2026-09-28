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

    private function payrollFixture(): void
    {
        $employee = \App\Models\Employee::withoutEvents(fn () => \App\Models\Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-001', 'first_name' => 'Hauwa', 'last_name' => 'Musa',
            'email' => 'hauwa@example.com', 'hire_date' => now()->subYear(), 'status' => 'active',
            'bank_name' => 'Zenith', 'bank_account_number' => '0123456789',
        ]));

        \App\Models\Payroll::withoutEvents(fn () => \App\Models\Payroll::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'payroll_number' => 'PAY-000001',
            'pay_period_start' => now()->startOfMonth(), 'pay_period_end' => now()->endOfMonth(), 'pay_date' => now(),
            'basic_salary' => 100000, 'allowances' => 20000, 'gross_salary' => 120000, 'tax_deduction' => 9000,
            'total_deductions' => 9000, 'net_salary' => 111000, 'employer_contributions' => 12000,
            'status' => 'approved', 'payment_method' => 'bank_transfer', 'created_by' => $this->user->id,
        ]));
    }

    public function test_n9_api_payroll_reports_answer(): void
    {
        $this->createAuthenticatedUser(['view reports', 'view payroll']);
        $this->payrollFixture();
        $api = $this->actingAs($this->user, 'sanctum');

        $api->getJson('/api/v1/reports/payroll-register')->assertOk()
            ->assertJsonPath('data.totals.net_salary', 111000)
            ->assertJsonPath('data.payrolls.0.employee.name', 'Hauwa Musa');
        $api->getJson('/api/v1/reports/ytd-earnings')->assertOk()->assertJsonPath('data.totals.gross', 120000);
        $api->getJson('/api/v1/reports/tax-liability-payroll')->assertOk()->assertJsonPath('data.totals.total_tax', 9000);
        $api->getJson('/api/v1/reports/employer-contributions')->assertOk()->assertJsonPath('data.totals.total_employer_contributions', 12000);
        $api->getJson('/api/v1/reports/bank-disbursement')->assertOk()
            ->assertJsonPath('data.totals.total_net', 111000)
            ->assertJsonPath('data.payments.0.bank_account_number', '0123456789');
        $api->getJson('/api/v1/reports/salary-revision-history')->assertOk()->assertJsonStructure(['data' => ['versions', 'salary_progression']]);

        // Salary details stay out of the employee block
        $this->assertArrayNotHasKey('basic_salary', $api->getJson('/api/v1/reports/ytd-earnings')->json('data.by_employee.0.employee'));
    }

    public function test_n9_bank_details_need_the_payroll_permission(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->payrollFixture();

        $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/reports/bank-disbursement')->assertForbidden();
    }

    public function test_n9_web_payroll_reports_still_render(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->payrollFixture();

        foreach (['payroll-register', 'ytd-earnings', 'tax-liability-payroll', 'employer-contributions', 'bank-disbursement', 'salary-revision-history'] as $report) {
            $this->get(route("reports.{$report}"))->assertOk();
        }
        $this->get(route('reports.payroll-register'))->assertSee('Hauwa');
    }

    private function purchaseOrder(string $status = 'received'): \App\Models\PurchaseOrder
    {
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = \App\Models\Item::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Fish feed 15kg']);
        $po = \App\Models\PurchaseOrder::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'order_number' => 'PO-000001',
            'order_date' => now(), 'status' => $status, 'subtotal' => 50000, 'total' => 50000,
        ]);
        $po->items()->create([
            'item_id' => $item->id, 'description' => 'Fish feed 15kg', 'quantity' => 10, 'quantity_received' => 8,
            'unit_price' => 5000, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 50000,
        ]);

        return $po;
    }

    public function test_n8_convert_to_bill_fills_the_bill_from_the_order(): void
    {
        $this->createAuthenticatedUser(['view purchase-orders', 'edit purchase-orders', 'create bills']);
        $po = $this->purchaseOrder();

        $this->post(route('purchase-orders.convert-to-bill', $po))
            ->assertRedirect(route('bills.create', ['purchase_order_id' => $po->id]));

        $this->get(route('bills.create', ['purchase_order_id' => $po->id]))->assertOk()
            ->assertSee('PO-000001')
            ->assertSee('Fish feed 15kg')
            ->assertSee('\u0022quantity\u0022:8,', false)   // what was received, not the 10 ordered
            ->assertSee('name="purchase_order_id"', false);
    }

    public function test_n8_saving_the_bill_links_it_and_marks_the_order_billed(): void
    {
        $this->createAuthenticatedUser(['view purchase-orders', 'edit purchase-orders', 'create bills']);
        $po = $this->purchaseOrder();
        $line = $po->items()->first();
        $payload = [
            'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id,
            'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'items' => [['item_id' => $line->item_id, 'description' => 'Fish feed 15kg', 'quantity' => 8, 'unit_price' => 5000, 'tax_rate' => 0]],
        ];

        $this->post(route('bills.store'), $payload)->assertSessionHasNoErrors();

        $bill = \App\Models\Bill::sole();
        $this->assertSame($po->id, $bill->purchase_order_id);
        $this->assertSame('billed', $po->fresh()->status);
        $this->assertCount(1, $po->fresh()->bills);

        // Can't be billed twice
        $this->post(route('purchase-orders.convert-to-bill', $po))->assertSessionHas('error');
        $this->post(route('bills.store'), $payload)->assertSessionHasErrors('purchase_order_id');
        $this->assertSame(1, \App\Models\Bill::count());
    }

    public function test_n8_bill_vendor_must_match_the_order(): void
    {
        $this->createAuthenticatedUser(['create bills']);
        $po = $this->purchaseOrder();
        $other = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('bills.store'), [
            'purchase_order_id' => $po->id, 'vendor_id' => $other->id,
            'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1]],
        ])->assertSessionHasErrors('vendor_id');

        $this->assertSame('received', $po->fresh()->status);
    }
}
