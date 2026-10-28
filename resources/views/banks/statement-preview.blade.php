{{-- Bank statement import, step 2 (audit MB-08): what was read from the CSV, before anything is saved. --}}
@php
    $shown = $lines->take(200);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$bank->name.': check the statement'" description="This is what was read from the file. Nothing is saved until you import it; a line imported before is skipped.">
            <x-slot name="more">
                <x-table.menu-item :href="route('banks.reconcile', $bank)">Back to reconcile</x-table.menu-item>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-6">
        <x-report.stats :cols="4">
            <x-report.stat label="Lines" :value="number_format($lines->count())" />
            <x-report.stat label="Dates" :value="\Carbon\Carbon::parse($lines->min('date'))->format('j M Y').' – '.\Carbon\Carbon::parse($lines->max('date'))->format('j M Y')" />
            <x-report.stat label="Money in" :value="\App\Support\Figure::show($moneyIn, false)" />
            <x-report.stat label="Money out" :value="\App\Support\Figure::show($moneyOut, false)" />
        </x-report.stats>

        @if ($problems)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100">
                <p class="font-medium">{{ count($problems) }} row(s) couldn't be read and won't be imported:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach (array_slice($problems, 0, 10) as $problem)
                        <li>{{ $problem }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('banks.statement.store', $bank) }}" class="flex flex-wrap items-center gap-3">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <button type="submit" class="btn-new">Import {{ number_format($lines->count()) }} line(s)</button>
            <a href="{{ route('banks.reconcile', $bank) }}" class="btn-secondary">Cancel</a>
        </form>

        <x-table caption="Statement lines read from the file">
            <x-slot name="head">
                <x-table.th>Date</x-table.th>
                <x-table.th>Description</x-table.th>
                <x-table.th class="hidden md:table-cell">Reference</x-table.th>
                <x-table.th num>Money in</x-table.th>
                <x-table.th num>Money out</x-table.th>
                <x-table.th num class="hidden md:table-cell">Balance</x-table.th>
            </x-slot>
            @foreach ($shown as $line)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($line['date'])->format('j M Y') }}</td>
                    <td class="rpt-wrap max-w-[24rem]">{{ $line['description'] ?? '—' }}</td>
                    <td class="hidden md:table-cell {{ $line['reference'] ? '' : 'tbl-zero' }}">{{ $line['reference'] ?? '—' }}</td>
                    <td class="num {{ $line['amount'] > 0 ? '' : 'tbl-zero' }}">@if ($line['amount'] > 0)@fig($line['amount'], false)@else—@endif</td>
                    <td class="num {{ $line['amount'] < 0 ? '' : 'tbl-zero' }}">@if ($line['amount'] < 0)@fig(abs($line['amount']), false)@else—@endif</td>
                    <td class="num hidden md:table-cell {{ $line['balance'] === null ? 'tbl-zero' : '' }}">@if ($line['balance'] !== null)@fig($line['balance'], false)@else—@endif</td>
                </tr>
            @endforeach
        </x-table>
        @if ($lines->count() > $shown->count())
            <p class="text-sm text-gray-600 dark:text-gray-400">Showing the first {{ $shown->count() }} of {{ number_format($lines->count()) }} lines; all of them are imported.</p>
        @endif
    </div>
</x-app-layout>
