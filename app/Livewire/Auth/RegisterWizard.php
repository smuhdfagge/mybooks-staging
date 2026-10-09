<?php

namespace App\Livewire\Auth;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscriptionBilling;
use App\Support\SignupThrottle;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Sign-up page: one short form instead of the old three-step wizard.
 *
 * Only what is needed to open the account is asked for: your name, email,
 * password, the business name and the plan. Address, city and the rest are
 * filled in later under Settings. The business email defaults to your own
 * email unless you choose a different one.
 *
 * @property-read Collection<int, Plan> $plans
 * @property-read Plan|null $selectedPlan
 */
class RegisterWizard extends Component
{
    public ?int $plan_id = null;

    public string $billing_cycle = 'monthly';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $company_name = '';

    public bool $separate_company_email = false;

    public string $company_email = '';

    public string $currency = 'NGN';

    /** Hidden from people; bots fill it in. */
    public string $hp_check = '';

    public const CURRENCIES = [
        'NGN' => 'NGN, Nigerian naira',
        'USD' => 'USD, US dollar',
        'GBP' => 'GBP, British pound',
        'EUR' => 'EUR, Euro',
        'GHS' => 'GHS, Ghanaian cedi',
        'KES' => 'KES, Kenyan shilling',
        'ZAR' => 'ZAR, South African rand',
        'CAD' => 'CAD, Canadian dollar',
        'AUD' => 'AUD, Australian dollar',
    ];

    public function mount(): void
    {
        $plans = $this->plans;
        $chosen = $plans->firstWhere('slug', request('plan')) ?? $plans->first();

        if ($chosen) {
            $this->plan_id = $chosen->id;
            $this->billing_cycle = $this->cycleFor($chosen, request('cycle') === 'annual' ? 'annual' : 'monthly');
        }
    }

    /** @return Collection<int, Plan> */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::active()->ordered()->get();
    }

    #[Computed]
    public function selectedPlan(): ?Plan
    {
        return $this->plans->firstWhere('id', $this->plan_id);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', Password::defaults()],
            'company_name' => ['required', 'string', 'max:255'],
            // Without its own email the business uses yours, which the
            // email rule already checks; only a clash with another business
            // can then go wrong.
            'company_email' => $this->separate_company_email
                ? ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:tenants,email']
                : ['nullable', 'string', 'unique:tenants,email'],
            'currency' => ['required', 'string', 'in:'.implode(',', array_keys(self::CURRENCIES))],
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,annual'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Enter your full name.',
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address, like name@business.com.',
            'email.unique' => 'An account already uses this email.',
            'password.required' => 'Choose a password.',
            'company_name.required' => 'Enter your business name.',
            'company_email.required' => 'Enter the email for your invoices.',
            'company_email.email' => 'Enter a valid email address, like accounts@business.com.',
            'company_email.unique' => 'Another business already uses this email.',
            'plan_id.required' => 'Choose a plan.',
        ];
    }

    protected function validationAttributes(): array
    {
        return ['company_name' => 'business name', 'company_email' => 'business email'];
    }

    /** Check each field as soon as the person leaves it. */
    public function updated(string $field): void
    {
        if ($field === 'email' || $field === 'company_email') {
            $this->{$field} = Str::lower(trim($this->{$field}));
        }

        // A fresh password is checked in full when the form is sent.
        if ($field === 'password') {
            $this->resetErrorBag('password');
        }

        if ($field === 'email' && ! $this->separate_company_email) {
            $this->company_email = $this->email;
        }

        if (in_array($field, ['name', 'email', 'company_name', 'company_email', 'phone'], true) && $this->{$field} !== '') {
            $this->validateOnly($field);
        }
    }

    public function updatedSeparateCompanyEmail(bool $on): void
    {
        $this->company_email = $on ? '' : $this->email;
        $this->resetErrorBag('company_email');
    }

    public function updatedPlanId(): void
    {
        unset($this->selectedPlan);
        if ($plan = $this->selectedPlan) {
            $this->billing_cycle = $this->cycleFor($plan, $this->billing_cycle);
        }
    }

    public function updatedBillingCycle(): void
    {
        if ($plan = $this->selectedPlan) {
            $this->billing_cycle = $this->cycleFor($plan, $this->billing_cycle);
        }
    }

    public function register()
    {
        // Bots fill in the hidden field: act as if it worked, create nothing.
        if ($this->hp_check !== '') {
            return redirect()->route('login');
        }

        // Livewire calls skip route throttles, so limit sign-ups here (S8).
        SignupThrottle::check((string) request()->ip());

        $this->email = Str::lower(trim($this->email));
        if (! $this->separate_company_email || trim($this->company_email) === '') {
            $this->company_email = $this->email;
        }
        $this->company_email = Str::lower(trim($this->company_email));

        $this->validate();

        $plan = Plan::findOrFail($this->plan_id);
        if (! $plan->allowsBillingCycle($this->billing_cycle)) {
            $this->addError('billing_cycle', "The {$plan->name} plan is not sold {$this->billing_cycle}.");

            return null;
        }

        $user = DB::transaction(function () use ($plan) {
            $tenant = Tenant::create([
                'name' => $this->company_name,
                'slug' => Str::slug($this->company_name).'-'.Str::random(6),
                'email' => $this->company_email,
                'phone' => $this->phone,
                'currency' => $this->currency,
                'is_active' => true,
            ]);

            // The subscription waits for payment (finding C1). A free plan
            // is switched on straight away.
            app(SubscriptionBilling::class)
                ->startPendingSubscription($tenant, $plan, $this->billing_cycle);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'password' => Hash::make($this->password),
                'is_active' => true,
            ]);

            $adminRole = Role::firstOrCreate(
                ['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null],
                []
            );
            $user->assignRole($adminRole);

            return $user;
        });

        SignupThrottle::recordSignup((string) request()->ip());

        // Sends the verification email.
        event(new Registered($user));

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('verification.notice');
    }

    private function cycleFor(Plan $plan, string $wanted): string
    {
        if ($plan->allowsBillingCycle($wanted)) {
            return $wanted;
        }

        return $plan->allow_monthly_billing ? 'monthly' : 'annual';
    }

    public function render()
    {
        return view('livewire.auth.register-wizard', ['currencies' => self::CURRENCIES]);
    }
}
