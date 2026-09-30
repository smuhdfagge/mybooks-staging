<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Salary Structure - {{ $salaryStructure->name }}
            </h2>
            <a href="{{ route('salary-structures.show', $salaryStructure) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Cancel
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('salary-structures.update', $salaryStructure) }}" method="POST" class="p-6" x-data="salaryStructureForm()">
                    @csrf
                    @method('PUT')

                    <!-- Employee & Basic Info -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Employee & Basic Salary
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Structure Name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" id="name" required value="{{ old('name', $salaryStructure->name) }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="basic_salary" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Basic Salary <span class="text-red-500">*</span></label>
                                <input type="number" name="basic_salary" id="basic_salary" step="0.01" min="0" x-model="basicSalary" required
                                    value="{{ old('basic_salary', $salaryStructure->basic_salary) }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="effective_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Effective From <span class="text-red-500">*</span></label>
                                <input type="date" name="effective_from" id="effective_from" required
                                    value="{{ old('effective_from', $salaryStructure->effective_from->format('Y-m-d')) }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="effective_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Effective To</label>
                                <input type="date" name="effective_to" id="effective_to"
                                    value="{{ old('effective_to', $salaryStructure->effective_to?->format('Y-m-d')) }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="is_active" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                <select name="is_active" id="is_active"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="1" {{ old('is_active', $salaryStructure->is_active) ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !old('is_active', $salaryStructure->is_active) ? 'selected' : '' }}>Inactive</option>
                                </select>
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
                                    <label x-show="index === 0" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Allowance</label>
                                    <select :name="'allowances['+index+'][name]'" x-model="allowance.name"
                                        @change="onAllowanceSelected(index)"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Select Allowance --</option>
                                        <template x-for="tpl in allowanceTemplates" :key="tpl.id">
                                            <option :value="tpl.name" x-text="tpl.name" :selected="allowance.name === tpl.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <label x-show="index === 0" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                                    <select :name="'allowances['+index+'][amount_type]'" x-model="allowance.amount_type"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="fixed">Fixed Amount</option>
                                        <option value="percentage">% of Basic</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label x-show="index === 0" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount</label>
                                    <input type="number" :name="'allowances['+index+'][amount]'" x-model="allowance.amount" step="0.01" min="0"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>
                                <div class="col-span-2">
                                    <label x-show="index === 0" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Taxable</label>
                                    <select :name="'allowances['+index+'][is_taxable]'" x-model="allowance.is_taxable"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <button type="button" @click="removeAllowance(index)" class="p-2 text-red-600 hover:text-red-800 dark:text-red-400">
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
                                    <label x-show="index === 0" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deduction</label>
                                    <select :name="'deductions['+index+'][name]'" x-model="deduction.name"
                                        @change="onDeductionSelected(index)"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Select Deduction --</option>
                                        <template x-for="tpl in deductionTemplates" :key="tpl.id">
                                            <option :value="tpl.name" x-text="tpl.name" :selected="deduction.name === tpl.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <label x-show="index === 0" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                                    <select :name="'deductions['+index+'][amount_type]'" x-model="deduction.amount_type"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="fixed">Fixed Amount</option>
                                        <option value="percentage">% of Gross</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label x-show="index === 0" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount</label>
                                    <input type="number" :name="'deductions['+index+'][amount]'" x-model="deduction.amount" step="0.01" min="0"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>
                                <div class="col-span-2">
                                    <label x-show="index === 0" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" title="Pre-tax deductions (pension, NHF, health insurance) are taken off pay before PAYE is worked out">Pre-tax</label>
                                    <select :name="'deductions['+index+'][is_taxable]'" x-model="deduction.is_taxable"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="0">No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <button type="button" @click="removeDeduction(index)" class="p-2 text-red-600 hover:text-red-800 dark:text-red-400">
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
                    <div class="mb-4">
                        <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                        <textarea name="notes" id="notes" rows="3"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $salaryStructure->notes) }}</textarea>
                    </div>

                    <!-- Change Reason -->
                    <div class="mb-8">
                        <label for="change_reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Reason for Change <span class="text-gray-500 dark:text-gray-400 font-normal">(optional — recorded in version history)</span>
                        </label>
                        <input type="text" name="change_reason" id="change_reason" maxlength="500"
                            value="{{ old('change_reason') }}"
                            placeholder="e.g. Annual salary review, Cost of living adjustment"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('salary-structures.show', $salaryStructure) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Update Salary Structure
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
                $allowancesData = old('allowances', $salaryStructure->allowances->map(fn($a) => ['name' => $a->name, 'amount_type' => $a->amount_type, 'amount' => $a->amount, 'is_taxable' => (string)$a->is_taxable])->values()->toArray()) ?: [['name' => '', 'amount_type' => 'fixed', 'amount' => '', 'is_taxable' => '1']];
                $deductionsData = old('deductions', $salaryStructure->deductions->map(fn($d) => ['name' => $d->name, 'amount_type' => $d->amount_type, 'amount' => $d->amount, 'is_taxable' => (string)$d->is_taxable])->values()->toArray()) ?: [['name' => '', 'amount_type' => 'fixed', 'amount' => '', 'is_taxable' => '0']];
            @endphp
            return {
                basicSalary: @js((string) old('basic_salary', $salaryStructure->basic_salary)),
                allowances: @js($allowancesData),
                deductions: @js($deductionsData),
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
                addAllowance() { this.allowances.push({ name: '', amount_type: 'fixed', amount: '', is_taxable: '1' }); },
                removeAllowance(index) { if (this.allowances.length > 1) this.allowances.splice(index, 1); },
                addDeduction() { this.deductions.push({ name: '', amount_type: 'fixed', amount: '', is_taxable: '0' }); },
                removeDeduction(index) { if (this.deductions.length > 1) this.deductions.splice(index, 1); },
                totalAllowances() {
                    let base = parseFloat(this.basicSalary) || 0;
                    return this.allowances.reduce((sum, a) => {
                        let amt = parseFloat(a.amount) || 0;
                        return sum + (a.amount_type === 'percentage' ? base * amt / 100 : amt);
                    }, 0);
                },
                grossSalary() { return (parseFloat(this.basicSalary) || 0) + this.totalAllowances(); },
                totalDeductions() {
                    let gross = this.grossSalary();
                    return this.deductions.reduce((sum, d) => {
                        let amt = parseFloat(d.amount) || 0;
                        return sum + (d.amount_type === 'percentage' ? gross * amt / 100 : amt);
                    }, 0);
                },
                netSalary() { return this.grossSalary() - this.totalDeductions(); },
                formatCurrency(value) { return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value); }
            }
        }
    </script>
    @endpush
</x-app-layout>
