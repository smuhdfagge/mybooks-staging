<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SignupThrottle;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Same sign-up limit as the registration wizard (S8).
        SignupThrottle::check((string) $request->ip());

        $request->validate([
            // Plan validation
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,annual'],
            // Tenant validation
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:tenants,email'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_city' => ['nullable', 'string', 'max:100'],
            'company_state' => ['nullable', 'string', 'max:100'],
            'company_country' => ['nullable', 'string', 'max:100'],
            'company_postal_code' => ['nullable', 'string', 'max:20'],
            'currency' => ['required', 'string', 'size:3'],
            // User validation
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Get the plan and validate billing cycle
        $plan = Plan::findOrFail($request->plan_id);
        $billingCycle = $request->billing_cycle;

        // Validate that the plan allows the selected billing cycle
        if (! $plan->allowsBillingCycle($billingCycle)) {
            return back()->withErrors([
                'billing_cycle' => "The selected plan does not support {$billingCycle} billing.",
            ])->withInput();
        }

        $user = DB::transaction(function () use ($request, $plan, $billingCycle) {
            // Create the tenant first
            $tenant = Tenant::create([
                'name' => $request->company_name,
                'slug' => Str::slug($request->company_name).'-'.Str::random(6),
                'email' => $request->company_email,
                'phone' => $request->company_phone,
                'address' => $request->company_address,
                'city' => $request->company_city,
                'state' => $request->company_state,
                'country' => $request->company_country,
                'postal_code' => $request->company_postal_code,
                'currency' => $request->currency,
                'is_active' => true,
            ]);

            // The subscription waits for payment (finding C1). A free plan
            // is switched on straight away.
            app(\App\Services\Billing\SubscriptionBilling::class)
                ->startPendingSubscription($tenant, $plan, $billingCycle);

            // Create the admin user for this tenant
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'is_active' => true,
            ]);

            // Ensure admin role exists and assign it
            $adminRole = \App\Models\Role::firstOrCreate(
                ['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null],
                []
            );
            $user->assignRole($adminRole);

            return $user;
        });

        SignupThrottle::recordSignup((string) $request->ip());

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('verification.notice'));
    }
}
