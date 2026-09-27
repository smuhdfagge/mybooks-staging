<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    public function test_generate_number_starts_at_one(): void
    {
        $this->createAuthenticatedUser();
        $number = Invoice::generateNumber($this->tenant->id);
        $this->assertEquals('INV-000001', $number);
    }

    public function test_generate_number_increments(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-000001',
            'status' => 'draft',
        ]);

        $number = Invoice::generateNumber($this->tenant->id);
        $this->assertEquals('INV-000002', $number);
    }

    public function test_generate_number_is_tenant_scoped(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-000005',
            'status' => 'draft',
        ]);

        // Different tenant should start fresh - suppress events to avoid
        // ChartOfAccount conflicts from BelongsToTenant creating callback
        $tenant2 = Tenant::withoutEvents(fn () => Tenant::factory()->create());
        $number = Invoice::generateNumber($tenant2->id);
        $this->assertEquals('INV-000001', $number);
    }

    public function test_is_released(): void
    {
        $invoice = Invoice::factory()->make(['released_at' => now()]);
        $this->assertTrue($invoice->isReleased());

        $invoice2 = Invoice::factory()->make(['released_at' => null]);
        $this->assertFalse($invoice2->isReleased());
    }

    public function test_can_be_released_when_paid_and_not_released(): void
    {
        $invoice = Invoice::factory()->paid()->make(['released_at' => null]);
        $this->assertTrue($invoice->canBeReleased());
    }

    public function test_cannot_be_released_when_already_released(): void
    {
        $invoice = Invoice::factory()->paid()->make(['released_at' => now()]);
        $this->assertFalse($invoice->canBeReleased());
    }

    public function test_cannot_be_released_when_draft(): void
    {
        $invoice = Invoice::factory()->draft()->make(['released_at' => null]);
        $this->assertFalse($invoice->canBeReleased());
    }

    public function test_customer_relationship(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $this->assertEquals($customer->id, $invoice->customer->id);
    }

    public function test_soft_deletes(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);
        $invoiceId = $invoice->id;
        $invoice->delete();
        $this->assertSoftDeleted('invoices', ['id' => $invoiceId]);
    }

    public function test_generate_waybill_number(): void
    {
        $this->createAuthenticatedUser();
        $number = Invoice::generateWaybillNumber($this->tenant->id);
        $this->assertEquals('WB-000001', $number);
    }
}
