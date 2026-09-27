<div class="relative py-6">
    <x-table-loading />
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Flash Messages -->
        <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <!-- Search and Filters -->
                <div class="mb-6 flex flex-col sm:flex-row gap-4 justify-between">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search accounts..."
                                class="w-full sm:w-80 pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>
                        <select wire:model.live="typeFilter" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Types</option>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <!-- Bulk Actions -->
                        <x-bulk-actions :actions="['activate' => 'Activate', 'deactivate' => 'Deactivate', 'delete' => 'Delete']" :selectedCount="count($selectedItems)" />
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- View mode toggle --}}
                        <div class="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                            <button wire:click="$set('viewMode', 'tree')" class="px-3 py-1.5 text-xs font-medium transition-colors {{ $viewMode === 'tree' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600' }}" title="Tree View" aria-label="Tree View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            </button>
                            <button wire:click="$set('viewMode', 'flat')" class="px-3 py-1.5 text-xs font-medium transition-colors {{ $viewMode === 'flat' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600' }}" title="Table View" aria-label="Table View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 18h18M3 6h18"/></svg>
                            </button>
                        </div>
                        <label class="text-sm text-gray-600 dark:text-gray-400">Show:</label>
                        <select wire:model.live="perPage" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <!-- Account Type Summary -->
                <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    @php
                        $assetTotal = \App\Models\ChartOfAccount::where('type', 'asset')->sum('current_balance');
                        $liabilityTotal = \App\Models\ChartOfAccount::where('type', 'liability')->sum('current_balance');
                        $equityTotal = \App\Models\ChartOfAccount::where('type', 'equity')->sum('current_balance');
                        $incomeTotal = \App\Models\ChartOfAccount::where('type', 'income')->sum('current_balance');
                        $expenseTotal = \App\Models\ChartOfAccount::where('type', 'expense')->sum('current_balance');
                    @endphp
                    <div class="bg-white dark:bg-gray-700 overflow-hidden shadow-sm rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 rounded-full p-3">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Assets</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($assetTotal, 2) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-700 overflow-hidden shadow-sm rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-red-100 dark:bg-red-900 rounded-full p-3">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Liabilities</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($liabilityTotal, 2) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-700 overflow-hidden shadow-sm rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 rounded-full p-3">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Equity</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($equityTotal, 2) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-700 overflow-hidden shadow-sm rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 rounded-full p-3">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Income</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($incomeTotal, 2) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-700 overflow-hidden shadow-sm rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 rounded-full p-3">
                                <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Expenses</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($expenseTotal, 2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tree View --}}
                @if($viewMode === 'tree')
                <div class="space-y-3">
                    @php
                        $typeConfig = [
                            'asset' => ['label' => 'Assets', 'color' => 'blue', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                            'liability' => ['label' => 'Liabilities', 'color' => 'red', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
                            'equity' => ['label' => 'Equity', 'color' => 'purple', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                            'income' => ['label' => 'Income', 'color' => 'green', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
                            'expense' => ['label' => 'Expenses', 'color' => 'yellow', 'icon' => 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'],
                        ];
                    @endphp

                    @foreach($typeConfig as $typeKey => $config)
                        @if(isset($groupedAccounts[$typeKey]) && $groupedAccounts[$typeKey]->count() > 0)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            {{-- Type header --}}
                            <button wire:click="toggleType('{{ $typeKey }}')"
                                    class="w-full flex items-center justify-between px-4 py-3 bg-{{ $config['color'] }}-50 dark:bg-{{ $config['color'] }}-900/20 hover:bg-{{ $config['color'] }}-100 dark:hover:bg-{{ $config['color'] }}-900/30 transition-colors"
                                    aria-expanded="{{ !in_array($typeKey, $collapsedTypes) ? 'true' : 'false' }}">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-{{ $config['color'] }}-600 dark:text-{{ $config['color'] }}-400 transition-transform duration-200 {{ in_array($typeKey, $collapsedTypes) ? '-rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    <svg class="w-5 h-5 text-{{ $config['color'] }}-600 dark:text-{{ $config['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $config['icon'] }}"/>
                                    </svg>
                                    <span class="font-semibold text-{{ $config['color'] }}-800 dark:text-{{ $config['color'] }}-300">{{ $config['label'] }}</span>
                                    <span class="text-xs text-{{ $config['color'] }}-600 dark:text-{{ $config['color'] }}-400 bg-{{ $config['color'] }}-100 dark:bg-{{ $config['color'] }}-900/50 px-2 py-0.5 rounded-full">
                                        {{ $groupedAccounts[$typeKey]->count() }} accounts
                                    </span>
                                </div>
                                <span class="text-sm font-bold text-{{ $config['color'] }}-800 dark:text-{{ $config['color'] }}-300">
                                    {{ number_format($groupedAccounts[$typeKey]->sum('current_balance'), 2) }}
                                </span>
                            </button>

                            {{-- Accounts list --}}
                            @if(!in_array($typeKey, $collapsedTypes))
                            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($groupedAccounts[$typeKey]->sortBy('account_code') as $account)
                                <div class="flex items-center justify-between px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors {{ $account->parent_id ? 'pl-10' : '' }}">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if($account->parent_id)
                                            <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        @endif
                                        <code class="text-xs font-mono text-gray-500 dark:text-gray-400 flex-shrink-0">{{ $account->account_code }}</code>
                                        <a href="{{ route('chart-of-accounts.show', $account) }}" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 truncate">
                                            {{ $account->name }}
                                        </a>
                                        @if($account->is_system)
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400 flex-shrink-0">System</span>
                                        @endif
                                        @if(!$account->is_active)
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 flex-shrink-0">Inactive</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 flex-shrink-0">
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100 tabular-nums">
                                            {{ number_format($account->current_balance, 2) }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <a href="{{ route('chart-of-accounts.show', $account) }}" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="View" aria-label="View {{ $account->name }}">
                                                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                            @if(!$account->is_system)
                                            <a href="{{ route('chart-of-accounts.edit', $account) }}" class="p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Edit" aria-label="Edit {{ $account->name }}">
                                                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        @endif
                    @endforeach

                    @if(collect($groupedAccounts)->flatten()->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No accounts found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your search or filters.</p>
                        </div>
                    @endif
                </div>
                @endif

                {{-- Flat Table View --}}
                @if($viewMode === 'flat')
                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">
                                    <input type="checkbox" wire:model.live="selectAll"
                                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                                </th>
                                <th scope="col" wire:click="sortBy('account_code')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <div class="flex items-center space-x-1">
                                        <span>Code</span>
                                        @if($sortField === 'account_code')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" wire:click="sortBy('name')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <div class="flex items-center space-x-1">
                                        <span>Account Name</span>
                                        @if($sortField === 'name')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" wire:click="sortBy('type')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <div class="flex items-center space-x-1">
                                        <span>Type</span>
                                        @if($sortField === 'type')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Parent Account</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th scope="col" wire:click="sortBy('current_balance')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <div class="flex items-center justify-end space-x-1">
                                        <span>Balance</span>
                                        @if($sortField === 'current_balance')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($accounts as $account)
                                <tr wire:key="account-{{ $account->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-4">
                                        <input type="checkbox" wire:model.live="selectedItems" value="{{ $account->id }}"
                                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $account->account_code }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('chart-of-accounts.show', $account) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 font-medium">
                                            {{ $account->name }}
                                        </a>
                                        @if($account->is_system)
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-400">
                                                System
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $typeColors = [
                                                'asset' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-400',
                                                'liability' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400',
                                                'equity' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-400',
                                                'income' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400',
                                                'expense' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-400',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$account->type] ?? 'bg-gray-100 text-gray-800' }} capitalize">
                                            {{ $account->type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if($account->parent)
                                            <a href="{{ route('chart-of-accounts.show', $account->parent) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900">
                                                {{ $account->parent->name }}
                                            </a>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @if($account->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-400">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100 text-right">
                                        {{ number_format($account->current_balance, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            <a href="{{ route('chart-of-accounts.show', $account) }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200" title="View" aria-label="View">
                                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                            @if(!$account->is_system)
                                                <a href="{{ route('chart-of-accounts.edit', $account) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300" title="Edit" aria-label="Edit">
                                                    <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No accounts found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new account.</p>
                                        <div class="mt-6">
                                            <a href="{{ route('chart-of-accounts.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                New Account
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($accounts->hasPages())
                    <div class="mt-6">
                        {{ $accounts->links() }}
                    </div>
                @endif
                @endif {{-- end flat view --}}
            </div>
        </div>
    </div>
</div>
