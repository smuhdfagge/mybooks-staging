{{--
    Withholding tax fields on the vendor and customer forms.
    $party: the vendor/customer being edited (null when creating); $side: 'vendor' or 'customer'.
--}}
@php
    $whtCategories = \App\Models\WhtCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    $payeeType = old('payee_type', $party?->payee_type ?? 'company');
@endphp
<div class="mb-8">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Withholding Tax</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <x-field name="payee_type" label="{{ $side === 'vendor' ? 'Vendor is' : 'Customer is' }}" type="select"
                help="{{ $side === 'vendor' ? 'Companies: WHT goes to the NRS. Individuals: to the state IRS.' : 'Used on WHT credit note records.' }}">
                <option value="company" @selected($payeeType === 'company')>A company</option>
                <option value="individual" @selected($payeeType === 'individual')>An individual / enterprise</option>
            </x-field>
        </div>
        <div>
            <x-field name="wht_category_id" label="Usual WHT transaction type" type="select"
                help="{{ $side === 'vendor' ? 'Suggested when paying this vendor. The TIN is the tax number above.' : 'Suggested when this customer pays you.' }}">
                <option value="">None</option>
                @foreach($whtCategories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('wht_category_id', $party?->wht_category_id) === (string) $category->id)>
                        {{ $category->name }} ({{ rtrim(rtrim(number_format((float) $category->rate_company, 2), '0'), '.') }}% / {{ rtrim(rtrim(number_format((float) $category->rate_individual, 2), '0'), '.') }}%)
                    </option>
                @endforeach
            </x-field>
        </div>
        <div class="flex items-center md:pt-6">
            <input type="hidden" name="wht_exempt" value="0">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="wht_exempt" value="1" @checked(old('wht_exempt', $party?->wht_exempt))>
                {{ $side === 'vendor' ? 'Exempt from WHT (no WHT is deducted)' : 'Does not deduct WHT' }}
            </label>
        </div>
    </div>
</div>
