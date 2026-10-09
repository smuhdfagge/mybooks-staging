@extends('reports.pdf.layout')

@section('content')
    <p style="text-align: center; font-size: 9px; color: #6b7280; margin-bottom: 10px;">As of {{ \Carbon\Carbon::parse($asOf)->format('F d, Y') }}</p>

    <!-- Aging Summary -->
    <div class="section-title">Aging Summary</div>
    <table>
        <thead>
            <tr>
                <th>Current</th>
                <th>1-30 Days</th>
                <th>31-60 Days</th>
                <th>61-90 Days</th>
                <th>91-120 Days</th>
                <th>Over 120 Days</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr class="summary-row">
                <td class="text-right">{{ number_format($current, 2) }}</td>
                <td class="text-right">{{ number_format($days30, 2) }}</td>
                <td class="text-right">{{ number_format($days60, 2) }}</td>
                <td class="text-right">{{ number_format($days90, 2) }}</td>
                <td class="text-right">{{ number_format($days120, 2) }}</td>
                <td class="text-right negative">{{ number_format($over120, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($totalPayable, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Bill Details -->
    <div class="section-title">Outstanding Bills</div>
    <table>
        <thead>
            <tr>
                <th>Bill #</th>
                <th>Vendor</th>
                <th>Bill Date</th>
                <th>Due Date</th>
                <th class="text-right">Total</th>
                <th class="text-right">Balance Due</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bills as $bill)
                <tr>
                    <td>{{ $bill->bill_number }}</td>
                    <td>{{ $bill->vendor->name ?? 'N/A' }}</td>
                    <td>{{ $bill->bill_date?->format('Y-m-d') }}</td>
                    <td>{{ $bill->due_date?->format('Y-m-d') }}</td>
                    <td class="text-right">{{ number_format($bill->total, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($bill->balance_due, 2) }}</td>
                    <td>
                        <span style="color: {{ $bill->status === 'overdue' ? '#C62828' : '#6b7280' }};">
                            {{ ucfirst($bill->status) }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No outstanding bills found.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="4"><strong>Total Outstanding</strong></td>
                <td class="text-right"><strong>{{ number_format($bills->sum('total'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalPayable, 2) }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endsection
