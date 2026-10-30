{{-- Where a line is posted (audit MB-14). Stock items go to Inventory whatever is chosen. --}}
<select aria-label="Account" :name="'items[' + index + '][account_id]'" x-model="item.account_id"
    class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
    <option value="">Account: Miscellaneous Expense</option>
    @foreach ($lineAccounts as $id => $label)
        <option value="{{ $id }}">{{ $label }}</option>
    @endforeach
</select>
