@extends('reports.pdf.layout')

@section('content')
    <p style="text-align: center; font-size: 9px; color: #6b7280; margin-bottom: 10px;">
        Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
    </p>

    <!-- Summary -->
    <div style="text-align: center; margin-bottom: 12px; padding: 10px; background-color: {{ $netProfit >= 0 ? '#ecfdf5' : '#fef2f2' }}; border-radius: 4px; border: 1px solid {{ $netProfit >= 0 ? '#a7f3d0' : '#fecaca' }};">
        <p style="font-size: 9px; color: #6b7280; margin-bottom: 3px;">Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}</p>
        <p style="font-size: 18px; font-weight: bold; color: {{ $netProfit >= 0 ? '#2E7D32' : '#C62828' }};">
            {{ $netProfit >= 0 ? '' : '-' }}{{ number_format(abs($netProfit), 2) }}
        </p>
        @if($revenue > 0)
            <p style="font-size: 8px; color: #6b7280;">{{ number_format(($netProfit / $revenue) * 100, 1) }}% profit margin</p>
        @endif
    </div>

    <!-- Revenue Section -->
    <div class="section-title">Revenue</div>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Sales Revenue</td>
                <td class="text-right positive">{{ number_format($revenue, 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td><strong>Total Revenue</strong></td>
                <td class="text-right positive"><strong>{{ number_format($revenue, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Cost of Goods Sold Section -->
    <div class="section-title">Cost of Goods Sold</div>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Cost of Goods Sold</td>
                <td class="text-right negative">({{ number_format($costOfGoodsSold, 2) }})</td>
            </tr>
            <tr class="summary-row">
                <td><strong>Total COGS</strong></td>
                <td class="text-right negative"><strong>({{ number_format($costOfGoodsSold, 2) }})</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Gross Profit -->
    <table style="margin-bottom: 10px;">
        <tbody>
            <tr style="background-color: #D9E4EF;">
                <td><strong>Gross Profit</strong></td>
                <td class="text-right {{ $grossProfit >= 0 ? 'positive' : 'negative' }}">
                    <strong>{{ $grossProfit >= 0 ? '' : '-' }}{{ number_format(abs($grossProfit), 2) }}</strong>
                    @if($revenue > 0)
                        <span style="font-size: 8px; color: #6b7280;">({{ number_format(($grossProfit / $revenue) * 100, 1) }}% margin)</span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Operating Expenses Section -->
    <div class="section-title">Operating Expenses</div>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>General & Administrative</td>
                <td class="text-right negative">({{ number_format($operatingExpenses, 2) }})</td>
            </tr>
            <tr>
                <td>Salaries & Wages</td>
                <td class="text-right negative">({{ number_format($payroll, 2) }})</td>
            </tr>
            <tr class="summary-row">
                <td><strong>Total Operating Expenses</strong></td>
                <td class="text-right negative"><strong>({{ number_format($operatingExpenses + $payroll, 2) }})</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Net Profit/Loss Summary -->
    <div class="section-title">Profit & Loss Summary</div>
    <table>
        <tbody>
            <tr>
                <td>Revenue</td>
                <td class="text-right positive">{{ number_format($revenue, 2) }}</td>
            </tr>
            <tr>
                <td>Less: Cost of Goods Sold</td>
                <td class="text-right negative">({{ number_format($costOfGoodsSold, 2) }})</td>
            </tr>
            <tr style="background-color: #D9E4EF;">
                <td><strong>Gross Profit</strong></td>
                <td class="text-right {{ $grossProfit >= 0 ? 'positive' : 'negative' }}"><strong>{{ $grossProfit >= 0 ? '' : '-' }}{{ number_format(abs($grossProfit), 2) }}</strong></td>
            </tr>
            <tr>
                <td>Less: Operating Expenses</td>
                <td class="text-right negative">({{ number_format($operatingExpenses, 2) }})</td>
            </tr>
            <tr>
                <td>Less: Salaries & Wages</td>
                <td class="text-right negative">({{ number_format($payroll, 2) }})</td>
            </tr>
            <tr class="total-row">
                <td><strong>Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}</strong></td>
                <td class="text-right {{ $netProfit >= 0 ? 'positive' : 'negative' }}"><strong>{{ $netProfit >= 0 ? '' : '-' }}{{ number_format(abs($netProfit), 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
