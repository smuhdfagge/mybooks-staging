<div>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Subscription Management</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage your subscription plan and billing</p>
        </div>

        {{-- On a full page load the layout shows the message; here only after an action (U9). --}}
        @if (\Livewire\Livewire::isLivewireRequest() && session()->has('success'))
            <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <p class="ml-3 text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if (\Livewire\Livewire::isLivewireRequest() && session()->has('error'))
            <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <p class="text-sm text-red-800 dark:text-red-200">{{ session('error') }}</p>
            </div>
        @endif

        <!-- Current Subscription Card -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-8">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Current Subscription</h3>
            </div>
            <div class="p-6">
                @if($currentSubscription && $currentPlan)
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-xl font-bold text-gray-900 dark:text-white">{{ $currentPlan->name }} Plan</h4>
                                <div class="flex items-center mt-1">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        @if($currentSubscription->isCancelled()) bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @elseif($currentSubscription->status === 'active') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif($currentSubscription->status === 'cancelled') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @elseif($currentSubscription->status === 'past_due') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                        @endif">
                                        {{ $currentSubscription->isCancelled() ? 'Cancelled' : ucfirst(str_replace('_', ' ', $currentSubscription->status)) }}
                                    </span>
                                    <span class="mx-2 text-gray-500 dark:text-gray-400">•</span>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ ucfirst($currentSubscription->billing_cycle) }} billing</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0 text-right">
                            <div class="text-3xl font-bold text-gray-900 dark:text-white">
                                ₦{{ number_format($currentSubscription->amount) }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                per {{ $currentSubscription->billing_cycle === 'monthly' ? 'month' : 'year' }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Users Allowed</div>
                                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $currentPlan->max_users }} users</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    @if($currentSubscription->isCancelled())
                                        Access Until
                                    @else
                                        Next Billing Date
                                    @endif
                                </div>
                                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $currentSubscription->ends_at?->format('M d, Y') ?? 'No end date' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Days Remaining</div>
                                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $currentSubscription->daysUntilExpiration() ?? '—' }} days</div>
                            </div>
                        </div>
                    </div>

                    @if($canManage)
                    <div class="mt-6 flex flex-wrap gap-3">
                        @if($currentSubscription->ends_at && (float) $currentSubscription->amount > 0)
                        <button wire:click="renew" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                            Pay for another {{ $currentSubscription->billing_cycle === 'annual' ? 'year' : 'month' }}
                        </button>
                        @endif
                        <button wire:click="openUpgradeModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Change Plan
                        </button>
                        @if($currentSubscription->isCancelled())
                            <button wire:click="reactivateSubscription" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Reactivate Subscription
                            </button>
                        @else
                            <button wire:click="openCancelModal" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-red-300 dark:border-red-600 text-red-600 dark:text-red-400 text-sm font-medium rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Cancel Subscription
                            </button>
                        @endif
                    </div>
                    @else
                    <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">Only an administrator can change or cancel the subscription.</p>
                    @endif
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        @if($pendingSubscription)
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Finish setting up your subscription</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                You chose the {{ $pendingSubscription->plan?->name }} plan, billed {{ $pendingSubscription->billing_cycle === 'annual' ? 'yearly' : 'monthly' }}
                                (₦{{ number_format((float) $pendingSubscription->amount, 2) }}). Pay to start using MyBooks.
                            </p>
                        @elseif($lapsedSubscription)
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Your subscription has ended</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Your {{ $lapsedSubscription->plan?->name }} plan ended{{ $lapsedSubscription->ends_at ? ' on '.$lapsedSubscription->ends_at->format('M d, Y') : '' }}. Your data is safe; renew to get back in.
                            </p>
                        @else
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No active subscription</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by choosing a plan below.</p>
                        @endif
                        <div class="mt-6 flex flex-wrap justify-center gap-3">
                            @if($canManage && $pendingSubscription)
                            <button wire:click="payPending" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                                Pay now
                            </button>
                            @elseif($canManage && $lapsedSubscription?->plan?->is_active)
                            <button wire:click="renew" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                                Renew {{ $lapsedSubscription->plan->name }}
                            </button>
                            @endif
                            @if($canManage)
                            <button wire:click="openUpgradeModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                {{ $pendingSubscription || $lapsedSubscription ? 'Choose a different plan' : 'Choose a Plan' }}
                            </button>
                            @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">Ask an administrator to choose a plan.</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Available Plans -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Available Plans</h3>
            </div>
            <div class="p-6">
                <div class="grid md:grid-cols-3 gap-6">
                    @foreach($plans as $plan)
                        <div class="relative rounded-lg border-2 p-6 transition-all
                            {{ $currentPlan && $currentPlan->id === $plan->id 
                                ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20' 
                                : 'border-gray-200 dark:border-gray-700 hover:border-indigo-400' }}">
                            @if($plan->slug === 'professional')
                                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-yellow-400 text-gray-900 px-3 py-0.5 rounded-full text-xs font-semibold">Popular</div>
                            @endif
                            @if($currentPlan && $currentPlan->id === $plan->id)
                                <div class="absolute top-3 right-3 bg-indigo-600 text-white px-2 py-0.5 rounded text-xs font-semibold">Current</div>
                            @endif
                            
                            <div class="text-center mb-4">
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white">{{ $plan->name }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $plan->description }}</p>
                            </div>

                            <div class="text-center mb-4">
                                <div class="text-3xl font-bold text-gray-900 dark:text-white">₦{{ number_format($plan->monthly_price) }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">/month</div>
                                @if($plan->allow_annual_billing && $plan->annual_savings > 0)
                                    <div class="mt-1 text-sm text-green-600 dark:text-green-400">
                                        or ₦{{ number_format($plan->annual_price) }}/year (Save {{ round($plan->annual_savings_percent) }}%)
                                    </div>
                                @endif
                            </div>

                            <ul class="space-y-3 mb-6">
                                <li class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Up to {{ $plan->max_users }} users
                                </li>
                                @php $features = $plan->features ?? []; @endphp
                                @foreach(array_slice($features, 0, 4) as $feature)
                                    <li class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>

                            @if($canManage && (!$currentPlan || $currentPlan->id !== $plan->id))
                                <button wire:click="openUpgradeModal({{ $plan->id }})" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                                    @if($currentPlan && $currentPlan->monthly_price > $plan->monthly_price)
                                        Downgrade
                                    @else
                                        Upgrade
                                    @endif
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Change Plan Modal -->
    @if($showUpgradeModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="closeUpgradeModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Change Subscription Plan</h3>
                        
                        <!-- Plan Selection -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Plan</label>
                            <select wire:model.live="selectedPlanId" class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} - ₦{{ number_format($plan->monthly_price) }}/mo ({{ $plan->max_users }} users)</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Billing Cycle -->
                        @php $selectedPlanObj = $plans->firstWhere('id', $selectedPlanId); @endphp
                        @if($selectedPlanObj)
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Billing Cycle</label>
                                <div class="grid grid-cols-2 gap-3">
                                    @if($selectedPlanObj->allow_monthly_billing)
                                        <label class="relative flex items-center justify-center p-3 cursor-pointer rounded-lg border-2 transition-all {{ $selectedBillingCycle === 'monthly' ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700' }}">
                                            <input type="radio" wire:model.live="selectedBillingCycle" value="monthly" class="sr-only">
                                            <div class="text-center">
                                                <div class="font-semibold text-gray-900 dark:text-white">Monthly</div>
                                                <div class="text-sm text-gray-500">₦{{ number_format($selectedPlanObj->monthly_price) }}/mo</div>
                                            </div>
                                        </label>
                                    @endif
                                    @if($selectedPlanObj->allow_annual_billing)
                                        <label class="relative flex items-center justify-center p-3 cursor-pointer rounded-lg border-2 transition-all {{ $selectedBillingCycle === 'annual' ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700' }}">
                                            <input type="radio" wire:model.live="selectedBillingCycle" value="annual" class="sr-only">
                                            <div class="text-center">
                                                <div class="font-semibold text-gray-900 dark:text-white">Annual</div>
                                                <div class="text-sm text-gray-500">₦{{ number_format($selectedPlanObj->annual_price) }}/yr</div>
                                                @if($selectedPlanObj->annual_savings > 0)
                                                    <div class="text-xs text-green-600 dark:text-green-400">Save {{ round($selectedPlanObj->annual_savings_percent) }}%</div>
                                                @endif
                                            </div>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button wire:click="changePlan" wire:loading.attr="disabled" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Continue to payment
                        </button>
                        <button wire:click="closeUpgradeModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Cancel Subscription Modal -->
    @if($showCancelModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="closeCancelModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Cancel Subscription</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to cancel your subscription? You will continue to have access until 
                                        <span class="font-semibold">{{ $currentSubscription?->ends_at?->format('M d, Y') }}</span>.
                                    </p>
                                </div>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Reason for cancellation (optional)</label>
                                    <textarea wire:model="cancellationReason" rows="3" class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Tell us why you're leaving..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button wire:click="cancelSubscription" wire:loading.attr="disabled" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Yes, Cancel Subscription
                        </button>
                        <button wire:click="closeCancelModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Keep Subscription
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
