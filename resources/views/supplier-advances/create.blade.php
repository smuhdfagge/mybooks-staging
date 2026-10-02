<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Pay a Supplier in Advance</h2>
            <a href="{{ route('supplier-advances.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400">Back</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            <x-card class="p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Use this when you pay a supplier before they send their bill. The money is kept as an advance (an asset) until you use it against their bill.</p>
                <form method="POST" action="{{ route('supplier-advances.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-searchable-select name="vendor_id" id="vendor_id" label="Supplier *" :options="$vendors->all()" :value="old('vendor_id', $vendorId)" :has-error="$errors->has('vendor_id')" placeholder="Choose a supplier" />
                        @error('vendor_id')<p id="vendor_id-error" class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><x-field name="payment_date" label="Date paid" type="date" :value="old('payment_date', now()->toDateString())" required /></div>
                        <div><x-field name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="old('amount')" required /></div>
                        <div>
                            <x-field name="payment_method" label="Paid by" type="select" required>
                                @foreach(['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'cheque' => 'Cheque', 'debit_card' => 'Debit card', 'mobile_money' => 'Mobile money', 'other' => 'Other'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-field>
                        </div>
                        <div>
                            <x-field name="bank_id" label="From bank account" type="select">
                                <option value="">—</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}" @selected((string) old('bank_id') === (string) $bank->id)>{{ $bank->name }}</option>
                                @endforeach
                            </x-field>
                        </div>
                    </div>
                    {{-- WHT is due when the money is paid, advances included. --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-field name="wht_category_id" label="WHT transaction type (if you deduct WHT)" type="select">
                                <option value="">No WHT</option>
                                @foreach($whtCategories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('wht_category_id') === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </x-field>
                        </div>
                        <div><x-field name="wht_amount" label="WHT withheld" type="number" step="0.01" min="0" :value="old('wht_amount')" help="Leave empty to work it out. Amount above is what you pay; the supplier's credit is the amount plus the WHT." /></div>
                    </div>
                    <x-field name="reference" label="Reference" :value="old('reference')" />
                    <x-field name="notes" label="Notes" type="textarea" rows="2" :value="old('notes')" />
                    <div class="flex justify-end"><button class="btn-primary">Record advance</button></div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
