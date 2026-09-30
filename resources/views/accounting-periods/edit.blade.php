<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit: {{ $accountingPeriod->name }}
            </h2>
            <a href="{{ route('accounting-periods.show', $accountingPeriod) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    @if($accountingPeriod->isClosed())
                        <div class="mb-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                            This period is closed. Only the name and fiscal year can be modified.
                        </div>
                    @endif

                    <form action="{{ route('accounting-periods.update', $accountingPeriod) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-4">
                            <label for="name" class="form-label">Period Name *</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $accountingPeriod->name) }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        </div>

                        @if(!$accountingPeriod->isClosed())
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" name="start_date" id="start_date" value="{{ old('start_date', $accountingPeriod->start_date->format('Y-m-d')) }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label for="end_date" class="form-label">End Date *</label>
                                <input type="date" name="end_date" id="end_date" value="{{ old('end_date', $accountingPeriod->end_date->format('Y-m-d')) }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>
                        </div>
                        @else
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="form-label">Start Date</label>
                                <p class="text-sm text-gray-900 dark:text-gray-100 py-2">{{ $accountingPeriod->start_date->format('F d, Y') }}</p>
                            </div>
                            <div>
                                <label class="form-label">End Date</label>
                                <p class="text-sm text-gray-900 dark:text-gray-100 py-2">{{ $accountingPeriod->end_date->format('F d, Y') }}</p>
                            </div>
                        </div>
                        @endif

                        <div class="mb-6">
                            <x-field name="fiscal_year" label="Fiscal Year" type="number" :value="old('fiscal_year', $accountingPeriod->fiscal_year)" min="2000" max="2100" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('accounting-periods.show', $accountingPeriod) }}" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 transition">
                                Cancel
                            </a>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                                Update Period
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
