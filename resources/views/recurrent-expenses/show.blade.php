<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$recurrentExpense->profile_name" :description="ucfirst($recurrentExpense->frequency).' · '.($recurrentExpense->expenseAccount->name ?? 'Unknown account')">
            <x-slot name="badge"><x-status-badge :status="$recurrentExpense->status === 'active' ? 'active' : 'paused'" :label="$recurrentExpense->status === 'active' ? 'Active' : 'Paused'" /></x-slot>
            <x-slot name="more">
                <x-table.menu-item :post="route('recurrent-expenses.toggle', $recurrentExpense)">{{ $recurrentExpense->status === 'active' ? 'Pause' : 'Start again' }}</x-table.menu-item>
                @can('delete recurrent-expenses')
                    <x-table.menu-item danger :post="route('recurrent-expenses.destroy', $recurrentExpense)" method="DELETE" confirm="Delete this profile? Bills already made stay.">Delete profile</x-table.menu-item>
                @endcan
            </x-slot>
            <x-slot name="actions"><a href="{{ route('recurrent-expenses.edit', $recurrentExpense) }}" class="btn-secondary">Edit</a></x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <!-- Status & Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</div>
                    <div class="mt-1">
                        <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full {{ $recurrentExpense->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300' }}">
                            {{ ucfirst($recurrentExpense->status ?? 'active') }}
                        </span>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Amount</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">@money($recurrentExpense->amount)</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Next Expense Date</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $recurrentExpense->next_expense_date?->format('M d, Y') ?? '—' }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Expenses Generated</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $recurrentExpense->expenses->count() }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Profile Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Expense Info -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                Expense Details
                            </h3>
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expense Account</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentExpense->expenseAccount->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Amount</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">@money($recurrentExpense->amount)</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Paid Through</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentExpense->paidThroughAccount->name ?? 'Not specified' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Vendor</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        @if($recurrentExpense->vendor)
                                            <a href="{{ route('vendors.show', $recurrentExpense->vendor) }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900">
                                                {{ $recurrentExpense->vendor->name }}
                                            </a>
                                        @else
                                            Not specified
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Schedule Info -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Schedule Details
                            </h3>
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Frequency</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ ucfirst($recurrentExpense->frequency) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Start Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentExpense->start_date->format('M d, Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentExpense->end_date?->format('M d, Y') ?? 'Indefinite' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Next Expense Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentExpense->next_expense_date?->format('M d, Y') ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Description -->
                    @if($recurrentExpense->description)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Description</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $recurrentExpense->description }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Generated Expenses -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Generated Expenses</h3>
                            @if($recurrentExpense->expenses->count() > 0)
                                <ul class="space-y-2">
                                    @foreach($recurrentExpense->expenses->take(10) as $expense)
                                        <li class="flex justify-between items-center text-sm">
                                            <a href="{{ route('expenses.show', $expense) }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900">
                                                {{ $expense->expense_number }}
                                            </a>
                                            <span class="text-gray-500 dark:text-gray-400">{{ $expense->expense_date->format('M d, Y') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if($recurrentExpense->expenses->count() > 10)
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">And {{ $recurrentExpense->expenses->count() - 10 }} more...</p>
                                @endif
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">No expenses generated yet.</p>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
