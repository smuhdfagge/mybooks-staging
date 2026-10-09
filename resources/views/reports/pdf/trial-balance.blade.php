@extends('reports.pdf.layout')

@section('content')
    <p style="text-align: center; font-size: 9px; color: #6b7280; margin-bottom: 10px;">As of {{ \Carbon\Carbon::parse($asOf)->format('F d, Y') }}</p>

    @php
        $difference = $totalDebits - $totalCredits;
    @endphp

    <!-- Balance Status -->
    <div style="text-align: center; margin-bottom: 10px; padding: 8px; background-color: {{ abs($difference) <= 0.01 ? '#ecfdf5' : '#fef2f2' }}; border-radius: 4px; border: 1px solid {{ abs($difference) <= 0.01 ? '#a7f3d0' : '#fecaca' }};">
        @if(abs($difference) <= 0.01)
            <p style="font-size: 9px; color: #2E7D32; font-weight: bold;">✓ Trial Balance is Balanced</p>
        @else
            <p style="font-size: 9px; color: #C62828; font-weight: bold;">✗ Out of Balance by {{ number_format(abs($difference), 2) }}</p>
        @endif
    </div>

    <!-- Summary Cards -->
    <div style="margin-bottom: 12px; overflow: hidden;">
        <div style="float: left; width: 48%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Debits</p>
            <p style="font-size: 14px; font-weight: bold; color: #1f2937;">{{ number_format($totalDebits, 2) }}</p>
        </div>
        <div style="float: right; width: 48%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Credits</p>
            <p style="font-size: 14px; font-weight: bold; color: #1f2937;">{{ number_format($totalCredits, 2) }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Trial Balance Table -->
    <div class="section-title">Account Details</div>
    <table>
        <thead>
            <tr>
                <th>Account Code</th>
                <th>Account Name</th>
                <th>Type</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
            </tr>
        </thead>
        <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td>{{ $account->account_code }}</td>
                    <td>{{ $account->name }}</td>
                    <td>{{ ucfirst($account->type) }}</td>
                    <td class="text-right">{{ $account->total_debit > 0 ? number_format($account->total_debit, 2) : '' }}</td>
                    <td class="text-right">{{ $account->total_credit > 0 ? number_format($account->total_credit, 2) : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No accounts with balances found.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3"><strong>Totals</strong></td>
                <td class="text-right"><strong>{{ number_format($totalDebits, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalCredits, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
