<x-layouts.admin>
    <x-slot name="header">All Tenants</x-slot>
    
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">All Tenants</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage and filter tenant accounts</p>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <form method="GET" action="{{ route('admin.tenants.list') }}" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <input aria-label="Search by name or email" type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search by name or email..."
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="w-40">
                <select name="status" class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="trial" {{ request('status') === 'trial' ? 'selected' : '' }}>On Trial</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                    <option value="no_subscription" {{ request('status') === 'no_subscription' ? 'selected' : '' }}>No Subscription</option>
                </select>
            </div>
            <div class="w-40">
                <select name="plan" class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Plans</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" {{ request('plan') == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition font-medium">
                Filter
            </button>
            @if(request()->hasAny(['search', 'status', 'plan']))
                <a href="{{ route('admin.tenants.list') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <!-- Tenants Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-750">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tenant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Plan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Billing</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expires</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($tenants as $tenant)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 rounded-lg bg-brand-600 flex items-center justify-center flex-shrink-0">
                                        <span class="text-white font-bold text-sm">{{ strtoupper(substr($tenant->name, 0, 2)) }}</span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $tenant->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col gap-1">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full inline-flex w-fit {{ $tenant->is_active ? 'bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-700' : 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-700' }}">
                                        {{ $tenant->is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                    @if($tenant->activeSubscription)
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full inline-flex w-fit 
                                            @if($tenant->activeSubscription->status === 'active') bg-brand-100 dark:bg-brand-900/50 text-brand-700 dark:text-brand-300 border border-brand-200 dark:border-brand-700
                                            @elseif($tenant->activeSubscription->status === 'trialing') bg-accent-100 dark:bg-accent-900/50 text-accent-800 dark:text-accent-300 border border-accent-200 dark:border-brand-700
                                            @else bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-700
                                            @endif">
                                            {{ ucfirst($tenant->activeSubscription->status) }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($tenant->activeSubscription?->plan)
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $tenant->activeSubscription->plan->name }}</span>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($tenant->activeSubscription)
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ ucfirst($tenant->activeSubscription->billing_cycle) }}</span>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($tenant->activeSubscription?->ends_at)
                                    <span class="text-sm @if($tenant->activeSubscription->ends_at->isPast()) text-red-600 dark:text-red-300 @elseif((int) now()->diffInDays($tenant->activeSubscription->ends_at) <= 7) text-yellow-700 dark:text-yellow-400 @else text-gray-600 dark:text-gray-400 @endif">
                                        {{ $tenant->activeSubscription->ends_at->format('M d, Y') }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <a href="{{ route('admin.tenants.show', $tenant) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-brand-600 dark:text-brand-300 hover:text-brand-800 dark:hover:text-brand-300 transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <p class="text-gray-500 dark:text-gray-400 mt-2">No tenants found</p>
                                @if(request()->hasAny(['search', 'status', 'plan']))
                                    <a href="{{ route('admin.tenants.list') }}" class="mt-2 inline-block text-brand-600 dark:text-brand-300 hover:text-brand-500 dark:hover:text-brand-300 transition">
                                        Clear filters
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($tenants->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $tenants->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
