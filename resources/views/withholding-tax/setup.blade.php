{{-- Withholding tax rates and settings (tables plan T7). --}}
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Withholding tax" description="WHT transaction types, rates and settings for this business." />
    </x-slot>

    <div>
        <div class="space-y-6">
            @include('withholding-tax._tabs')

            @php $canManage = auth()->user()->can('manage withholding-tax'); @endphp

            <x-card title="WHT rates">
                <div class="px-6 pt-2 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <p>Rates are percentages for resident payees, worked out on the amount before VAT. A payee without a TIN is charged twice the rate where "Double without TIN" is ticked.</p>
                    <p>The seeded rates follow the Deduction of Tax at Source (Withholding) Regulations 2024 (in force 1 January 2025), which the Nigeria Tax Administration Act 2025 keeps in place. Please confirm them with your accountant; you can change any of them.</p>
                </div>

                <form method="POST" action="{{ route('withholding-tax.rates.update') }}" class="p-6">
                    @csrf
                    @method('PUT')
                    <x-table caption="WHT rates">
<x-slot name="head">
                                    <x-table.th>Transaction type</x-table.th>
                                    <x-table.th num>Companies %</x-table.th>
                                    <x-table.th num>Individuals %</x-table.th>
                                    <x-table.th class="text-center">Double without TIN</x-table.th>
                                    <x-table.th>Effective from</x-table.th>
                                    <x-table.th>Source</x-table.th>
                                    <x-table.th class="text-center">Active</x-table.th>
</x-slot>

                                @foreach($categories as $category)
                                    <tr>
                                        <td>
                                            <label class="sr-only" for="rate-name-{{ $category->id }}">Transaction type</label>
                                            <input id="rate-name-{{ $category->id }}" name="rates[{{ $category->id }}][name]" value="{{ old("rates.{$category->id}.name", $category->name) }}" class="form-control min-w-[14rem]" @disabled(! $canManage) required>
                                        </td>
                                        <td>
                                            <label class="sr-only" for="rate-co-{{ $category->id }}">Rate for companies</label>
                                            <input id="rate-co-{{ $category->id }}" type="number" step="0.01" min="0" max="100" name="rates[{{ $category->id }}][rate_company]" value="{{ old("rates.{$category->id}.rate_company", $category->rate_company) }}" class="form-control w-24 text-right" @disabled(! $canManage) required>
                                        </td>
                                        <td>
                                            <label class="sr-only" for="rate-ind-{{ $category->id }}">Rate for individuals</label>
                                            <input id="rate-ind-{{ $category->id }}" type="number" step="0.01" min="0" max="100" name="rates[{{ $category->id }}][rate_individual]" value="{{ old("rates.{$category->id}.rate_individual", $category->rate_individual) }}" class="form-control w-24 text-right" @disabled(! $canManage) required>
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden" name="rates[{{ $category->id }}][double_without_tin]" value="0">
                                            <input type="checkbox" name="rates[{{ $category->id }}][double_without_tin]" value="1" aria-label="Double without TIN" @checked($category->double_without_tin) @disabled(! $canManage)>
                                        </td>
                                        <td>
                                            <label class="sr-only" for="rate-eff-{{ $category->id }}">Effective from</label>
                                            <input id="rate-eff-{{ $category->id }}" type="date" name="rates[{{ $category->id }}][effective_from]" value="{{ $category->effective_from?->format('Y-m-d') }}" class="form-control" @disabled(! $canManage)>
                                        </td>
                                        <td>
                                            <label class="sr-only" for="rate-src-{{ $category->id }}">Source</label>
                                            <input id="rate-src-{{ $category->id }}" name="rates[{{ $category->id }}][source]" value="{{ $category->source }}" title="{{ $category->source }}" class="form-control min-w-[12rem]" @disabled(! $canManage)>
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden" name="rates[{{ $category->id }}][is_active]" value="0">
                                            <input type="checkbox" name="rates[{{ $category->id }}][is_active]" value="1" aria-label="Active" @checked($category->is_active) @disabled(! $canManage)>
                                        </td>
                                    </tr>
                                @endforeach
</x-table>
                    @if($canManage)
                        <div class="mt-4 flex justify-end">
                            <button type="submit" class="btn-primary">Save rates</button>
                        </div>
                    @endif
                </form>
            </x-card>

            @if($canManage)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <x-card title="Add a transaction type">
                        <form method="POST" action="{{ route('withholding-tax.rates.store') }}" class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @csrf
                            <div class="sm:col-span-2">
                                <x-field name="name" label="Transaction type" :value="old('name')" required />
                            </div>
                            <div>
                                <x-field name="rate_company" label="Rate for companies (%)" type="number" step="0.01" min="0" max="100" :value="old('rate_company')" required />
                            </div>
                            <div>
                                <x-field name="rate_individual" label="Rate for individuals (%)" type="number" step="0.01" min="0" max="100" :value="old('rate_individual')" required />
                            </div>
                            <div>
                                <x-field name="effective_from" label="Effective from" type="date" :value="old('effective_from')" />
                            </div>
                            <div>
                                <x-field name="source" label="Source" :value="old('source')" />
                            </div>
                            <div class="sm:col-span-2">
                                <input type="hidden" name="double_without_tin" value="0">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="double_without_tin" value="1" @checked(old('double_without_tin', true))>
                                    Double the rate when the payee has no TIN
                                </label>
                            </div>
                            <div class="sm:col-span-2 flex justify-end">
                                <button type="submit" class="btn-primary">Add</button>
                            </div>
                        </form>
                    </x-card>

                    <x-card title="Settings">
                        <form method="POST" action="{{ route('withholding-tax.settings.update') }}" class="p-6 space-y-4">
                            @csrf
                            @method('PUT')
                            <div>
                                <x-field name="business_type" label="This business is" type="select" help="Sets the rate customers deduct from your invoices.">
                                    <option value="company" @selected($settings['business_type'] === 'company')>A company</option>
                                    <option value="individual" @selected($settings['business_type'] === 'individual')>An individual / enterprise</option>
                                </x-field>
                            </div>
                            <div>
                                <input type="hidden" name="small_company" value="0">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="small_company" value="1" @checked($settings['small_company'])>
                                    Small company exemption: don't suggest WHT on payments to a vendor with a TIN whose payments this month stay within the limit below
                                </label>
                            </div>
                            <div>
                                <x-field name="small_company_threshold" label="Monthly limit per vendor" type="number" step="0.01" min="0" :value="$settings['small_company_threshold']" required />
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary">Save settings</button>
                            </div>
                        </form>
                    </x-card>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
