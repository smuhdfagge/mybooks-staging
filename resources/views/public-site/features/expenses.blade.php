{{--
    The expenses feature page (SEO plan, build order 8). Everything here
    is something MyBooks does: the approval steps come from the Expense
    model's statuses, and the voucher from App\Support\ExpenseDocument.
--}}
@php
    $steps = [
        ['Record it', 'What was spent, who it was paid to, and which account it belongs under. Add the VAT if there was any.'],
        ['Send it for approval', 'It goes to whoever approves spending, rather than sitting in somebody\'s notebook until month end.'],
        ['Approve or send it back', 'An approver sees the figure and the reason, and can reject it with a note saying what to fix.'],
        ['Mark it paid', 'Only then is it posted to the ledger, out of the account it was really paid from.'],
    ];
@endphp

<x-public.feature-page
    :page="$page"
    heading="Know where the money went"
    lead="Record what you spend, route it for approval before it is paid, and see each month's spending by category. Every expense feeds your reports and your VAT return without being entered twice.">

    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6" aria-label="How it works">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Money does not leave until somebody approves it</h2>
        <p class="mt-3 max-w-2xl text-gray-700 dark:text-gray-300">
            The point of recording an expense is not bookkeeping. It is that the person who spends is not the person who approves.
        </p>
        <ol class="mt-8 space-y-6">
            @foreach ($steps as $i => [$when, $what])
                <li class="flex gap-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $when }}</h3>
                        <p class="mt-1 text-gray-700 dark:text-gray-300">{{ $what }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40" aria-label="The payment voucher">
        <div class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">A payment voucher you can file</h2>
            <p class="mt-3 max-w-2xl text-gray-700 dark:text-gray-300">
                Every expense prints as a proper payment voucher, with your logo, the amount in figures and in words, and
                signature lines for who prepared it, who approved it and who received the money. Print it, sign it, and file
                it with the receipt.
            </p>
            <p class="mt-3 max-w-2xl text-gray-700 dark:text-gray-300">
                The amount in words matters: a voucher is signed against it, so the figure cannot be altered afterwards
                without the two disagreeing.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6" aria-label="What else expenses do">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">What else it handles</h2>
        <ul class="mt-6 grid gap-x-8 gap-y-3 text-gray-700 sm:grid-cols-2 dark:text-gray-300">
            @foreach ([
                'Spending grouped by category, so you can see where it goes',
                'The account it was paid from, so your bank balance stays right',
                'VAT on an expense carried into your monthly return',
                'Expenses marked billable to a customer',
                'Recurring expenses like rent and subscriptions, raised for you',
                'Every approval and rejection kept, with who and when',
            ] as $line)
                <li class="flex gap-2">
                    <svg class="mt-1 h-4 w-4 shrink-0 text-brand-600 dark:text-brand-300" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2"/></svg>
                    <span>{{ $line }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <x-slot:related>
        <li><a href="{{ route('features.show', 'reports') }}" class="font-medium text-brand-600 underline dark:text-brand-300">Reports</a> — where this spending shows up</li>
        <li><a href="{{ route('guides.show', 'withholding-tax-nigeria') }}" class="font-medium text-brand-600 underline dark:text-brand-300">Withholding tax in Nigeria</a> — what to deduct when you pay a supplier</li>
        <li><a href="{{ route('guides.show', 'vat-returns-nigeria') }}" class="font-medium text-brand-600 underline dark:text-brand-300">How to file VAT returns in Nigeria</a> — claiming back the VAT you paid</li>
    </x-slot:related>
</x-public.feature-page>
