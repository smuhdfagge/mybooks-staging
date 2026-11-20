{{--
    The expense page. The payment voucher itself, exactly as it will be
    filed and signed, sits on the left; what the business needs to act on
    (approval, posting, vendor, activity) sits on the right. Print prints
    just the voucher from this page; nothing opens in a new tab.
--}}
<x-app-layout>
    @php
        $state = $document['state'];
        $currency = $document['currency'];
        $money = fn ($value) => \App\Support\Money::format($value, $currency);
        $user = auth()->user();
        $canApprove = $user->hasRole('admin') || $user->isSuperAdmin();
        $printIcon = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>';
    @endphp

    <x-slot name="header">
        <x-table.page-header :title="'Expense '.$expense->expense_number">
            <x-slot name="badge"><x-status-badge :status="$state['tone']" :label="$state['label']" /></x-slot>
            <x-slot name="description">
                @if ($expense->vendor)
                    <a href="{{ route('vendors.show', $expense->vendor) }}" class="link underline">{{ $expense->vendor->name }}</a> ·
                @endif
                {{ $expense->expense_date->format('j M Y') }} · {{ $money($expense->total) }}
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('expenses.pdf', $expense)">Download PDF</x-table.menu-item>
                @if ($expense->canBeEdited())
                    @can('delete expenses')
                        <x-table.menu-item danger :post="route('expenses.destroy', $expense)" method="DELETE" confirm="Delete this expense?">Delete</x-table.menu-item>
                    @endcan
                @endif
            </x-slot>
            <x-slot name="actions">
                <button type="button" class="btn-secondary gap-2" data-print data-testid="print-expense">{!! $printIcon !!}<span>Print</span></button>
                @if ($expense->canBeEdited())
                    @can('edit expenses')
                        <a href="{{ route('expenses.edit', $expense) }}" class="btn-secondary">Edit</a>
                    @endcan
                @endif
                @if ($expense->canBeSubmitted())
                    <form action="{{ route('expenses.submit', $expense) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-primary">Submit for approval</button>
                    </form>
                @elseif ($canApprove && $expense->canBeApproved())
                    <form action="{{ route('expenses.approve', $expense) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-primary">Approve</button>
                    </form>
                @elseif ($canApprove && $expense->canBeMarkedAsPaid())
                    <form action="{{ route('expenses.mark-paid', $expense) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-primary">Mark as paid</button>
                    </form>
                @endif
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] xl:grid-cols-[minmax(0,1fr)_21rem] print:block">
        {{-- The voucher, on its sheet of paper --}}
        <div class="min-w-0 space-y-6 print:space-y-0">
            @if ($expense->isRejected() && $expense->rejection_reason)
                <div class="rounded-lg border-l-4 border-red-500 bg-red-50 p-4 dark:border-red-600 dark:bg-red-900/20 print:hidden">
                    <h2 class="text-sm font-medium text-red-800 dark:text-red-300">This expense was rejected</h2>
                    <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $expense->rejection_reason }}</p>
                    @if ($expense->rejectedByUser)
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                            Rejected by {{ $expense->rejectedByUser->name }} on {{ $expense->rejected_at?->format('j M Y, H:i') }}
                        </p>
                    @endif
                </div>
            @endif

            <section aria-label="The payment voucher as it will be filed" class="rounded-xl bg-gray-100 px-0 py-3 sm:p-6 dark:bg-gray-950/60 print:rounded-none print:bg-white print:p-0">
                <div class="mx-auto max-w-[820px] overflow-hidden bg-white shadow-[0_1px_2px_rgba(16,42,67,0.08),0_16px_36px_-16px_rgba(16,42,67,0.35)] ring-1 ring-gray-900/5 sm:rounded-md print:max-w-none print:rounded-none print:shadow-none print:ring-0">
                    @include('expenses.document', $document)
                </div>
                <p class="mt-3 px-3 text-center text-xs text-gray-600 dark:text-gray-400 print:hidden">
                    Print this voucher, sign it, and file it with the receipt.
                    @can('view settings')<a href="{{ route('settings.invoice-templates.index') }}" class="link underline">Change its look</a>@endcan
                    @if (! $document['logo'] && $document['s']['show_logo'])
                        @can('edit settings')
                            <span class="mt-1 block" data-testid="add-logo-hint">Your logo is not on it yet. <a href="{{ route('settings.company') }}" class="link underline">Add your logo</a> and it will appear on every document.</span>
                        @endcan
                    @endif
                </p>
            </section>

            <div class="space-y-6 print:hidden">
                @if ($expense->journal)
                    <details class="group rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-5 py-4 focus-ring [&::-webkit-details-marker]:hidden">
                            <span>
                                <span class="font-medium text-gray-900 dark:text-white">Accounting entries</span>
                                <span class="block text-sm text-gray-600 dark:text-gray-400">How this expense was posted to the ledger, journal {{ $expense->journal->journal_number }}</span>
                            </span>
                            <svg class="h-5 w-5 shrink-0 text-gray-500 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
                        </summary>
                        <div class="border-t border-gray-100 px-5 pb-5 pt-4 dark:border-gray-700">
                            <x-table caption="Ledger postings">
                                <x-slot name="head">
                                    <x-table.th>Account</x-table.th>
                                    <x-table.th num>Debit</x-table.th>
                                    <x-table.th num>Credit</x-table.th>
                                </x-slot>
                                @foreach ($expense->journal->entries as $entry)
                                    <tr>
                                        <td><span class="mr-2 text-xs text-gray-500 dark:text-gray-400">{{ $entry->account->account_code }}</span>{{ $entry->account->name }}</td>
                                        <td class="num {{ \App\Support\Figure::tone($entry->debit) }}">@fig($entry->debit, false)</td>
                                        <td class="num {{ \App\Support\Figure::tone($entry->credit) }}">@fig($entry->credit, false)</td>
                                    </tr>
                                @endforeach
                                <x-slot name="foot">
                                    <tr>
                                        <td>Total</td>
                                        <td class="num">@fig($expense->journal->total_debit, false)</td>
                                        <td class="num">@fig($expense->journal->total_credit, false)</td>
                                    </tr>
                                </x-slot>
                            </x-table>
                            <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">
                                Posted {{ $expense->journal->posted_at?->format('j M Y, H:i') ?? 'not yet' }}.
                                <a href="{{ route('journals.show', $expense->journal) }}" class="link">Open the journal</a>
                            </p>
                        </div>
                    </details>
                @elseif (! $expense->isPaid())
                    <div class="rounded-lg bg-white p-5 text-sm text-gray-700 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/10">
                        Nothing has been posted to the ledger yet. That happens once this expense is approved and marked as paid.
                    </div>
                @endif
            </div>
        </div>

        {{-- What to act on --}}
        <aside class="space-y-4 lg:sticky lg:top-20 print:hidden" aria-label="Approval and history">
            {{-- Where it stands --}}
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10" data-testid="expense-amount">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $state['key'] === 'paid' ? 'Paid' : 'Amount payable' }}</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums {{ $state['key'] === 'rejected' ? 'text-gray-500 line-through dark:text-gray-400' : 'text-gray-900 dark:text-white' }}">{{ $money($expense->total) }}</p>
                @if ($expense->is_billable)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $expense->customer ? 'Billable to '.$expense->customer->name.'.' : 'Billable.' }}</p>
                @endif

                {{-- The approval steps, in the order they happen --}}
                @php
                    $steps = [
                        ['label' => 'Recorded', 'done' => true],
                        ['label' => 'Submitted', 'done' => (bool) $expense->submitted_at || in_array($state['key'], ['approved', 'paid'], true)],
                        ['label' => 'Approved', 'done' => in_array($state['key'], ['approved', 'paid'], true)],
                        ['label' => 'Paid', 'done' => $state['key'] === 'paid'],
                    ];
                @endphp
                @unless ($state['key'] === 'rejected')
                    <ol class="mt-4 space-y-2" aria-label="Approval progress">
                        @foreach ($steps as $step)
                            <li class="flex items-center gap-2 text-sm">
                                @if ($step['done'])
                                    <svg class="h-4 w-4 shrink-0 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span class="text-gray-900 dark:text-gray-100">{{ $step['label'] }}</span>
                                @else
                                    <span class="h-4 w-4 shrink-0 rounded-full border border-gray-300 dark:border-gray-600" aria-hidden="true"></span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $step['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endunless

                @if ($expense->canBeSubmitted())
                    <form action="{{ route('expenses.submit', $expense) }}" method="POST" class="mt-4">
                        @csrf
                        <button type="submit" class="btn-primary w-full justify-center">Submit for approval</button>
                    </form>
                @endif
                @if ($canApprove && $expense->canBeApproved())
                    <form action="{{ route('expenses.approve', $expense) }}" method="POST" class="mt-4">
                        @csrf
                        <button type="submit" class="btn-primary w-full justify-center">Approve</button>
                    </form>
                @endif
                @if ($canApprove && $expense->canBeRejected())
                    <button type="button" data-open-modal="reject-expense" class="btn-secondary mt-2 w-full justify-center">Reject</button>
                @endif
                @if ($canApprove && $expense->canBeMarkedAsPaid())
                    <form action="{{ route('expenses.mark-paid', $expense) }}" method="POST" class="mt-2">
                        @csrf
                        <button type="submit" class="btn-primary w-full justify-center">Mark as paid</button>
                    </form>
                @endif
            </section>

            {{-- Vendor --}}
            @if ($expense->vendor)
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h2 class="section-title">Paid to</h2>
                    <p class="mt-2 text-sm">
                        <a href="{{ route('vendors.show', $expense->vendor) }}" class="link">{{ $expense->vendor->name }}</a>
                    </p>
                    @if ($expense->vendor->company_name && $expense->vendor->company_name !== $expense->vendor->name)
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $expense->vendor->company_name }}</p>
                    @endif
                    @if ($expense->vendor->email)
                        <p class="truncate text-sm"><a href="mailto:{{ $expense->vendor->email }}" class="link">{{ $expense->vendor->email }}</a></p>
                    @endif
                    @if ($expense->vendor->phone)
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $expense->vendor->phone }}</p>
                    @endif
                </section>
            @endif

            {{-- Activity --}}
            @php
                $events = [['date' => $expense->expense_date ?? $expense->created_at, 'recorded' => $expense->created_at, 'text' => 'Spent', 'who' => $expense->createdBy?->name]];
                if ($expense->submitted_at) {
                    $events[] = ['date' => $expense->submitted_at, 'text' => 'Submitted for approval'];
                }
                if ($expense->approved_at) {
                    $events[] = ['date' => $expense->approved_at, 'text' => 'Approved', 'who' => $expense->approvedByUser?->name];
                }
                if ($expense->rejected_at) {
                    $events[] = ['date' => $expense->rejected_at, 'text' => 'Rejected', 'who' => $expense->rejectedByUser?->name];
                }
                if ($expense->journal?->posted_at) {
                    $events[] = ['date' => $expense->journal->posted_at, 'text' => 'Posted to the ledger'];
                }
            @endphp
            <x-activity-timeline :events="$events" />
        </aside>
    </div>

    {{-- Reject without leaving the page --}}
    @if ($canApprove && $expense->canBeRejected())
        <x-modal name="reject-expense" title="Reject this expense" maxWidth="md">
            <form action="{{ route('expenses.reject', $expense) }}" method="POST" class="space-y-4 px-6 pb-6 pt-2">
                @csrf
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $expense->expense_number }}, {{ $money($expense->total) }}@if ($expense->vendor) to {{ $expense->vendor->name }}@endif.</p>
                <div>
                    <label for="rejection_reason" class="form-label">Why <span class="font-normal text-gray-600 dark:text-gray-400">(optional)</span></label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="3" class="form-control" placeholder="Tell whoever recorded it what to fix"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn-secondary" data-close-modal="reject-expense">Cancel</button>
                    <button type="submit" class="btn-danger">Reject expense</button>
                </div>
            </form>
        </x-modal>
    @endif

    @include('partials.invoice-print-page')
</x-app-layout>
