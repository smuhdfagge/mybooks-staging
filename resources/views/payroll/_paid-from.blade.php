{{-- The bank net pay leaves (audit MB-08): it posts to that bank's ledger account. --}}
@if ($banks->isNotEmpty())
    @php $selected = old('bank_id', $selected ?? $banks->firstWhere('is_primary', true)?->id); @endphp
    <label for="paid-from" class="sr-only">Paid from</label>
    <select id="paid-from" name="bank_id" class="h-9 rounded-md border-gray-300 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" title="Paid from">
        <option value="">Paid from: default account</option>
        @foreach ($banks as $bank)
            <option value="{{ $bank->id }}" @selected((string) $selected === (string) $bank->id)>Paid from {{ $bank->name }}</option>
        @endforeach
    </select>
@endif
