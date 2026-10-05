{{-- A stock history row's note; a transfer's note links to the transfer (session 13). --}}
@if($record->reference_type === 'stock_transfer' && $record->reference_id && \App\Models\StockTransfer::moduleOn() && auth()->user()?->can('adjust inventory'))
    <a href="{{ route('stock-transfers.show', $record->reference_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $record->notes ?? 'Stock transfer' }}</a>
@else
    {{ $record->notes ?? '-' }}
@endif
