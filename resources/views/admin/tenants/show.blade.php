<x-layouts.admin>
    <x-slot name="header">{{ $tenant->name }}</x-slot>
    
    <!-- Page Header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center">
                    <span class="text-white font-bold text-lg">{{ strtoupper(substr($tenant->name, 0, 2)) }}</span>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $tenant->name }}</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Tenant Details & Subscription Management</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('admin.tenants.toggle-status', $tenant) }}" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition {{ $tenant->is_active ? 'bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-700 hover:bg-green-200 dark:hover:bg-green-900' : 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-700 hover:bg-red-200 dark:hover:bg-red-900' }}">
                    @if($tenant->is_active)
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Active
                    @else
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        Disabled
                    @endif
                </button>
            </form>
            <a href="{{ route('admin.tenants.list') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition text-sm font-medium">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
                Back to List
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Tenant Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Company Information -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Company Information</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Company Name</label>
                            <p class="text-gray-900 dark:text-white mt-1">{{ $tenant->name }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</label>
                            <p class="text-gray-900 dark:text-white mt-1">{{ $tenant->email }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</label>
                            <p class="text-gray-900 dark:text-white mt-1">{{ $tenant->phone ?? '-' }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Address</label>
                            <p class="text-gray-900 dark:text-white mt-1">{{ $tenant->address ?? '-' }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</label>
                            <p class="text-gray-900 dark:text-white mt-1">{{ $tenant->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                            <p class="mt-1">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $tenant->is_active ? 'bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-700' : 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-700' }}">
                                    {{ $tenant->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Users ({{ $tenant->users->count() }})</h3>
                    @if($tenant->activeSubscription?->plan)
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Limit: {{ $tenant->activeSubscription->plan->user_limit > 0 ? $tenant->activeSubscription->plan->user_limit : 'Unlimited' }}
                        </span>
                    @endif
                </div>
                <div class="p-6">
                    @if($tenant->users->count() > 0)
                        <div class="space-y-3">
                            @foreach($tenant->users->take(5) as $user)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center">
                                            <span class="text-white font-semibold text-sm">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        @if($user->roles->isNotEmpty())
                                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-700">
                                                {{ $user->roles->first()->name }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                            @if($tenant->users->count() > 5)
                                <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-3">
                                    And {{ $tenant->users->count() - 5 }} more users...
                                </p>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 mt-2">No users found</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Subscription History -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Subscription History</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-750">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Billing</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Period</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($tenant->subscriptions as $subscription)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $subscription->plan->name ?? 'Unknown' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full 
                                            @if($subscription->status === 'active') bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-700
                                            @elseif($subscription->status === 'trialing') bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-700
                                            @elseif($subscription->status === 'cancelled') bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-700
                                            @else bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-700
                                            @endif">
                                            {{ ucfirst($subscription->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        {{ ucfirst($subscription->billing_cycle) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        {{ $subscription->starts_at->format('M d, Y') }} - {{ $subscription->ends_at?->format('M d, Y') ?? 'Ongoing' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No subscription history
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Subscription Management -->
        <div class="space-y-6">
            <!-- Current Subscription -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Current Subscription</h3>
                </div>
                <div class="p-6">
                    @if($tenant->activeSubscription)
                        <div class="space-y-4">
                            <div class="text-center p-4 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl">
                                <p class="text-2xl font-bold text-white">{{ $tenant->activeSubscription->plan->name ?? 'Unknown' }}</p>
                                <p class="text-sm text-indigo-100 mt-1">{{ ucfirst($tenant->activeSubscription->billing_cycle) }} billing</p>
                            </div>
                            
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Status</span>
                                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full 
                                        @if($tenant->activeSubscription->status === 'active') bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300
                                        @elseif($tenant->activeSubscription->status === 'trialing') bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300
                                        @else bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300
                                        @endif">
                                        {{ ucfirst($tenant->activeSubscription->status) }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Started</span>
                                    <span class="text-gray-900 dark:text-white">{{ $tenant->activeSubscription->starts_at->format('M d, Y') }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Expires</span>
                                    <span class="text-gray-900 dark:text-white">{{ $tenant->activeSubscription->ends_at?->format('M d, Y') ?? 'Never' }}</span>
                                </div>
                                @if($tenant->activeSubscription->ends_at)
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Days Left</span>
                                        <span class="@if($tenant->activeSubscription->ends_at->diffInDays(now()) <= 7) text-yellow-600 dark:text-yellow-400 @else text-gray-900 dark:text-white @endif">
                                            {{ $tenant->activeSubscription->ends_at->diffInDays(now()) }} days
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 mt-2">No active subscription</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Update Subscription -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Update Subscription</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.tenants.update-subscription', $tenant) }}">
                        @csrf
                        @method('PUT')
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Plan</label>
                                <select name="plan_id" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->id }}" {{ $tenant->activeSubscription?->plan_id == $plan->id ? 'selected' : '' }}>
                                            {{ $plan->name }} - ₦{{ number_format($plan->monthly_price) }}/mo
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Billing Cycle</label>
                                <select name="billing_cycle" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="monthly" {{ $tenant->activeSubscription?->billing_cycle === 'monthly' ? 'selected' : '' }}>Monthly</option>
                                    <option value="annual" {{ $tenant->activeSubscription?->billing_cycle === 'annual' ? 'selected' : '' }}>Annual (Save 17%)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                <select name="status" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="active" {{ $tenant->activeSubscription?->status === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="trialing" {{ $tenant->activeSubscription?->status === 'trialing' ? 'selected' : '' }}>Trial</option>
                                    <option value="past_due" {{ $tenant->activeSubscription?->status === 'past_due' ? 'selected' : '' }}>Past Due</option>
                                </select>
                            </div>
                            <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium">
                                Update Subscription
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Extend Subscription -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Extend Subscription</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.tenants.extend-subscription', $tenant) }}">
                        @csrf
                        @method('PATCH')
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Extend By</label>
                                <select name="extension_days" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="7">7 Days</option>
                                    <option value="14">14 Days</option>
                                    <option value="30" selected>30 Days (1 Month)</option>
                                    <option value="90">90 Days (3 Months)</option>
                                    <option value="180">180 Days (6 Months)</option>
                                    <option value="365">365 Days (1 Year)</option>
                                </select>
                            </div>
                            <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                                Extend Subscription
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cancel Subscription -->
            @if($tenant->activeSubscription && $tenant->activeSubscription->status !== 'cancelled')
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl border border-red-200 dark:border-red-900/50">
                    <div class="px-6 py-4 border-b border-red-200 dark:border-red-900/50">
                        <h3 class="text-lg font-semibold text-red-600 dark:text-red-400">Danger Zone</h3>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Cancelling the subscription will immediately disable access to premium features.
                        </p>
                        <form method="POST" action="{{ route('admin.tenants.cancel-subscription', $tenant) }}" onsubmit="return confirm('Are you sure you want to cancel this subscription?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                                Cancel Subscription
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
