<?php

namespace App\Livewire\Auth;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class RegisterWizard extends Component
{
    // Current step (1 = Plan, 2 = Company, 3 = User)
    public int $currentStep = 1;
    public int $totalSteps = 3;

    // Step 1: Plan Selection
    public ?int $plan_id = null;
    public string $billing_cycle = 'monthly';

    // Step 2: Company Information
    public string $company_name = '';
    public string $company_email = '';
    public string $company_phone = '';
    public string $company_address = '';
    public string $company_city = '';
    public string $company_state = '';
    public string $company_country = '';
    public string $company_postal_code = '';
    public string $currency = 'NGN';

    // Step 3: User Information
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Plans collection
    public $plans;

    public function mount()
    {
        $this->plans = Plan::active()->ordered()->get();
        
        // Set default plan from query string or first plan
        $selectedPlanSlug = request('plan');
        $selectedPlan = $this->plans->firstWhere('slug', $selectedPlanSlug) ?? $this->plans->first();
        
        if ($selectedPlan) {
            $this->plan_id = $selectedPlan->id;
        }
    }

    // Validation rules for each step
    protected function rulesForStep(int $step): array
    {
        return match($step) {
            1 => [
                'plan_id' => ['required', 'exists:plans,id'],
                'billing_cycle' => ['required', 'in:monthly,annual'],
            ],
            2 => [
                'company_name' => ['required', 'string', 'max:255'],
                'company_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:tenants,email'],
                'company_phone' => ['nullable', 'string', 'max:20'],
                'company_address' => ['nullable', 'string', 'max:500'],
                'company_city' => ['nullable', 'string', 'max:100'],
                'company_state' => ['nullable', 'string', 'max:100'],
                'company_country' => ['nullable', 'string', 'max:100'],
                'company_postal_code' => ['nullable', 'string', 'max:20'],
                'currency' => ['required', 'string', 'size:3'],
            ],
            3 => [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
                'phone' => ['nullable', 'string', 'max:20'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
            default => [],
        };
    }

    protected function messagesForStep(): array
    {
        return [
            'plan_id.required' => 'Please select a plan to continue.',
            'company_name.required' => 'Company name is required.',
            'company_email.required' => 'Company email is required.',
            'company_email.unique' => 'This company email is already registered.',
            'email.unique' => 'This email address is already registered.',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }

    public function updatedPlanId($value)
    {
        // Reset billing cycle when plan changes to ensure valid selection
        $plan = $this->plans->find($value);
        if ($plan) {
            if (!$plan->allowsBillingCycle($this->billing_cycle)) {
                $this->billing_cycle = $plan->allow_monthly_billing ? 'monthly' : 'annual';
            }
        }
    }

    public function submit()
    {
        if ($this->currentStep < $this->totalSteps) {
            $this->nextStep();
        } else {
            $this->register();
        }
    }

    public function nextStep()
    {
        // Validate current step
        $this->validate(
            $this->rulesForStep($this->currentStep),
            $this->messagesForStep()
        );

        // Additional validation for step 1 - check billing cycle is allowed
        if ($this->currentStep === 1) {
            $plan = Plan::find($this->plan_id);
            if (!$plan->allowsBillingCycle($this->billing_cycle)) {
                $this->addError('billing_cycle', "The selected plan does not support {$this->billing_cycle} billing.");
                return;
            }
        }

        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep(int $step)
    {
        // Only allow going to previous steps or current step
        if ($step < $this->currentStep && $step >= 1) {
            $this->currentStep = $step;
        }
    }

    public function register()
    {
        // Validate final step
        $this->validate(
            $this->rulesForStep($this->currentStep),
            $this->messagesForStep()
        );

        // Get the plan
        $plan = Plan::findOrFail($this->plan_id);

        // Create user and tenant in transaction
        $user = DB::transaction(function () use ($plan) {
            // Create the tenant
            $tenant = Tenant::create([
                'name' => $this->company_name,
                'slug' => Str::slug($this->company_name) . '-' . Str::random(6),
                'email' => strtolower($this->company_email),
                'phone' => $this->company_phone,
                'address' => $this->company_address,
                'city' => $this->company_city,
                'state' => $this->company_state,
                'country' => $this->company_country,
                'postal_code' => $this->company_postal_code,
                'currency' => $this->currency,
                'is_active' => true,
            ]);

            // The subscription waits for payment (finding C1). A free plan
            // is switched on straight away.
            app(\App\Services\Billing\SubscriptionBilling::class)
                ->startPendingSubscription($tenant, $plan, $this->billing_cycle);

            // Create the admin user
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $this->name,
                'email' => strtolower($this->email),
                'phone' => $this->phone,
                'password' => Hash::make($this->password),
                'is_active' => true,
            ]);

            // Ensure admin role exists and assign it
            $adminRole = \App\Models\Role::firstOrCreate(
                ['name' => 'admin', 'guard_name' => 'web'],
                ['tenant_id' => null]
            );
            $user->assignRole($adminRole);

            return $user;
        });

        // Fire registered event (sends verification email)
        event(new Registered($user));

        // Log the user in
        Auth::login($user);

        // Redirect to email verification notice
        return redirect()->route('verification.notice');
    }

    public function getSelectedPlanProperty()
    {
        return $this->plans->find($this->plan_id);
    }

    public function render()
    {
        return view('livewire.auth.register-wizard')
            ->layout('layouts.guest');
    }
}
