@extends('reports.pdf.layout')

@section('content')
    <p style="text-align: center; font-size: 9px; color: #6b7280; margin-bottom: 10px;">As of {{ \Carbon\Carbon::parse($asOf)->format('F d, Y') }}</p>

    @php
        $equity = $accountsReceivable - $accountsPayable;
    @endphp

    <!-- Assets Section -->
    <div class="section-title">Assets</div>
    <table>
        <thead>
            <tr>
                <th>Account</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding-left: 15px;">Accounts Receivable</td>
                <td class="text-right">{{ number_format($accountsReceivable, 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td><strong>Total Assets</strong></td>
                <td class="text-right"><strong>{{ number_format($accountsReceivable, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Liabilities Section -->
    <div class="section-title">Liabilities</div>
    <table>
        <thead>
            <tr>
                <th>Account</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding-left: 15px;">Accounts Payable</td>
                <td class="text-right">{{ number_format($accountsPayable, 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td><strong>Total Liabilities</strong></td>
                <td class="text-right"><strong>{{ number_format($accountsPayable, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Equity Section -->
    <div class="section-title">Equity</div>
    <table>
        <thead>
            <tr>
                <th>Account</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding-left: 15px;">Retained Earnings</td>
                <td class="text-right {{ $equity >= 0 ? 'positive' : 'negative' }}">{{ number_format($equity, 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td><strong>Total Equity</strong></td>
                <td class="text-right {{ $equity >= 0 ? 'positive' : 'negative' }}"><strong>{{ number_format($equity, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Summary -->
    <div class="section-title">Summary</div>
    <table>
        <tbody>
            <tr>
                <td>Total Assets</td>
                <td class="text-right">{{ number_format($accountsReceivable, 2) }}</td>
            </tr>
            <tr>
                <td>Total Liabilities</td>
                <td class="text-right">{{ number_format($accountsPayable, 2) }}</td>
            </tr>
            <tr>
                <td>Total Equity</td>
                <td class="text-right">{{ number_format($equity, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td><strong>Total Liabilities & Equity</strong></td>
                <td class="text-right"><strong>{{ number_format($accountsPayable + $equity, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
