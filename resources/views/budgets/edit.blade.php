<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Edit Budget: {{ $budget->name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Fiscal Year {{ $budget->fiscal_year }}</p>
            </div>
            <a href="{{ route('budgets.show', $budget) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Budget
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="budgetEditor()">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('budgets.update', $budget) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Budget Info -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Budget Name *</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $budget->name) }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <input type="text" name="description" id="description" value="{{ old('description', $budget->description) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Budget Lines -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Budget Line Items</h3>
                            <div class="flex gap-2">
                                <button type="button" @click="showImportModal = true"
                                        class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                    </svg>
                                    Import Lines
                                </button>
                                <button type="button" @click="showAddAccount = true" 
                                        class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Add Account
                                </button>
                            </div>
                        </div>

                        <!-- Add Account Modal -->
                        <div x-show="showAddAccount" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
                            <div class="flex items-center justify-center min-h-screen px-4">
                                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showAddAccount = false"></div>
                                <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-lg w-full p-6">
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Add Account to Budget</h4>
                                    <select x-model="selectedAccountId" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 mb-4">
                                        <option value="">Select an account...</option>
                                        <optgroup label="Income Accounts">
                                            @foreach($accounts['income'] ?? [] as $account)
                                                @if(!in_array($account->id, $existingAccountIds))
                                                <option value="{{ $account->id }}" data-code="{{ $account->account_code }}" data-name="{{ $account->name }}" data-type="income">
                                                    {{ $account->account_code }} - {{ $account->name }}
                                                </option>
                                                @endif
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="Expense Accounts">
                                            @foreach($accounts['expense'] ?? [] as $account)
                                                @if(!in_array($account->id, $existingAccountIds))
                                                <option value="{{ $account->id }}" data-code="{{ $account->account_code }}" data-name="{{ $account->name }}" data-type="expense">
                                                    {{ $account->account_code }} - {{ $account->name }}
                                                </option>
                                                @endif
                                            @endforeach
                                        </optgroup>
                                    </select>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="showAddAccount = false" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">Cancel</button>
                                        <button type="button" @click="addAccount()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Add</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lines Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-48">Account</th>
                                        @foreach($months as $key => $name)
                                        <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-24">{{ substr($name, 0, 3) }}</th>
                                        @endforeach
                                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600 w-28">Annual</th>
                                        <th class="px-2 py-3 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <template x-for="(line, index) in lines" :key="line.key">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-3 py-2 text-sm">
                                                <input type="hidden" :name="'lines['+index+'][id]'" :value="line.id">
                                                <input type="hidden" :name="'lines['+index+'][account_id]'" :value="line.account_id">
                                                <span class="text-gray-500 dark:text-gray-400 text-xs" x-text="line.account_code"></span>
                                                <span class="text-gray-900 dark:text-gray-100 block text-xs" x-text="line.account_name"></span>
                                            </td>
                                            @foreach($months as $key => $name)
                                            <td class="px-1 py-2">
                                                <input type="number" step="0.01" min="0"
                                                       :name="'lines['+index+'][{{ $key }}]'"
                                                       x-model.number="line.{{ $key }}"
                                                       @input="calculateRowTotal(line)"
                                                       class="w-full text-right text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 px-1 py-1">
                                            </td>
                                            @endforeach
                                            <td class="px-3 py-2 text-sm text-right font-semibold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-700">
                                                <span x-text="formatNumber(line.annual_total)"></span>
                                            </td>
                                            <td class="px-2 py-2">
                                                <button type="button" @click="removeLine(index)" class="text-red-600 hover:text-red-800" title="Remove">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="lines.length === 0">
                                        <td colspan="15" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            No budget lines. Click "Add Account" to add your first budget line item.
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Totals</th>
                                        @foreach($months as $key => $name)
                                        <th class="px-2 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            <span x-text="formatNumber(monthTotals.{{ $key }})"></span>
                                        </th>
                                        @endforeach
                                        <th class="px-3 py-3 text-right text-sm font-bold text-gray-900 dark:text-gray-100 bg-gray-200 dark:bg-gray-600">
                                            <span x-text="formatNumber(grandTotal)"></span>
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('budgets.show', $budget) }}" class="inline-flex items-center px-4 py-2 bg-gray-300 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-400">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        Save Budget
                    </button>
                </div>
            </form>

            <!-- Import Lines Modal (outside main form to avoid nested forms) -->
            <div x-show="showImportModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showImportModal = false"></div>
                    <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-lg w-full p-6">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Import Budget Line Items</h4>
                        
                        <form action="{{ route('budgets.import', $budget) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CSV or Excel File *</label>
                                <input type="file" name="file" accept=".csv,.xlsx,.xls" required
                                       class="block w-full text-sm text-gray-500 dark:text-gray-400
                                              file:mr-4 file:py-2 file:px-4
                                              file:rounded-md file:border-0
                                              file:text-sm file:font-semibold
                                              file:bg-blue-50 file:text-blue-700
                                              dark:file:bg-blue-900 dark:file:text-blue-300
                                              hover:file:bg-blue-100 dark:hover:file:bg-blue-800">
                            </div>

                            <div class="mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="update_existing" value="1"
                                           class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Update existing lines (overwrite amounts for matching accounts)</span>
                                </label>
                            </div>

                            <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg mb-4">
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>Required column:</strong> account_code
                                </p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>Optional columns:</strong> account_name, jan, feb, mar, apr, may, jun, jul, aug, sep, oct, nov, dec, notes
                                </p>
                                <a href="{{ route('budgets.import-template') }}" 
                                   class="inline-flex items-center text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    Download sample template
                                </a>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button type="button" @click="showImportModal = false" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Import</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script nonce="{{ app('csp-nonce') }}">
        function budgetEditor() {
            return {
                showAddAccount: false,
                showImportModal: false,
                selectedAccountId: '',
                lines: @json($budgetLinesJson),
                existingAccountIds: @json($existingAccountIds),

                get monthTotals() {
                    const totals = { jan: 0, feb: 0, mar: 0, apr: 0, may: 0, jun: 0, jul: 0, aug: 0, sep: 0, oct: 0, nov: 0, dec: 0 };
                    this.lines.forEach(line => {
                        Object.keys(totals).forEach(month => {
                            totals[month] += parseFloat(line[month]) || 0;
                        });
                    });
                    return totals;
                },

                get grandTotal() {
                    return this.lines.reduce((sum, line) => sum + (parseFloat(line.annual_total) || 0), 0);
                },

                calculateRowTotal(line) {
                    const months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
                    line.annual_total = months.reduce((sum, month) => sum + (parseFloat(line[month]) || 0), 0);
                },

                formatNumber(num) {
                    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num || 0);
                },

                addAccount() {
                    if (!this.selectedAccountId) return;
                    
                    const select = document.querySelector('select[x-model="selectedAccountId"]');
                    const option = select.querySelector(`option[value="${this.selectedAccountId}"]`);
                    
                    if (option && !this.existingAccountIds.includes(parseInt(this.selectedAccountId))) {
                        this.lines.push({
                            key: 'new_' + Date.now(),
                            id: null,
                            account_id: parseInt(this.selectedAccountId),
                            account_code: option.dataset.code,
                            account_name: option.dataset.name,
                            jan: 0, feb: 0, mar: 0, apr: 0, may: 0, jun: 0,
                            jul: 0, aug: 0, sep: 0, oct: 0, nov: 0, dec: 0,
                            annual_total: 0
                        });
                        this.existingAccountIds.push(parseInt(this.selectedAccountId));
                    }
                    
                    this.selectedAccountId = '';
                    this.showAddAccount = false;
                },

                removeLine(index) {
                    const accountId = this.lines[index].account_id;
                    this.lines.splice(index, 1);
                    this.existingAccountIds = this.existingAccountIds.filter(id => id !== accountId);
                }
            }
        }
    </script>
</x-app-layout>
