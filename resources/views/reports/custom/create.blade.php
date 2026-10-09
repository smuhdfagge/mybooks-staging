<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Create Custom Report') }}
            </h2>
            <a href="{{ route('reports.custom.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Cancel
            </a>
        </div>
    </x-slot>

    <div x-data="reportBuilder()" x-init="init()">
        <form method="POST" action="{{ route('reports.custom.store') }}" class="space-y-6">
            @csrf

            <!-- Basic Info -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Report Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Report Name *</label>
                            <input type="text" name="name" id="name" required value="{{ old('name') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                placeholder="e.g., Monthly Sales Summary" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                            @error('name')
                                <p id="name-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="data_source" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data Source *</label>
                            <select name="data_source" id="data_source" required x-model="dataSource" @change="loadColumns()"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm" @error('data_source') aria-invalid="true" aria-describedby="data_source-error" @enderror>
                                <option value="">Select a data source</option>
                                @foreach($dataSources as $key => $source)
                                    <option value="{{ $key }}">{{ $source['label'] }}</option>
                                @endforeach
                            </select>
                            @error('data_source')
                                <p id="data_source-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea name="description" id="description" rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                placeholder="Optional description of this report">{{ old('description') }}</textarea>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="is_public" value="1" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-brand-300">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Share with team members</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Column Selection -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg" x-show="dataSource">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Select Columns *</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Choose which columns to include in your report.</p>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3" x-show="Object.keys(availableColumns).length > 0">
                        <template x-for="(config, column) in availableColumns" :key="column">
                            <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer transition-colors">
                                <input type="checkbox" :name="'columns[]'" :value="column"
                                    x-model="selectedColumns"
                                    class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-600 dark:border-gray-500 dark:text-brand-300">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300" x-text="config.label"></span>
                            </label>
                        </template>
                    </div>
                    
                    <div x-show="Object.keys(availableColumns).length === 0" class="text-center py-4 text-gray-500 dark:text-gray-400">
                        <p>Select a data source to see available columns.</p>
                    </div>

                    @error('columns')
                        <p id="columns-error" class="mt-2 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Date Filter -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg" x-show="dataSource && dateFields.length > 0">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Date Filter</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Select a date field to enable date range filtering when running the report.</p>
                    
                    <div class="max-w-xs">
                        <select aria-label="Selected date field" name="date_field" x-model="selectedDateField"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                            <option value="">No date filter</option>
                            <template x-for="field in dateFields" :key="field">
                                <option :value="field" x-text="availableColumns[field]?.label || field"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg" x-show="dataSource">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Filters</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Add conditions to filter the data.</p>
                        </div>
                        <button type="button" @click="addFilter()" 
                            class="inline-flex items-center px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Filter
                        </button>
                    </div>
                    
                    <div class="space-y-3">
                        <template x-for="(filter, index) in filters" :key="index">
                            <div class="flex flex-wrap items-center gap-2 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <select aria-label="Column" :name="'filters[' + index + '][column]'" x-model="filter.column" required
                                    class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white text-sm">
                                    <option value="">Select column</option>
                                    <template x-for="(config, column) in availableColumns" :key="column">
                                        <option :value="column" x-text="config.label"></option>
                                    </template>
                                </select>
                                
                                <select aria-label="Operator" :name="'filters[' + index + '][operator]'" x-model="filter.operator" required
                                    class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white text-sm">
                                    @foreach($operators as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                
                                <input aria-label="Value" type="text" :name="'filters[' + index + '][value]'" x-model="filter.value" placeholder="Value"
                                    x-show="!['is_null', 'is_not_null'].includes(filter.operator)"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white text-sm flex-1 min-w-[150px]">
                                
                                <button type="button" @click="removeFilter(index)" class="text-red-600 hover:text-red-700 p-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                    
                    <div x-show="filters.length === 0" class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                        No filters added. Click "Add Filter" to add conditions.
                    </div>
                </div>
            </div>

            <!-- Grouping -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg" x-show="dataSource && groupFields.length > 0">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Grouping</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Group results by a specific field.</p>
                    
                    <div class="max-w-xs">
                        <select aria-label="Selected group by" name="group_by" x-model="selectedGroupBy"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                            <option value="">No grouping</option>
                            <template x-for="field in groupFields" :key="field">
                                <option :value="field" x-text="availableColumns[field]?.label || field"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Aggregations -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg" x-show="dataSource">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Calculations</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Add summary calculations (totals, averages, etc.)</p>
                        </div>
                        <button type="button" @click="addAggregation()" 
                            class="inline-flex items-center px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Calculation
                        </button>
                    </div>
                    
                    <div class="space-y-3">
                        <template x-for="(agg, index) in aggregations" :key="index">
                            <div class="flex flex-wrap items-center gap-2 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <select aria-label="Function" :name="'aggregations[' + index + '][function]'" x-model="agg.function" required
                                    class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white text-sm">
                                    @foreach($aggregations as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                
                                <span class="text-gray-500 dark:text-gray-400">of</span>
                                
                                <select aria-label="Column" :name="'aggregations[' + index + '][column]'" x-model="agg.column" required
                                    class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white text-sm">
                                    <option value="">Select column</option>
                                    <template x-for="(config, column) in numericColumns" :key="column">
                                        <option :value="column" x-text="config.label"></option>
                                    </template>
                                </select>
                                
                                <button type="button" @click="removeAggregation(index)" class="text-red-600 hover:text-red-700 p-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                    
                    <div x-show="aggregations.length === 0" class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                        No calculations added. Click "Add Calculation" to add summary values.
                    </div>
                </div>
            </div>

            <!-- Sorting -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg" x-show="dataSource && selectedColumns.length > 0">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Sorting</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Define how results should be sorted.</p>
                        </div>
                        <button type="button" @click="addSort()" 
                            class="inline-flex items-center px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Sort
                        </button>
                    </div>
                    
                    <div class="space-y-3">
                        <template x-for="(sort, index) in sortBy" :key="index">
                            <div class="flex flex-wrap items-center gap-2 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <select aria-label="Column" :name="'sort_by[' + index + '][column]'" x-model="sort.column" required
                                    class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white text-sm">
                                    <option value="">Select column</option>
                                    <template x-for="column in selectedColumns" :key="column">
                                        <option :value="column" x-text="availableColumns[column]?.label || column"></option>
                                    </template>
                                </select>
                                
                                <select aria-label="Direction" :name="'sort_by[' + index + '][direction]'" x-model="sort.direction" required
                                    class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white text-sm">
                                    <option value="asc">Ascending</option>
                                    <option value="desc">Descending</option>
                                </select>
                                
                                <button type="button" @click="removeSort(index)" class="text-red-600 hover:text-red-700 p-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                    
                    <div x-show="sortBy.length === 0" class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                        No sorting defined. Click "Add Sort" to specify sort order.
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-3">
                <a href="{{ route('reports.custom.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Create Report
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function reportBuilder() {
            return {
                dataSource: '',
                availableColumns: {},
                selectedColumns: [],
                dateFields: [],
                groupFields: [],
                selectedDateField: '',
                selectedGroupBy: '',
                filters: [],
                aggregations: [],
                sortBy: [],

                init() {
                    // Initialize from old values if validation failed
                },

                async loadColumns() {
                    if (!this.dataSource) {
                        this.availableColumns = {};
                        this.dateFields = [];
                        this.groupFields = [];
                        this.selectedColumns = [];
                        return;
                    }

                    try {
                        const response = await fetch(`{{ route('reports.custom.get-columns') }}?data_source=${this.dataSource}`);
                        const data = await response.json();
                        
                        this.availableColumns = data.columns || {};
                        this.dateFields = data.date_fields || [];
                        this.groupFields = data.group_fields || [];
                        this.selectedColumns = [];
                        this.selectedDateField = '';
                        this.selectedGroupBy = '';
                        this.filters = [];
                        this.aggregations = [];
                        this.sortBy = [];
                    } catch (error) {
                        console.error('Error loading columns:', error);
                    }
                },

                get numericColumns() {
                    return Object.fromEntries(
                        Object.entries(this.availableColumns).filter(([key, config]) => config.type === 'decimal')
                    );
                },

                addFilter() {
                    this.filters.push({ column: '', operator: 'equals', value: '' });
                },

                removeFilter(index) {
                    this.filters.splice(index, 1);
                },

                addAggregation() {
                    this.aggregations.push({ function: 'sum', column: '' });
                },

                removeAggregation(index) {
                    this.aggregations.splice(index, 1);
                },

                addSort() {
                    this.sortBy.push({ column: '', direction: 'asc' });
                },

                removeSort(index) {
                    this.sortBy.splice(index, 1);
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
