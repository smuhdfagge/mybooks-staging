<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$chartOfAccount->account_code.' '.$chartOfAccount->name" :description="ucfirst($chartOfAccount->type).' account'">
            @if ($chartOfAccount->is_system)
                <x-slot name="badge"><x-status-badge status="sent" label="Built in" /></x-slot>
            @endif
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.general-ledger', ['account_id' => $chartOfAccount->id])">General ledger</x-table.menu-item>
                @if (! $chartOfAccount->is_system && $chartOfAccount->journalEntries->count() == 0)
                    @can('delete chart-of-accounts')
                        <x-table.menu-item danger :post="route('chart-of-accounts.destroy', $chartOfAccount)" method="DELETE" confirm="Delete this account?">Delete</x-table.menu-item>
                    @endcan
                @endif
            </x-slot>
            @unless ($chartOfAccount->is_system)
                <x-slot name="actions"><a href="{{ route('chart-of-accounts.edit', $chartOfAccount) }}" class="btn-secondary">Edit</a></x-slot>
            @endunless
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            <!-- Balance Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 rounded-full p-4">
                                <svg class="w-8 h-8 text-green-700 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-brand-100 dark:bg-brand-900/50 text-brand-800 dark:text-brand-300">
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
                                    <a href="{{ route('chart-of-accounts.show', $chartOfAccount->parent) }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900">
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
                                            <span class="text-sm text-brand-600 dark:text-brand-300">{{ $child->account_code }} - {{ $child->name }}</span>
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
                        <x-table caption="Recent postings">
<x-slot name="head">
                                        <x-table.th>Date</x-table.th>
                                        <x-table.th>Journal</x-table.th>
                                        <x-table.th>Description</x-table.th>
                                        <x-table.th num>Debit</x-table.th>
                                        <x-table.th num>Credit</x-table.th>
</x-slot>

                                    @foreach($chartOfAccount->journalEntries->take(10) as $entry)
                                        <tr>
                                            <td>
                                                {{ $entry->journal->journal_date->format('j M Y') }}
                                            </td>
                                            <td>
                                                <a href="{{ route('journals.show', $entry->journal) }}" class="text-sm text-brand-600 dark:text-brand-300 hover:text-brand-900">
                                                    {{ $entry->journal->journal_number }}
                                                </a>
                                            </td>
                                            <td>
                                                {{ $entry->description ?? $entry->journal->description ?? '—' }}
                                            </td>
                                            <td class="num {{ $entry->debit > 0 ? 'font-medium text-gray-900 dark:text-gray-100' : 'tbl-zero' }}">
                                                {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}
                                            </td>
                                            <td class="num {{ $entry->credit > 0 ? 'font-medium text-gray-900 dark:text-gray-100' : 'tbl-zero' }}">
                                                {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
</x-table>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No journal entries for this account.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
