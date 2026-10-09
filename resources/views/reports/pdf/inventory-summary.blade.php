@extends('reports.pdf.layout')

@section('content')
    <!-- Summary Cards -->
    <div style="margin-bottom: 12px; overflow: hidden;">
        <div style="float: left; width: 31%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center; border: 1px solid #e5e7eb;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Items</p>
            <p style="font-size: 14px; font-weight: bold; color: #1f2937;">{{ number_format($totalItems) }}</p>
        </div>
        <div style="float: left; width: 31%; margin-left: 3%; padding: 8px; background-color: #ecfdf5; border-radius: 4px; text-align: center; border: 1px solid #a7f3d0;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Stock Value</p>
            <p style="font-size: 14px; font-weight: bold; color: #2E7D32;">{{ number_format($totalValue, 2) }}</p>
        </div>
        <div style="float: right; width: 31%; padding: 8px; background-color: {{ $lowStockItems > 0 ? '#fef2f2' : '#f3f4f6' }}; border-radius: 4px; text-align: center; border: 1px solid {{ $lowStockItems > 0 ? '#fecaca' : '#e5e7eb' }};">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Low Stock Items</p>
            <p style="font-size: 14px; font-weight: bold; color: {{ $lowStockItems > 0 ? '#C62828' : '#1f2937' }};">{{ number_format($lowStockItems) }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Inventory Table -->
    <div class="section-title">Inventory Details</div>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th class="text-center">Stock Qty</th>
                <th class="text-center">Reorder Level</th>
                <th class="text-right">Cost Price</th>
                <th class="text-right">Stock Value</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->sku ?? '-' }}</td>
                    <td class="text-center">{{ number_format($item->stock_quantity) }}</td>
                    <td class="text-center">{{ number_format($item->reorder_level) }}</td>
                    <td class="text-right">{{ number_format($item->cost_price, 2) }}</td>
                    <td class="text-right">{{ number_format($item->stock_value, 2) }}</td>
                    <td class="text-center">
                        @if($item->is_low_stock)
                            <span style="color: #C62828; font-weight: bold;">Low Stock</span>
                        @else
                            <span style="color: #2E7D32;">OK</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No inventory items found.</td>
                </tr>
            @endforelse
            @if($inTransit ?? null)
                <tr>
                    <td colspan="3">Goods in transit between warehouses (cost when sent: {{ number_format($inTransit['cost'], 2) }})</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($inTransit['quantity'], 4), '0'), '.') }}</td>
                    <td></td>
                    <td class="text-right">{{ number_format($inTransit['value'], 2) }}</td>
                    <td></td>
                </tr>
            @endif
            <tr class="total-row">
                <td colspan="5"><strong>Total Stock Value</strong></td>
                <td class="text-right"><strong>{{ number_format($totalValue, 2) }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endsection
