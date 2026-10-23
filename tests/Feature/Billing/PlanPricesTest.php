<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What MyBooks charges.
 *
 * These are business figures rather than code, and they live in a seeder
 * where a careless edit would not stand out. Renewals read the plan's
 * current price, so a wrong number here is money, not cosmetics. Written
 * down so a change has to be deliberate.
 */
class PlanPricesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public static function prices(): array
    {
        return [
            // plan, monthly, annual
            'Starter' => ['starter', 25000.00, 250000.00],
            'Professional' => ['professional', 30000.00, 300000.00],
            'Enterprise' => ['enterprise', 60000.00, 600000.00],
        ];
    }

    #[DataProvider('prices')]
    public function test_each_plan_costs_what_it_should(string $slug, float $monthly, float $annual): void
    {
        $plan = Plan::where('slug', $slug)->firstOrFail();

        $this->assertSame($monthly, (float) $plan->monthly_price);
        $this->assertSame($annual, (float) $plan->annual_price);
    }

    #[DataProvider('prices')]
    public function test_paying_for_a_year_saves_two_months(string $slug, float $monthly, float $annual): void
    {
        $plan = Plan::where('slug', $slug)->firstOrFail();

        // The promise the pricing page makes. Ten months' money for twelve
        // months of service, on every plan.
        $this->assertSame($monthly * 10, (float) $plan->annual_price);
        $this->assertSame($annual, $monthly * 10);
    }

    public function test_every_plan_can_be_bought_either_way(): void
    {
        // Professional was annual-only. It now carries a monthly price, and
        // a price nobody can choose is not a price.
        foreach (Plan::all() as $plan) {
            $this->assertTrue((bool) $plan->allow_monthly_billing, "{$plan->name} has no monthly option.");
            $this->assertTrue((bool) $plan->allow_annual_billing, "{$plan->name} has no annual option.");
        }
    }

    public function test_the_plans_get_dearer_as_they_get_bigger(): void
    {
        $plans = Plan::orderBy('sort_order')->get();

        $this->assertSame(['Starter', 'Professional', 'Enterprise'], $plans->pluck('name')->all());
        $this->assertTrue(
            $plans->pluck('monthly_price')->map(fn ($p) => (float) $p)->sliding(2)->every(fn ($pair) => $pair->first() < $pair->last()),
            'A bigger plan must not cost less than a smaller one.'
        );
    }

    public function test_reseeding_changes_the_price_without_duplicating_the_plan(): void
    {
        Plan::where('slug', 'professional')->update(['monthly_price' => 1]);

        // How the live prices get updated: the seeder matches on slug, so
        // running it again moves the price on the existing row rather than
        // creating a second Professional.
        $this->seed(PlanSeeder::class);

        $this->assertSame(1, Plan::where('slug', 'professional')->count());
        $this->assertSame(30000.00, (float) Plan::where('slug', 'professional')->value('monthly_price'));
    }
}
