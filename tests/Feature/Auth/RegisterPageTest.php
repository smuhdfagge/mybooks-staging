<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\RegisterWizard;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The one-page sign-up form that replaced the three-step wizard.
 */
class RegisterPageTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Kano-Textiles-2026!';

    private function plan(array $attrs = []): Plan
    {
        return Plan::factory()->create(array_merge([
            'slug' => 'starter',
            'is_active' => true,
            'monthly_price' => 25000,
            'annual_price' => 250000,
            'allow_monthly_billing' => true,
            'allow_annual_billing' => true,
        ], $attrs));
    }

    private function form(Plan $plan)
    {
        return Livewire::test(RegisterWizard::class)
            ->set('plan_id', $plan->id)
            ->set('name', 'Amina Yusuf')
            ->set('email', 'Amina@KanoTextiles.test')
            ->set('password', self::PASSWORD)
            ->set('company_name', 'Kano Textiles Ltd');
    }

    public function test_page_is_one_short_form_with_autofill_hints(): void
    {
        $this->plan(['name' => 'Starter']);

        $html = $this->get(route('register'))->assertOk()->getContent();

        $this->assertStringContainsString('<title>Create your account · MyBooks</title>', $html);
        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
        $this->assertStringContainsString('autocomplete="organization"', $html);
        $this->assertStringContainsString('Starter', $html);
        $this->assertStringNotContainsString('Company Info', $html);
        $this->assertStringNotContainsString('company_postal_code', $html);
    }

    public function test_signing_up_needs_only_the_short_form(): void
    {
        Notification::fake();
        $plan = $this->plan();

        $this->form($plan)->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'amina@kanotextiles.test')->firstOrFail();
        $tenant = Tenant::findOrFail($user->tenant_id);
        $this->assertSame('Kano Textiles Ltd', $tenant->name);
        $this->assertSame('amina@kanotextiles.test', $tenant->email, 'business email defaults to yours');
        $this->assertSame('NGN', $tenant->currency);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertAuthenticatedAs($user);

        $sub = Subscription::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(Subscription::STATUS_PENDING, $sub->status);
        $this->assertSame('monthly', $sub->billing_cycle);
    }

    public function test_a_separate_business_email_can_be_given(): void
    {
        Notification::fake();
        $plan = $this->plan();

        $this->form($plan)
            ->set('separate_company_email', true)
            ->set('company_email', 'Accounts@KanoTextiles.test')
            ->set('billing_cycle', 'annual')
            ->call('register')
            ->assertHasNoErrors();

        $tenant = Tenant::where('name', 'Kano Textiles Ltd')->firstOrFail();
        $this->assertSame('accounts@kanotextiles.test', $tenant->email);
        $this->assertSame('annual', Subscription::where('tenant_id', $tenant->id)->value('billing_cycle'));
    }

    public function test_missing_fields_and_a_weak_password_are_reported(): void
    {
        $this->plan();

        Livewire::test(RegisterWizard::class)
            ->set('password', 'password')
            ->call('register')
            ->assertHasErrors(['name', 'email', 'company_name', 'password'])
            ->assertHasNoErrors('company_email');

        $this->assertSame(0, User::count());
    }

    public function test_an_email_already_in_use_is_caught_when_leaving_the_field(): void
    {
        $this->plan();
        [$tenant] = $this->createTenantWithSubscription();
        $existing = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'taken@example.com']);

        Livewire::test(RegisterWizard::class)
            ->set('email', strtoupper($existing->email))
            ->assertHasErrors(['email' => 'unique'])
            ->assertSee('reset your password');
    }

    public function test_a_plan_sold_only_yearly_is_billed_yearly(): void
    {
        $this->plan(['slug' => 'professional', 'allow_monthly_billing' => false]);

        Livewire::withQueryParams(['plan' => 'professional'])
            ->test(RegisterWizard::class)
            ->assertSet('billing_cycle', 'annual')
            ->set('billing_cycle', 'monthly')
            ->assertSet('billing_cycle', 'annual');
    }

    public function test_bots_filling_the_hidden_field_create_nothing(): void
    {
        $plan = $this->plan();

        $this->form($plan)->set('hp_check', 'http://spam.example')->call('register')
            ->assertRedirect(route('login'));

        $this->assertSame(0, Tenant::where('name', 'Kano Textiles Ltd')->count());
    }
}
