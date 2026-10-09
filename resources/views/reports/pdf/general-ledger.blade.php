@extends('reports.pdf.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 10px;">
        <p style="font-size: 9px; color: #6b7280;">
            Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
        </p>
        @if($selectedAccount)
            <p style="font-size: 10px; font-weight: bold; color: #1f2937;">Account: {{ $selectedAccount->account_code }} - {{ $selectedAccount->name }}</p>
        @endif
    </div>

    @if($selectedAccount)
        <!-- Ledger Entries Table -->
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Journal #</th>
                    <th>Description</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $runningDebit = 0;
                    $runningCredit = 0;
                @endphp
                @forelse($entries as $entry)
                    @php
                        $runningDebit += $entry->debit;
                        $runningCredit += $entry->credit;
                    @endphp
                    <tr>
                        <td>{{ $entry->journal->journal_date?->format('Y-m-d') }}</td>
                        <td>{{ $entry->journal->journal_number }}</td>
                        <td>{{ $entry->description ?? $entry->journal->description }}</td>
                        <td class="text-right">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '' }}</td>
                        <td class="text-right">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No entries found for the selected period.</td>
                    </tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="3"><strong>Totals</strong></td>
                    <td class="text-right"><strong>{{ number_format($runningDebit, 2) }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($runningCredit, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <!-- Summary -->
        <div style="margin-top: 12px; padding: 8px; background-color: #f3f4f6; border-radius: 4px; border-left: 3px solid #1F4E79;">
            <p style="font-size: 9px; color: #6b7280;">
                <strong>Net Balance:</strong> 
                @php $netBalance = $runningDebit - $runningCredit; @endphp
                <span style="color: {{ $netBalance >= 0 ? '#2E7D32' : '#C62828' }};">
                    {{ number_format(abs($netBalance), 2) }} {{ $netBalance >= 0 ? 'DR' : 'CR' }}
                </span>
            </p>
        </div>
    @else
        <div style="text-align: center; padding: 30px;">
            <p style="color: #6b7280;">Please select an account to view ledger entries.</p>
        </div>
    @endif
@endsection
