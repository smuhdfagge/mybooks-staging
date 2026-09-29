<?php

namespace Tests\Feature\Regression;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use Tests\TestCase;

/**
 * Round 3, Phase C: one set of rules.
 */
class PhaseCRegressionTest extends TestCase
{
    // ── R2: document numbers ────────────────────────────────────

    public function test_r2_two_saves_at_the_same_moment_get_different_numbers(): void
    {
        [$tenant] = $this->createTenantWithSubscription();

        // Both are asked for a number before either is saved, as when two
        // people press Save together. "Last number + 1" gave both the same.
        $first = Invoice::generateNumber($tenant->id);
        $second = Invoice::generateNumber($tenant->id);
        $this->assertNotSame($first, $second);

        $j1 = Journal::generateNumber($tenant->id);
        $j2 = Journal::generateNumber($tenant->id);
        $this->assertNotSame($j1, $j2);
    }

    public function test_r2_numbering_carries_on_from_existing_numbers_and_survives_odd_imports(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $make = fn (string $number) => Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'invoice_number' => $number,
        ]));

        $make('INV-000041');
        $make('2024/001'); // an imported number in another shape, saved last

        $this->assertSame('INV-000042', Invoice::generateNumber($tenant->id));

        // A number someone typed in by hand is skipped, not reused.
        $make('INV-000043');
        $this->assertSame('INV-000044', Invoice::generateNumber($tenant->id));
    }

    public function test_r2_each_business_has_its_own_sequence(): void
    {
        [$a] = $this->createTenantWithSubscription();
        [$b] = $this->createTenantWithSubscription();

        $this->assertSame('INV-000001', Invoice::generateNumber($a->id));
        $this->assertSame('INV-000001', Invoice::generateNumber($b->id));
        $this->assertSame('INV-000002', Invoice::generateNumber($a->id));
    }
}
