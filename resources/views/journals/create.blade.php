<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Create Manual Journal Entry') }}
            </h2>
            <a href="{{ route('journals.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <x-form-auto-save formKey="journal-create">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('journals.store') }}" method="POST" class="p-6" id="journalForm">
                    @csrf

                    <!-- Journal Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Journal Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="journal_number" class="form-label">Journal Number</label>
                                <input type="text" id="journal_number" value="{{ $journalNumber }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
                            </div>

                            <div>
                                <x-field name="journal_date" label="Journal Date" type="date" :value="old('journal_date', date('Y-m-d'))" required />
                            </div>

                            <div>
                                <label for="reference" class="form-label">Reference</label>
                                <input type="text" name="reference" id="reference" value="{{ old('reference') }}"
                                    class="form-control @error('reference') border-red-500 @enderror"
                                    placeholder="e.g., Check #123" @error('reference') aria-invalid="true" aria-describedby="reference-error" @enderror>
                                @error('reference')
                                    <p id="reference-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="description" class="form-label">Description <span class="text-red-500">*</span></label>
                            <textarea name="description" id="description" rows="2" required maxlength="500"
                                class="form-control @error('description') border-red-500 @enderror"
                                placeholder="Enter journal description..." @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>
                            @error('description')
                                <p id="description-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Journal Entries -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Journal Line Items
                        </h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full" id="entriesTable">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/3">Account</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-32">Debit</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-32">Credit</th>
                                        <th class="px-4 py-3 w-16"></th>
                                    </tr>
                                </thead>
                                <tbody id="entriesBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @if(old('entries'))
                                        @foreach(old('entries') as $index => $entry)
                                            <tr class="entry-row">
                                                <td class="px-4 py-2">
                                                    <select name="entries[{{ $index }}][account_id]" required
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <option value="">Select Account</option>
                                                        @foreach($accounts as $account)
                                                            <option value="{{ $account->id }}" {{ $entry['account_id'] == $account->id ? 'selected' : '' }}>
                                                                {{ $account->account_code }} - {{ $account->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <input aria-label="Line description" type="text" name="entries[{{ $index }}][description]" value="{{ $entry['description'] ?? '' }}"
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        placeholder="Line description">
                                                </td>
                                                <td class="px-4 py-2">
                                                    <input aria-label="Debit" type="number" name="entries[{{ $index }}][debit]" value="{{ $entry['debit'] ?? '' }}" min="0" step="0.01"
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right debit-input"
                                                        placeholder="0.00">
                                                </td>
                                                <td class="px-4 py-2">
                                                    <input aria-label="Credit" type="number" name="entries[{{ $index }}][credit]" value="{{ $entry['credit'] ?? '' }}" min="0" step="0.01"
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right credit-input"
                                                        placeholder="0.00">
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    <button type="button" class="remove-row-btn text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr class="entry-row">
                                            <td class="px-4 py-2">
                                                <select name="entries[0][account_id]" required
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">Select Account</option>
                                                    @foreach($accounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->account_code }} - {{ $account->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-4 py-2">
                                                <input aria-label="Line description" type="text" name="entries[0][description]"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="Line description">
                                            </td>
                                            <td class="px-4 py-2">
                                                <input aria-label="Debit" type="number" name="entries[0][debit]" min="0" step="0.01"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right debit-input"
                                                    placeholder="0.00">
                                            </td>
                                            <td class="px-4 py-2">
                                                <input aria-label="Credit" type="number" name="entries[0][credit]" min="0" step="0.01"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right credit-input"
                                                    placeholder="0.00">
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <button type="button" class="remove-row-btn text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="entry-row">
                                            <td class="px-4 py-2">
                                                <select name="entries[1][account_id]" required
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">Select Account</option>
                                                    @foreach($accounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->account_code }} - {{ $account->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-4 py-2">
                                                <input aria-label="Line description" type="text" name="entries[1][description]"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="Line description">
                                            </td>
                                            <td class="px-4 py-2">
                                                <input aria-label="Debit" type="number" name="entries[1][debit]" min="0" step="0.01"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right debit-input"
                                                    placeholder="0.00">
                                            </td>
                                            <td class="px-4 py-2">
                                                <input aria-label="Credit" type="number" name="entries[1][credit]" min="0" step="0.01"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right credit-input"
                                                    placeholder="0.00">
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <button type="button" class="remove-row-btn text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <td colspan="2" class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Totals:
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-gray-100">
                                            $<span id="totalDebit">0.00</span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-gray-100">
                                            $<span id="totalCredit">0.00</span>
                                        </td>
                                        <td></td>
                                    </tr>
                                    <tr id="differenceRow" class="hidden">
                                        <td colspan="2" class="px-4 py-2 text-right text-sm font-medium text-red-600 dark:text-red-400">
                                            Difference:
                                        </td>
                                        <td colspan="2" class="px-4 py-2 text-center text-sm font-bold text-red-600 dark:text-red-400">
                                            $<span id="difference">0.00</span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-4">
                            <button type="button" id="addLineBtn" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add Line
                            </button>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('journals.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-6 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Journal Entry
                        </button>
                    </div>
                </form>
            </div>
            </x-form-auto-save>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        let rowIndex = {{ old('entries') ? count(old('entries')) : 2 }};
        const accountsJson = @json($accounts);

        function addRow() {
            const tbody = document.getElementById('entriesBody');
            
            const row = document.createElement('tr');
            row.className = 'entry-row';
            row.innerHTML = `
                <td class="px-4 py-2">
                    <select name="entries[${rowIndex}][account_id]" required
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select Account</option>
                    </select>
                </td>
                <td class="px-4 py-2">
                    <input aria-label="Line description" type="text" name="entries[${rowIndex}][description]"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Line description">
                </td>
                <td class="px-4 py-2">
                    <input aria-label="Debit" type="number" name="entries[${rowIndex}][debit]" min="0" step="0.01"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right debit-input"
                        placeholder="0.00">
                </td>
                <td class="px-4 py-2">
                    <input aria-label="Credit" type="number" name="entries[${rowIndex}][credit]" min="0" step="0.01"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right credit-input"
                        placeholder="0.00">
                </td>
                <td class="px-4 py-2 text-center">
                    <button type="button" class="remove-row-btn text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </td>
            `;
            // Account names go in as text, never as HTML (S3).
            const select = row.querySelector('select');
            accountsJson.forEach(a => select.add(new Option(`${a.account_code} - ${a.name}`, a.id)));

            tbody.appendChild(row);
            rowIndex++;

            // Attach listeners to new row inputs
            row.querySelectorAll('.debit-input, .credit-input').forEach(input => {
                input.addEventListener('input', updateTotals);
            });
        }

        function removeRow(button) {
            const rows = document.querySelectorAll('.entry-row');
            if (rows.length > 2) {
                button.closest('tr').remove();
                updateTotals();
            } else {
                alert('A journal entry must have at least 2 lines.');
            }
        }

        function updateTotals() {
            let totalDebit = 0;
            let totalCredit = 0;

            document.querySelectorAll('.debit-input').forEach(input => {
                totalDebit += parseFloat(input.value) || 0;
            });

            document.querySelectorAll('.credit-input').forEach(input => {
                totalCredit += parseFloat(input.value) || 0;
            });

            document.getElementById('totalDebit').textContent = totalDebit.toFixed(2);
            document.getElementById('totalCredit').textContent = totalCredit.toFixed(2);

            const difference = Math.abs(totalDebit - totalCredit);
            const differenceRow = document.getElementById('differenceRow');
            
            if (difference > 0.01) {
                differenceRow.classList.remove('hidden');
                document.getElementById('difference').textContent = difference.toFixed(2);
            } else {
                differenceRow.classList.add('hidden');
            }
        }

        // Wire up all event listeners on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add Line button
            document.getElementById('addLineBtn').addEventListener('click', addRow);

            // Debit/credit inputs
            document.querySelectorAll('.debit-input, .credit-input').forEach(input => {
                input.addEventListener('input', updateTotals);
            });

            // Remove row buttons (delegated for dynamically added rows too)
            document.getElementById('entriesTable').addEventListener('click', function(e) {
                const btn = e.target.closest('.remove-row-btn');
                if (btn) {
                    removeRow(btn);
                }
            });

            updateTotals();
        });
    </script>
    @endpush
</x-app-layout>
