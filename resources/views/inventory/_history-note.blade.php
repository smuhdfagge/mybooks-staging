{{-- A stock history row's note; a transfer's or assembly order's note links to it (sessions 13, 14). --}}
@if($record->reference_type === 'stock_transfer' && $record->reference_id && \App\Models\StockTransfer::moduleOn() && auth()->user()?->can('adjust inventory'))
    <a href="{{ route('stock-transfers.show', $record->reference_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $record->notes ?? 'Stock transfer' }}</a>
@elseif($record->reference_type === 'assembly_order' && $record->reference_id && \App\Models\AssemblyOrder::moduleOn() && auth()->user()?->can('adjust inventory'))
    <a href="{{ route('assembly-orders.show', $record->reference_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $record->notes ?? 'Assembly' }}</a>
@else
    {{ $record->notes ?? '-' }}
@endif
