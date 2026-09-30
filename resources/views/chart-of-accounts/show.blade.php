<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $chartOfAccount->account_code }} - {{ $chartOfAccount->name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 capitalize">{{ $chartOfAccount->type }} Account</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(!$chartOfAccount->is_system)
                    <a href="{{ route('chart-of-accounts.edit', $chartOfAccount) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                @endif
                <a href="{{ route('chart-of-accounts.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">


            <!-- Balance Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 rounded-full p-4">
                                <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Balance</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($chartOfAccount->current_balance, 2) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            @if($chartOfAccount->is_active)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-400">
                                    Inactive
                                </span>
                            @endif
                            @if($chartOfAccount->is_system)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-400">
                                    System Account
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Account Details -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Account Details</h3>
                        <dl class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Code</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $chartOfAccount->account_code }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Name</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $chartOfAccount->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 capitalize">{{ $chartOfAccount->type }}</dd>
                            </div>
                            @if($chartOfAccount->sub_type)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Sub Type</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $chartOfAccount->sub_type }}</dd>
                            </div>
                            @endif
                            @if($chartOfAccount->parent)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Parent Account</dt>
                                <dd class="mt-1 text-sm">
                                    <a href="{{ route('chart-of-accounts.show', $chartOfAccount->parent) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900">
                                        {{ $chartOfAccount->parent->account_code }} - {{ $chartOfAccount->parent->name }}
                                    </a>
                                </dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Opening Balance</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ number_format($chartOfAccount->opening_balance, 2) }}</dd>
                            </div>
                        </dl>
                        @if($chartOfAccount->description)
                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $chartOfAccount->description }}</dd>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Sub Accounts -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Sub Accounts</h3>
                        @if($chartOfAccount->children->count() > 0)
                            <ul class="space-y-2">
                                @foreach($chartOfAccount->children as $child)
                                    <li>
                                        <a href="{{ route('chart-of-accounts.show', $child) }}" class="flex items-center justify-between p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <span class="text-sm text-indigo-600 dark:text-indigo-400">{{ $child->account_code }} - {{ $child->name }}</span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($child->current_balance, 2) }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">No sub accounts.</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Recent Journal Entries -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Recent Journal Entries</h3>
                    @if($chartOfAccount->journalEntries->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Journal</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Debit</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Credit</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($chartOfAccount->journalEntries->take(10) as $entry)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $entry->journal->journal_date->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a href="{{ route('journals.show', $entry->journal) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900">
                                                    {{ $entry->journal->journal_number }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $entry->description ?? $entry->journal->description ?? '—' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $entry->debit > 0 ? 'font-medium text-gray-900 dark:text-gray-100' : 'text-gray-400' }}">
                                                {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $entry->credit > 0 ? 'font-medium text-gray-900 dark:text-gray-100' : 'text-gray-400' }}">
                                                {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No journal entries for this account.</p>
                    @endif
                </div>
            </div>

            <!-- Actions -->
            @if(!$chartOfAccount->is_system)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6 flex flex-wrap gap-4">
                    <a href="{{ route('chart-of-accounts.edit', $chartOfAccount) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Account
                    </a>

                    @if($chartOfAccount->journalEntries->count() == 0)
                        <form action="{{ route('chart-of-accounts.destroy', $chartOfAccount) }}" method="POST" class="inline" data-confirm="Are you sure you want to delete this account?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Delete
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
