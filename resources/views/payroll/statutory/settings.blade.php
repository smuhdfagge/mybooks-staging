<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Statutory Settings') }}</h2>
            <div class="flex gap-2">
                @can('view statutory-remittances')
                    <a href="{{ route('payroll.statutory.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">Remittances</a>
                @endcan
                <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">&larr; Back to Payroll</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="POST" action="{{ route('payroll.statutory.settings.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-card class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">How payroll uses these</h3>
                    <label class="flex items-start gap-3">
                        <input type="hidden" name="auto" value="0">
                        <input type="checkbox" name="auto" value="1" class="mt-1 rounded border-gray-300 dark:border-gray-600" @checked(old('auto', $settings['auto']))>
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Work out pension, NHF, NSITF and ITF on every payslip from the rates below.</strong><br>
                            Employee pension and NHF come off pay before PAYE (reliefs under the Nigeria Tax Act 2025). Pension or NHF deductions in a salary structure are then ignored, so nothing is taken twice. Payslips already made are not changed.
                        </span>
                    </label>

                    <div class="mt-4 max-w-xl">
                        <x-field name="pensionable_components" label="Allowances counted as pensionable pay (with basic salary)"
                                 :value="old('pensionable_components', implode(', ', $settings['pensionable_components']))"
                                 help="Comma separated; matched by name, so 'Housing' matches 'Housing Allowance'. Pension Reform Act 2014: basic, housing and transport." />
                        @if($allowanceNames->isNotEmpty())
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Your allowances: {{ $allowanceNames->implode(', ') }}</p>
                        @endif
                    </div>
                </x-card>

                <x-card class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Rates and due dates</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Defaults follow the law as commonly applied. Check them with your accountant; change any that differ for your business.</p>

                    <div class="space-y-4">
                        @foreach($contributions as $c)
                            <fieldset class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <legend class="px-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $c->name }}</legend>
                                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                                    <div class="md:col-span-2">
                                        <x-field name="contributions[{{ $c->code }}][name]" label="Name" :value="old('contributions.'.$c->code.'.name', $c->name)" required />
                                    </div>
                                    @if($c->code === 'paye')
                                        <div class="md:col-span-2 text-sm text-gray-600 dark:text-gray-400 pt-6">
                                            Rate: the PAYE bands on <a class="text-indigo-600 underline" href="{{ route('payroll.tax-templates') }}">Tax templates</a>.
                                        </div>
                                    @else
                                        <div>
                                            <x-field name="contributions[{{ $c->code }}][rate]" label="Rate %" type="number" step="0.0001" min="0" max="100" :value="old('contributions.'.$c->code.'.rate', $c->rate !== null ? rtrim(rtrim((string) $c->rate, '0'), '.') : '')" />
                                        </div>
                                        <div>
                                            <x-field name="contributions[{{ $c->code }}][base]" label="Of" type="select">
                                                @foreach($bases as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('contributions.'.$c->code.'.base', $c->base) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </x-field>
                                        </div>
                                    @endif
                                    <div class="md:col-span-2">
                                        <span class="form-label">In use</span>
                                        <label class="inline-flex items-center gap-2 mt-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="hidden" name="contributions[{{ $c->code }}][is_enabled]" value="0">
                                            <input type="checkbox" name="contributions[{{ $c->code }}][is_enabled]" value="1" class="rounded border-gray-300 dark:border-gray-600" @checked(old('contributions.'.$c->code.'.is_enabled', $c->is_enabled))>
                                            {{ $c->code === 'itf' ? 'We have 5+ staff or turnover of N50m+' : 'Applies to this business' }}
                                        </label>
                                    </div>
                                    <div class="md:col-span-2">
                                        <x-field name="contributions[{{ $c->code }}][due_rule]" label="Due" type="select">
                                            @foreach(\App\Models\StatutoryContribution::DUE_RULES as $value => $label)
                                                <option value="{{ $value }}" @selected(old('contributions.'.$c->code.'.due_rule', $c->due_rule) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </x-field>
                                    </div>
                                    <div>
                                        <x-field name="contributions[{{ $c->code }}][due_value]" label="Day / days / month" type="number" min="1" max="60" :value="old('contributions.'.$c->code.'.due_value', $c->due_value)" />
                                    </div>
                                    <div>
                                        <x-field name="contributions[{{ $c->code }}][effective_from]" label="Effective from" type="date" :value="old('contributions.'.$c->code.'.effective_from', $c->effective_from?->toDateString())" />
                                    </div>
                                    <div class="md:col-span-2 text-sm text-gray-600 dark:text-gray-400 pt-6">Now: due {{ $c->dueRuleLabel() }}</div>
                                    <div class="md:col-span-6">
                                        <x-field name="contributions[{{ $c->code }}][source]" label="Source / notes" type="textarea" rows="2" :value="old('contributions.'.$c->code.'.source', $c->source)" />
                                    </div>
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                </x-card>

                <div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Save settings</button>
                </div>
            </form>

            <x-card class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Pension Fund Administrators</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ $sharedPfas->count() }} PFAs licensed by PenCom are listed for everyone. Add one here if yours is missing (for example after a merger).</p>

                @if($ownPfas->isNotEmpty())
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Code</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                    <th class="px-4 py-2"><span class="sr-only">Save</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($ownPfas as $pfa)
                                    <tr>
                                        <td colspan="4" class="px-4 py-2">
                                            <form method="POST" action="{{ route('payroll.statutory.pfas.update', $pfa) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-center">
                                                @csrf
                                                @method('PATCH')
                                                <input type="text" name="name" value="{{ $pfa->name }}" aria-label="Name" class="form-control" required maxlength="255">
                                                <input type="text" name="code" value="{{ $pfa->code }}" aria-label="Code" class="form-control" maxlength="30">
                                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 dark:border-gray-600" @checked($pfa->is_active)> Active
                                                </label>
                                                <button type="submit" class="text-sm text-indigo-600 hover:underline justify-self-start">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <form method="POST" action="{{ route('payroll.statutory.pfas.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    @csrf
                    <div><x-field name="name" label="New PFA name" required maxlength="255" /></div>
                    <div><x-field name="code" label="PenCom code (optional)" maxlength="30" /></div>
                    <div><button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-800">Add PFA</button></div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
