@extends('reports.pdf.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 10px;">
        <p style="font-size: 9px; color: #6b7280;">
            Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
        </p>
    </div>

    <!-- Summary Cards -->
    <div style="margin-bottom: 12px; overflow: hidden;">
        <div style="float: left; width: 48%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center; border: 1px solid #e5e7eb;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Quantity Sold</p>
            <p style="font-size: 14px; font-weight: bold; color: #1f2937;">{{ number_format($totalQuantity) }}</p>
        </div>
        <div style="float: right; width: 48%; padding: 8px; background-color: #ecfdf5; border-radius: 4px; text-align: center; border: 1px solid #a7f3d0;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Sales</p>
            <p style="font-size: 14px; font-weight: bold; color: #2E7D32;">{{ number_format($totalSales, 2) }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Sales by Item Table -->
    <div class="section-title">Sales by Item</div>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th class="text-center">Qty Sold</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total Sales</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->sku ?? '-' }}</td>
                    <td class="text-center">{{ number_format($item->quantity_sold) }}</td>
                    <td class="text-right">{{ number_format($item->selling_price, 2) }}</td>
                    <td class="text-right">{{ number_format($item->total_sales, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No sales found for the selected period.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2"><strong>Totals</strong></td>
                <td class="text-center"><strong>{{ number_format($totalQuantity) }}</strong></td>
                <td></td>
                <td class="text-right"><strong>{{ number_format($totalSales, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
