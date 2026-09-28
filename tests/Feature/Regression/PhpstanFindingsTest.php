<?php

namespace Tests\Feature\Regression;

use Tests\TestCase;

/**
 * Bugs that PHPStan found once model relationships had types (finding L5).
 */
class PhpstanFindingsTest extends TestCase
{
    public function test_bill_reference_typed_on_the_form_is_saved(): void
    {
        $this->createAuthenticatedUser(['create bills', 'edit bills']);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $form = [
            'vendor_id' => $vendor->id, 'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'reference' => 'INV-7781',
            'items' => [['description' => 'Feed', 'quantity' => 1, 'unit_price' => 100]],
        ];

        $this->post(route('bills.store'), $form)->assertSessionHasNoErrors();
        $bill = \App\Models\Bill::sole();
        $this->assertSame('INV-7781', $bill->vendor_bill_number);

        $this->put(route('bills.update', $bill), array_merge($form, ['reference' => 'INV-7782']))->assertSessionHasNoErrors();
        $this->assertSame('INV-7782', $bill->fresh()->vendor_bill_number);
    }
}
