<div class="w-full">
    <!-- Progress Steps -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            @for ($i = 1; $i <= $totalSteps; $i++)
                <div class="flex items-center {{ $i < $totalSteps ? 'flex-1' : '' }}">
                    <!-- Step Circle -->
                    <button 
                        type="button"
                        wire:click="goToStep({{ $i }})"
                        class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-300
                            {{ $i < $currentStep ? 'bg-green-500 text-white cursor-pointer hover:bg-green-600' : '' }}
                            {{ $i === $currentStep ? 'bg-brand-600 text-white ring-4 ring-brand-600/30' : '' }}
                            {{ $i > $currentStep ? 'bg-gray-700 text-gray-400 cursor-not-allowed' : '' }}"
                        {{ $i > $currentStep ? 'disabled' : '' }}
                    >
                        @if ($i < $currentStep)
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        @else
                            {{ $i }}
                        @endif
                    </button>
                    
                    <!-- Step Label -->
                    <span class="ml-3 text-sm font-medium hidden sm:block
                        {{ $i <= $currentStep ? 'text-gray-100' : 'text-slate-400' }}">
                        @if ($i === 1)
                            Select Plan
                        @elseif ($i === 2)
                            Company Info
                        @else
                            Your Account
                        @endif
                    </span>
                    
                    <!-- Connector Line -->
                    @if ($i < $totalSteps)
                        <div class="flex-1 mx-4 h-1 rounded {{ $i < $currentStep ? 'bg-green-500' : 'bg-gray-700' }}"></div>
                    @endif
                </div>
            @endfor
        </div>
    </div>

    <form wire:submit.prevent="submit">
        <!-- Step 1: Plan Selection -->
        @if ($currentStep === 1)
            <div class="space-y-6">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-white">Choose Your Plan</h2>
                    <p class="text-gray-400 mt-2">Select the plan that best fits your business needs</p>
                </div>

                <!-- Plans -->
                <div class="space-y-4">
                    @foreach($plans as $plan)
                        <label 
                            class="relative flex items-start p-5 cursor-pointer rounded-xl border-2 transition-all duration-300 hover:border-brand-500
                                {{ $plan_id === $plan->id ? 'border-brand-500 bg-brand-500/10' : 'border-gray-700 bg-gray-800/50' }}"
                            wire:click="$set('plan_id', {{ $plan->id }})"
                        >
                            <input 
                                type="radio" 
                                wire:model.live="plan_id" 
                                value="{{ $plan->id }}" 
                                class="sr-only"
                            >
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all
                                            {{ $plan_id === $plan->id ? 'border-brand-500 bg-brand-500' : 'border-gray-500' }}">
                                            @if($plan_id === $plan->id)
                                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="font-semibold text-white text-lg">{{ $plan->name }}</span>
                                            @if($plan->slug === 'professional')
                                                <span class="ml-2 px-2.5 py-1 text-xs font-semibold bg-accent-400 text-gray-900 rounded-full">Popular</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-2xl font-bold text-white">₦{{ number_format($plan->monthly_price) }}</span>
                                        <span class="text-gray-400">/mo</span>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    <span class="text-gray-400">{{ $plan->description }}</span>
                                    <span class="text-slate-400 text-sm">Up to {{ $plan->max_users }} users</span>
                                </div>
                                @if($plan->features && count($plan->features) > 0)
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach(array_slice($plan->features, 0, 3) as $feature)
                                            <span class="text-xs px-2 py-1 bg-gray-700 text-gray-300 rounded-full">{{ $feature }}</span>
                                        @endforeach
                                        @if(count($plan->features) > 3)
                                            <span class="text-xs px-2 py-1 text-brand-400">+{{ count($plan->features) - 3 }} more</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
                
                @error('plan_id')
                    <p id="plan_id-error" class="text-red-400 text-sm mt-2">{{ $message }}</p>
                @enderror

                <!-- Billing Cycle -->
                @if($this->selectedPlan)
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold text-white mb-4">Billing Cycle</h3>
                        <div class="grid grid-cols-2 gap-4">
                            @if($this->selectedPlan->allow_monthly_billing)
                                <label 
                                    class="relative flex flex-col p-5 cursor-pointer rounded-xl border-2 transition-all duration-300 hover:border-brand-500
                                        {{ $billing_cycle === 'monthly' ? 'border-brand-500 bg-brand-500/10' : 'border-gray-700 bg-gray-800/50' }}"
                                >
                                    <input 
                                        type="radio" 
                                        wire:model.live="billing_cycle" 
                                        value="monthly" 
                                        class="sr-only"
                                    >
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-white">Monthly</span>
                                        <div class="w-4 h-4 rounded-full border-2 transition-all
                                            {{ $billing_cycle === 'monthly' ? 'border-brand-500 bg-brand-500' : 'border-gray-500' }}">
                                        </div>
                                    </div>
                                    <span class="text-2xl font-bold text-white mt-3">
                                        ₦{{ number_format($this->selectedPlan->monthly_price) }}
                                        <span class="text-sm font-normal text-gray-400">/month</span>
                                    </span>
                                </label>
                            @endif
                            
                            @if($this->selectedPlan->allow_annual_billing)
                                <label 
                                    class="relative flex flex-col p-5 cursor-pointer rounded-xl border-2 transition-all duration-300 hover:border-brand-500
                                        {{ $billing_cycle === 'annual' ? 'border-brand-500 bg-brand-500/10' : 'border-gray-700 bg-gray-800/50' }}"
                                >
                                    <input 
                                        type="radio" 
                                        wire:model.live="billing_cycle" 
                                        value="annual" 
                                        class="sr-only"
                                    >
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-white">Annual</span>
                                        <div class="w-4 h-4 rounded-full border-2 transition-all
                                            {{ $billing_cycle === 'annual' ? 'border-brand-500 bg-brand-500' : 'border-gray-500' }}">
                                        </div>
                                    </div>
                                    <span class="text-2xl font-bold text-white mt-3">
                                        ₦{{ number_format($this->selectedPlan->annual_price) }}
                                        <span class="text-sm font-normal text-gray-400">/year</span>
                                    </span>
                                    @if($this->selectedPlan->annual_savings > 0)
                                        <span class="text-sm text-green-400 mt-2 font-medium">
                                            Save ₦{{ number_format($this->selectedPlan->annual_savings) }} ({{ round($this->selectedPlan->annual_savings_percent) }}%)
                                        </span>
                                    @endif
                                </label>
                            @endif
                        </div>
                        @error('billing_cycle')
                            <p id="billing_cycle-error" class="text-red-400 text-sm mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>
        @endif

        <!-- Step 2: Company Information -->
        @if ($currentStep === 2)
            <div class="space-y-6">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-white">Company Information</h2>
                    <p class="text-gray-400 mt-2">Tell us about your business</p>
                </div>

                <!-- Company Name -->
                <div>
                    <label for="company_name" class="block text-sm font-medium text-gray-300 mb-2">
                        Company Name <span class="text-red-400">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="company_name" 
                        wire:model="company_name"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                        placeholder="Enter your company name"
                    >
                    @error('company_name')
                        <p id="company_name-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Company Email -->
                <div>
                    <label for="company_email" class="block text-sm font-medium text-gray-300 mb-2">
                        Company Email <span class="text-red-400">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="company_email" 
                        wire:model="company_email"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                        placeholder="company@example.com"
                    >
                    @error('company_email')
                        <p id="company_email-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Company Phone -->
                <div>
                    <label for="company_phone" class="block text-sm font-medium text-gray-300 mb-2">
                        Company Phone
                    </label>
                    <input 
                        type="tel" 
                        id="company_phone" 
                        wire:model="company_phone"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                        placeholder="+234 XXX XXX XXXX"
                    >
                    @error('company_phone')
                        <p id="company_phone-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Company Address -->
                <div>
                    <label for="company_address" class="block text-sm font-medium text-gray-300 mb-2">
                        Company Address
                    </label>
                    <input 
                        type="text" 
                        id="company_address" 
                        wire:model="company_address"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                        placeholder="Street address"
                    >
                    @error('company_address')
                        <p id="company_address-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- City -->
                    <div>
                        <label for="company_city" class="block text-sm font-medium text-gray-300 mb-2">City</label>
                        <input 
                            type="text" 
                            id="company_city" 
                            wire:model="company_city"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                            placeholder="City"
                        >
                        @error('company_city')
                            <p id="company_city-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- State -->
                    <div>
                        <label for="company_state" class="block text-sm font-medium text-gray-300 mb-2">State</label>
                        <input 
                            type="text" 
                            id="company_state" 
                            wire:model="company_state"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                            placeholder="State"
                        >
                        @error('company_state')
                            <p id="company_state-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Country -->
                    <div>
                        <label for="company_country" class="block text-sm font-medium text-gray-300 mb-2">Country</label>
                        <input 
                            type="text" 
                            id="company_country" 
                            wire:model="company_country"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                            placeholder="Country"
                        >
                        @error('company_country')
                            <p id="company_country-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Postal Code -->
                    <div>
                        <label for="company_postal_code" class="block text-sm font-medium text-gray-300 mb-2">Postal Code</label>
                        <input 
                            type="text" 
                            id="company_postal_code" 
                            wire:model="company_postal_code"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                            placeholder="Postal code"
                        >
                        @error('company_postal_code')
                            <p id="company_postal_code-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Currency -->
                <div>
                    <label for="currency" class="block text-sm font-medium text-gray-300 mb-2">
                        Currency <span class="text-red-400">*</span>
                    </label>
                    <select 
                        id="currency" 
                        wire:model="currency"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                    >
                        <option value="NGN">NGN - Nigerian Naira</option>
                        <option value="USD">USD - US Dollar</option>
                        <option value="EUR">EUR - Euro</option>
                        <option value="GBP">GBP - British Pound</option>
                        <option value="CAD">CAD - Canadian Dollar</option>
                        <option value="AUD">AUD - Australian Dollar</option>
                        <option value="GHS">GHS - Ghanaian Cedi</option>
                        <option value="KES">KES - Kenyan Shilling</option>
                        <option value="ZAR">ZAR - South African Rand</option>
                    </select>
                    @error('currency')
                        <p id="currency-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @endif

        <!-- Step 3: User Information -->
        @if ($currentStep === 3)
            <div class="space-y-6">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-white">Create Your Account</h2>
                    <p class="text-gray-400 mt-2">Set up your admin account credentials</p>
                </div>

                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                        Your Name <span class="text-red-400">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="name" 
                        wire:model="name"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                        placeholder="Enter your full name"
                    >
                    @error('name')
                        <p id="name-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                        Email Address <span class="text-red-400">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        wire:model="email"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                        placeholder="you@example.com"
                    >
                    <p class="text-slate-400 text-sm mt-1">We'll send a verification email to this address</p>
                    @error('email')
                        <p id="email-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-300 mb-2">Phone Number</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        wire:model="phone"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                        placeholder="+234 XXX XXX XXXX"
                    >
                    @error('phone')
                        <p id="phone-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                        Password <span class="text-red-400">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        wire:model="password"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                        placeholder="Create a strong password"
                    >
                    @error('password')
                        <p id="password-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-300 mb-2">
                        Confirm Password <span class="text-red-400">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password_confirmation" 
                        wire:model="password_confirmation"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all"
                        placeholder="Confirm your password"
                    >
                    @error('password_confirmation')
                        <p id="password_confirmation-error" class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Summary -->
                <div class="mt-6 p-4 bg-gray-800/50 rounded-xl border border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-300 mb-3">Registration Summary</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Plan:</span>
                            <span class="text-white font-medium">{{ $this->selectedPlan?->name ?? 'Not selected' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Billing:</span>
                            <span class="text-white font-medium">{{ ucfirst($billing_cycle) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Company:</span>
                            <span class="text-white font-medium">{{ $company_name ?: 'Not entered' }}</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-700 pt-2 mt-2">
                            <span class="text-gray-400">Total:</span>
                            <span class="text-xl font-bold text-brand-400">
                                ₦{{ number_format($billing_cycle === 'annual' ? ($this->selectedPlan?->annual_price ?? 0) : ($this->selectedPlan?->monthly_price ?? 0)) }}
                                <span class="text-sm font-normal text-slate-400">/{{ $billing_cycle === 'annual' ? 'year' : 'month' }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Terms Notice -->
                <p class="text-sm text-slate-400 text-center">
                    By creating an account, you agree to our 
                    <a href="#" class="text-brand-400 hover:text-brand-300">Terms of Service</a> and 
                    <a href="#" class="text-brand-400 hover:text-brand-300">Privacy Policy</a>
                </p>
            </div>
        @endif

        <!-- Navigation Buttons -->
        <div class="flex items-center justify-between mt-8 pt-6 border-t border-gray-700">
            @if ($currentStep > 1)
                <button 
                    type="button" 
                    wire:click="previousStep"
                    class="inline-flex items-center px-5 py-3 text-sm font-medium text-gray-300 hover:text-white transition-colors"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back
                </button>
            @else
                <a 
                    href="{{ route('login') }}"
                    class="text-sm text-gray-400 hover:text-brand-400 transition-colors"
                >
                    Already have an account? Sign in
                </a>
            @endif

            <button 
                type="submit"
                class="inline-flex items-center px-6 py-3 bg-brand-600 hover:bg-brand-500 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-75 cursor-not-allowed"
            >
                <span wire:loading.remove wire:target="submit">
                    @if ($currentStep === $totalSteps)
                        Create Account & Verify Email
                    @else
                        Continue
                    @endif
                </span>
                <span wire:loading wire:target="submit" class="inline-flex items-center">
                    <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...
                </span>
                @if ($currentStep < $totalSteps)
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" wire:loading.remove wire:target="submit">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                @endif
            </button>
        </div>
    </form>
</div>
