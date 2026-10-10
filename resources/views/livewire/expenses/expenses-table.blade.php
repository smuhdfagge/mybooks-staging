{{-- Expenses list (tables plan T3), with the approval steps in each row's menu. --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $canBulk = $user->canAny(['edit expenses', 'delete expenses']) || $isAdmin;
    $ids = $expenses->pluck('id')->map(fn ($id) => (string) $id)->all();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, name, reference, vendor or amount" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="account" label="Account" :options="$accounts" />
            <x-table.pick model="vendor" label="Vendor" :options="$vendors" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit expenses')<x-table.bulk-button action="submit">Send for approval</x-table.bulk-button>@endcan
                @if ($isAdmin)
                    <x-table.bulk-button action="approve" confirm="Approve the ticked expenses that are waiting?">Approve</x-table.bulk-button>
                    <x-table.bulk-button action="mark_paid" confirm="Mark the ticked approved expenses as paid? This posts them to the books.">Mark as paid</x-table.bulk-button>
                @endif
                @can('delete expenses')<x-table.bulk-button action="delete" danger confirm="Delete the ticked expenses? Only drafts and rejected expenses are deleted.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$expenses" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($expenses->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No expenses match these filters" />
                @else
                    <x-table.empty title="No expenses yet" text="Record money spent without a bill: transport, fuel, airtime and the like.">
                        @can('create expenses')<a href="{{ route('expenses.create') }}" class="btn-new">Record expense</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Expenses" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every expense on this page" />@endif
                    <x-table.th field="expense_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>What for</x-table.th>
                    <x-table.th>Account</x-table.th>
                    <x-table.th>Vendor</x-table.th>
                    <x-table.th field="expense_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($expenses as $expense)
                    @php $ticked = in_array((string) $expense->id, $selectedItems, true); @endphp
                    <tr wire:key="ex-{{ $expense->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$expense->id" :label="$expense->expense_number" />@endif
                        <td><a href="{{ route('expenses.show', $expense) }}" class="tbl-link">{{ $expense->expense_number }}</a></td>
                        <td class="max-w-[16rem] truncate">{{ $expense->name ?: ($expense->description ?: '—') }}</td>
                        <td class="max-w-[12rem] truncate tbl-muted">{{ $expense->expenseAccount?->name ?? '—' }}</td>
                        <td class="max-w-[12rem] truncate {{ $expense->vendor ? '' : 'tbl-zero' }}">{{ $expense->vendor?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $date($expense->expense_date) }}</td>
                        <td class="num">{{ $money($expense->total) }}</td>
                        <td><x-status-badge :status="$expense->status" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$expense->expense_number">
                                <x-table.menu-item :href="route('expenses.show', $expense)">View</x-table.menu-item>
                                @if ($expense->canBeEdited() && $user->can('edit expenses'))
                                    <x-table.menu-item :href="route('expenses.edit', $expense)">Edit</x-table.menu-item>
                                @endif
                                @if ($expense->canBeSubmitted() && $user->can('edit expenses'))
                                    <x-table.menu-item wire="submitForApproval({{ $expense->id }})">Send for approval</x-table.menu-item>
                                @endif
                                @if ($isAdmin && $expense->canBeApproved())
                                    <x-table.menu-item wire="approveExpense({{ $expense->id }})">Approve</x-table.menu-item>
                                @endif
                                @if ($isAdmin && $expense->canBeRejected())
                                    <x-table.menu-item wire="openRejectModal({{ $expense->id }})">Reject</x-table.menu-item>
                                @endif
                                @if ($isAdmin && $expense->canBeMarkedAsPaid())
                                    <x-table.menu-item wire="markAsPaid({{ $expense->id }})" confirm="Mark this expense as paid? This posts it to the books.">Mark as paid</x-table.menu-item>
                                @endif
                                @if (in_array($expense->status, ['draft', 'rejected'], true) && $user->can('delete expenses'))
                                    <x-table.menu-item wire="deleteOne({{ $expense->id }})" :confirm="'Delete '.$expense->expense_number.'? This can\'t be undone.'" danger>Delete</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="5">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'expense' : 'expenses' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->total) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Expenses">
                @foreach ($expenses as $expense)
                    <li wire:key="ex-card-{{ $expense->id }}">
                        <x-table.card :href="route('expenses.show', $expense)" :title="$expense->name ?: ($expense->description ?: $expense->expense_number)" :amount="\App\Support\Money::format($expense->total)"
                            :meta="$expense->expense_number.' · '.$date($expense->expense_date).($expense->expenseAccount ? ' · '.$expense->expenseAccount->name : '')">
                            @if ($expense->status !== 'paid')
                                <x-slot name="badge"><x-status-badge :status="$expense->status" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">Total {{ \App\Support\Money::format($totals->total) }}</p>
        @endif
    </div>

    <x-table.footer :rows="$expenses" />

    {{-- Reject: a short reason goes back to whoever made the expense. --}}
    @if ($showRejectionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="reject-title"
            x-data x-trap.inert.noscroll="true" @keydown.escape.window="$wire.closeRejectModal()">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeRejectModal" aria-hidden="true"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-5 shadow-xl dark:bg-gray-800">
                <h3 id="reject-title" class="text-base font-semibold text-gray-900 dark:text-white">Reject this expense</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">It goes back to whoever made it, so they can fix it and send it again.</p>
                <label for="rejection_reason" class="form-label mt-4">Reason (optional)</label>
                <textarea id="rejection_reason" wire:model="rejectionReason" rows="3" class="form-control" placeholder="For example: receipt missing"></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeRejectModal" class="tbl-chip h-9">Keep it</button>
                    <button type="button" wire:click="rejectExpense" wire:loading.attr="disabled" class="inline-flex h-9 items-center rounded-md bg-red-700 px-3 text-sm font-semibold text-white hover:bg-red-800">Reject</button>
                </div>
            </div>
        </div>
    @endif
</div>
