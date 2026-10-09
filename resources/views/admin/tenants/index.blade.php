<x-layouts.admin>
    <x-slot name="header">Dashboard</x-slot>
    
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tenant Dashboard</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Overview of all tenants and subscriptions</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
        <!-- Total Tenants -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 rounded-lg bg-brand-100 dark:bg-brand-900/50">
                    <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Tenants</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_tenants']) }}</p>
                </div>
            </div>
        </div>

        <!-- Active Tenants -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 rounded-lg bg-green-100 dark:bg-green-900/50">
                    <svg class="w-6 h-6 text-green-700 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Tenants</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['active_tenants']) }}</p>
                </div>
            </div>
        </div>

        <!-- Active Subscriptions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 rounded-lg bg-brand-100 dark:bg-brand-900/50">
                    <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Subs</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['active_subscriptions']) }}</p>
                </div>
            </div>
        </div>

        <!-- Expired Subscriptions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 rounded-lg bg-yellow-100 dark:bg-yellow-900/50">
                    <svg class="w-6 h-6 text-yellow-700 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Expired</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['expired_subscriptions']) }}</p>
                </div>
            </div>
        </div>

        <!-- Monthly Revenue -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 rounded-lg bg-green-100 dark:bg-green-900/50">
                    <svg class="w-6 h-6 text-green-700 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Monthly Rev</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">₦{{ number_format($stats['monthly_revenue']) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Tenants -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Tenants</h3>
                <a href="{{ route('admin.tenants.list') }}" class="text-sm text-brand-600 dark:text-brand-300 hover:text-brand-500 dark:hover:text-brand-300 transition">View all →</a>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($recentTenants as $tenant)
                    <a href="{{ route('admin.tenants.show', $tenant) }}" class="flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-lg bg-brand-600 flex items-center justify-center">
                                <span class="text-white font-bold text-sm">{{ strtoupper(substr($tenant->name, 0, 2)) }}</span>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $tenant->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant->email }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            @if($tenant->activeSubscription)
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full 
                                    @if($tenant->activeSubscription->status === 'active') bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-700
                                    @elseif($tenant->activeSubscription->status === 'trialing') bg-brand-100 dark:bg-brand-900/50 text-brand-700 dark:text-brand-300 border border-brand-200 dark:border-brand-700
                                    @else bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-700
                                    @endif">
                                    {{ $tenant->activeSubscription->plan->name ?? 'Unknown' }}
                                </span>
                            @else
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-600">
                                    No Subscription
                                </span>
                            @endif
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $tenant->created_at->diffForHumans() }}</p>
                        </div>
                    </a>
                @empty
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        No tenants found
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Expiring Soon -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Expiring Soon</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Within 7 days</p>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($expiringSubscriptions as $subscription)
                    <a href="{{ route('admin.tenants.show', $subscription->tenant) }}" class="block p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $subscription->tenant->name }}</p>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300">
                                {{ (int) now()->diffInDays($subscription->ends_at) }} days
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $subscription->plan->name ?? 'Unknown' }} - Expires {{ $subscription->ends_at->format('M d, Y') }}</p>
                    </a>
                @empty
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        No subscriptions expiring soon
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Plans Overview -->
    <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Plans Overview</h3>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($plans as $plan)
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ $plan->name }}</h4>
                        <span class="text-2xl font-bold text-brand-600 dark:text-brand-300">{{ $plan->subscriptions_count }}</span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">₦{{ number_format($plan->monthly_price) }}/mo</p>
                    <div class="mt-3 flex items-center gap-2">
                        <a href="{{ route('admin.tenants.list', ['plan' => $plan->id]) }}" class="text-xs text-brand-600 dark:text-brand-300 hover:underline">View tenants →</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.admin>
