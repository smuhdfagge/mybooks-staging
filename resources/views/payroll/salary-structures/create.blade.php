<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Create Salary Structure') }}
            </h2>
            <a href="{{ route('salary-structures.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('salary-structures.store') }}" method="POST" class="p-6" x-data="salaryStructureForm()">
                    @csrf

                    <!-- Employee & Basic Info -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Employee & Basic Salary
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <x-field name="name" label="Structure Name" :value="old('name')" required placeholder="e.g. Senior Developer, Manager Level 1" />
                            </div>

                            <div>
                                <x-field name="basic_salary" label="Basic Salary" type="number" :value="old('basic_salary')" required step="0.01" min="0" x-model="basicSalary" />
                            </div>

                            <div>
                                <x-field name="effective_from" label="Effective From" type="date" :value="old('effective_from', now()->format('Y-m-d'))" required />
                            </div>

                            <div>
                                <x-field name="effective_to" label="Effective To" type="date" :value="old('effective_to')" />
                            </div>
                        </div>
                    </div>

                    <!-- Allowances -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Allowances
                        </h3>

                        <template x-for="(allowance, index) in allowances" :key="index">
                            <div class="grid grid-cols-12 gap-3 mb-3 items-end">
                                <div class="col-span-4">
                                    <label x-show="index === 0" class="form-label">Allowance</label>
                                    <select aria-label="Name" :name="'allowances['+index+'][name]'" x-model="allowance.name"
                                        @change="onAllowanceSelected(index)"
                                        class="form-control text-sm">
                                        <option value="">-- Select Allowance --</option>
                                        <template x-for="tpl in allowanceTemplates" :key="tpl.id">
                                            <option :value="tpl.name" x-text="tpl.name" :selected="allowance.name === tpl.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <label x-show="index === 0" class="form-label">Type</label>
                                    <select aria-label="Amount type" :name="'allowances['+index+'][amount_type]'" x-model="allowance.amount_type"
                                        class="form-control text-sm">
                                        <option value="fixed">Fixed Amount</option>
                                        <option value="percentage">% of Basic</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label x-show="index === 0" class="form-label">Amount</label>
                                    <input aria-label="Amount" type="number" :name="'allowances['+index+'][amount]'" x-model="allowance.amount" step="0.01" min="0"
                                        class="form-control text-sm">
                                </div>
                                <div class="col-span-2">
                                    <label x-show="index === 0" class="form-label">Taxable</label>
                                    <select aria-label="Is taxable" :name="'allowances['+index+'][is_taxable]'" x-model="allowance.is_taxable"
                                        class="form-control text-sm">
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <button type="button" @click="removeAllowance(index)" class="p-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <button type="button" @click="addAllowance()" class="inline-flex items-center px-3 py-2 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-300 dark:border-green-700 rounded-md text-sm hover:bg-green-100 dark:hover:bg-green-900/40">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Allowance
                        </button>
                    </div>

                    <!-- Deductions -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                            </svg>
                            Deductions
                        </h3>

                        <template x-for="(deduction, index) in deductions" :key="index">
                            <div class="grid grid-cols-12 gap-3 mb-3 items-end">
                                <div class="col-span-4">
                                    <label x-show="index === 0" class="form-label">Deduction</label>
                                    <select aria-label="Name" :name="'deductions['+index+'][name]'" x-model="deduction.name"
                                        @change="onDeductionSelected(index)"
                                        class="form-control text-sm">
                                        <option value="">-- Select Deduction --</option>
                                        <template x-for="tpl in deductionTemplates" :key="tpl.id">
                                            <option :value="tpl.name" x-text="tpl.name" :selected="deduction.name === tpl.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <label x-show="index === 0" class="form-label">Type</label>
                                    <select aria-label="Amount type" :name="'deductions['+index+'][amount_type]'" x-model="deduction.amount_type"
                                        class="form-control text-sm">
                                        <option value="fixed">Fixed Amount</option>
                                        <option value="percentage">% of Gross</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label x-show="index === 0" class="form-label">Amount</label>
                                    <input aria-label="Amount" type="number" :name="'deductions['+index+'][amount]'" x-model="deduction.amount" step="0.01" min="0"
                                        class="form-control text-sm">
                                </div>
                                <div class="col-span-2">
                                    <label x-show="index === 0" class="form-label" title="Pre-tax deductions (pension, NHF, health insurance) are taken off pay before PAYE is worked out">Pre-tax</label>
                                    <select aria-label="Is taxable" :name="'deductions['+index+'][is_taxable]'" x-model="deduction.is_taxable"
                                        class="form-control text-sm">
                                        <option value="0">No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <button type="button" @click="removeDeduction(index)" class="p-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <button type="button" @click="addDeduction()" class="inline-flex items-center px-3 py-2 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-300 dark:border-red-700 rounded-md text-sm hover:bg-red-100 dark:hover:bg-red-900/40">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Deduction
                        </button>
                    </div>

                    <!-- Summary -->
                    <div class="mb-8 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Salary Summary</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Basic Salary</span>
                                <p class="font-semibold text-gray-900 dark:text-gray-100" x-text="formatCurrency(parseFloat(basicSalary) || 0)"></p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Total Allowances</span>
                                <p class="font-semibold text-green-600 dark:text-green-400" x-text="'+' + formatCurrency(totalAllowances())"></p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Gross Salary</span>
                                <p class="font-semibold text-gray-900 dark:text-gray-100" x-text="formatCurrency(grossSalary())"></p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Total Deductions</span>
                                <p class="font-semibold text-red-600 dark:text-red-400" x-text="'-' + formatCurrency(totalDeductions())"></p>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                            <span class="text-gray-500 dark:text-gray-400">Estimated Net Salary</span>
                            <p class="text-xl font-bold text-gray-900 dark:text-gray-100" x-text="formatCurrency(netSalary())"></p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-8">
                        <x-field name="notes" label="Notes" type="textarea" :value="old('notes')" rows="3" />
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('salary-structures.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Save Salary Structure
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function salaryStructureForm() {
            @php
                $defaultAllowances = [['name' => '', 'amount_type' => 'fixed', 'amount' => '', 'is_taxable' => '1']];
                $defaultDeductions = [['name' => '', 'amount_type' => 'fixed', 'amount' => '', 'is_taxable' => '0']];
            @endphp
            return {
                basicSalary: @js((string) old('basic_salary', 0)),
                allowances: @js(old("allowances", $defaultAllowances)),
                deductions: @js(old("deductions", $defaultDeductions)),
                allowanceTemplates: @js($allowanceTemplates->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'amount_type' => $a->amount_type, 'amount' => $a->amount, 'is_taxable' => (string)(int)$a->is_taxable])->values()),
                deductionTemplates: @js($deductionTemplates->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'amount_type' => $d->amount_type, 'amount' => $d->amount, 'is_taxable' => (string)(int)$d->is_taxable])->values()),

                onAllowanceSelected(index) {
                    let tpl = this.allowanceTemplates.find(t => t.name === this.allowances[index].name);
                    if (tpl) {
                        this.allowances[index].amount_type = tpl.amount_type;
                        this.allowances[index].amount = tpl.amount;
                        this.allowances[index].is_taxable = tpl.is_taxable;
                    }
                },
                onDeductionSelected(index) {
                    let tpl = this.deductionTemplates.find(t => t.name === this.deductions[index].name);
                    if (tpl) {
                        this.deductions[index].amount_type = tpl.amount_type;
                        this.deductions[index].amount = tpl.amount;
                        this.deductions[index].is_taxable = tpl.is_taxable;
                    }
                },
                addAllowance() {
                    this.allowances.push({ name: '', amount_type: 'fixed', amount: '', is_taxable: '1' });
                },
                removeAllowance(index) {
                    if (this.allowances.length > 1) this.allowances.splice(index, 1);
                },
                addDeduction() {
                    this.deductions.push({ name: '', amount_type: 'fixed', amount: '', is_taxable: '0' });
                },
                removeDeduction(index) {
                    if (this.deductions.length > 1) this.deductions.splice(index, 1);
                },
                totalAllowances() {
                    let base = parseFloat(this.basicSalary) || 0;
                    return this.allowances.reduce((sum, a) => {
                        let amt = parseFloat(a.amount) || 0;
                        return sum + (a.amount_type === 'percentage' ? base * amt / 100 : amt);
                    }, 0);
                },
                grossSalary() {
                    return (parseFloat(this.basicSalary) || 0) + this.totalAllowances();
                },
                totalDeductions() {
                    let gross = this.grossSalary();
                    return this.deductions.reduce((sum, d) => {
                        let amt = parseFloat(d.amount) || 0;
                        return sum + (d.amount_type === 'percentage' ? gross * amt / 100 : amt);
                    }, 0);
                },
                netSalary() {
                    return this.grossSalary() - this.totalDeductions();
                },
                formatCurrency(value) {
                    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
