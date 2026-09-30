<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Fixed Asset Details') }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('fixed-assets.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
                @can('edit fixed-assets')
                    @if($fixedAsset->canDispose())
                    <button data-call="openDisposeModal" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Dispose
                    </button>
                    @endif
                    @if($fixedAsset->canDepreciate())
                    <button data-call="openDepreciationModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Record Depreciation
                    </button>
                    @endif
                    <a href="{{ route('fixed-assets.edit', $fixedAsset) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        Edit
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Asset Header -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fixedAsset->name }}</h3>
                            <p class="text-gray-600 dark:text-gray-400">{{ $fixedAsset->asset_number }}</p>
                        </div>
                        <span class="px-3 py-1 text-sm font-semibold rounded-full
                            @if($fixedAsset->status === 'active') bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100
                            @elseif($fixedAsset->status === 'fully_depreciated') bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                            @elseif($fixedAsset->status === 'disposed') bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100
                            @else bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100
                            @endif">
                            {{ ucfirst(str_replace('_', ' ', $fixedAsset->status)) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Purchase Cost</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($fixedAsset->purchase_cost, 2) }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $fixedAsset->purchase_date->format('M d, Y') }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Accumulated Depreciation</div>
                    <div class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">{{ number_format($fixedAsset->accumulated_depreciation, 2) }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ number_format($fixedAsset->depreciation_percentage, 1) }}% depreciated</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Book Value</div>
                    <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ number_format($fixedAsset->book_value, 2) }}</div>
                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 mt-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ 100 - $fixedAsset->depreciation_percentage }}%"></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Remaining Life</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ $fixedAsset->remaining_useful_life_months }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">months</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Asset Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Asset Information</h4>
                            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @if($fixedAsset->category)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->category->name }}</dd>
                                </div>
                                @endif
                                @if($fixedAsset->serial_number)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Serial Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->serial_number }}</dd>
                                </div>
                                @endif
                                @if($fixedAsset->model)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Model</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->model }}</dd>
                                </div>
                                @endif
                                @if($fixedAsset->manufacturer)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Manufacturer</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->manufacturer }}</dd>
                                </div>
                                @endif
                                @if($fixedAsset->location)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Location</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->location }}</dd>
                                </div>
                                @endif
                                @if($fixedAsset->vendor)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Vendor</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->vendor->name }}</dd>
                                </div>
                                @endif
                                @if($fixedAsset->description)
                                <div class="md:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->description }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <!-- Depreciation Schedule -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Depreciation Schedule</h4>
                                <a href="{{ route('fixed-assets.schedule', $fixedAsset) }}" class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">
                                    View Full Schedule →
                                </a>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Period</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Depreciation</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Book Value</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @forelse(collect($schedule)->take(12) as $period)
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $period['period'] }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $period['date']->format('M Y') }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-white text-right">{{ number_format($period['depreciation_amount'], 2) }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-white text-right">{{ number_format($period['book_value'], 2) }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                                No depreciation schedule available
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if(count($schedule) > 12)
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-center">
                                Showing first 12 months of {{ count($schedule) }} total periods
                            </p>
                            @endif
                        </div>
                    </div>

                    <!-- Depreciation History -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Depreciation History</h4>
                            <div class="space-y-3">
                                @forelse($fixedAsset->depreciations->take(10) as $depreciation)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            Period {{ $depreciation->period_number }} - {{ $depreciation->depreciation_date->format('M Y') }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ ucfirst($depreciation->status) }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ number_format($depreciation->depreciation_amount, 2) }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Book Value: {{ number_format($depreciation->book_value, 2) }}
                                        </div>
                                    </div>
                                </div>
                                @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No depreciation recorded yet</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Depreciation Details -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Depreciation Details</h4>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Method</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $fixedAsset->depreciation_method)) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Useful Life</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->useful_life_months }} months ({{ round($fixedAsset->useful_life_years, 1) }} years)</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">In Service Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->in_service_date->format('M d, Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Depreciable Amount</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ number_format($fixedAsset->depreciable_amount, 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Salvage Value</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ number_format($fixedAsset->salvage_value, 2) }}</dd>
                                </div>
                                @if($fixedAsset->next_depreciation_date)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Next Depreciation</dt>
                                    <dd class="mt-1 text-sm text-blue-600 dark:text-blue-400">{{ $fixedAsset->next_depreciation_date->format('M d, Y') }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    @if($fixedAsset->disposal_date)
                    <!-- Disposal Details -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Disposal Details</h4>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Disposal Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->disposal_date->format('M d, Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Method</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $fixedAsset->disposal_method)) }}</dd>
                                </div>
                                @if($fixedAsset->disposal_amount)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Disposal Amount</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ number_format($fixedAsset->disposal_amount, 2) }}</dd>
                                </div>
                                @endif
                                @if($fixedAsset->gain_loss_on_disposal !== null)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Gain/Loss</dt>
                                    <dd class="mt-1 text-sm {{ $fixedAsset->gain_loss_on_disposal >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $fixedAsset->gain_loss_on_disposal >= 0 ? 'Gain' : 'Loss' }}: {{ number_format(abs($fixedAsset->gain_loss_on_disposal), 2) }}
                                    </dd>
                                </div>
                                @endif
                                @if($fixedAsset->disposal_notes)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Notes</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->disposal_notes }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                    @endif

                    @if($fixedAsset->notes)
                    <!-- Notes -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Notes</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $fixedAsset->notes }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Depreciation Modal -->
    <div id="depreciationModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Record Depreciation</h3>
                <form action="{{ route('fixed-assets.depreciate', $fixedAsset) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="depreciation_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Depreciation Date</label>
                        <input type="date" name="depreciation_date" id="depreciation_date" value="{{ $fixedAsset->next_depreciation_date?->format('Y-m-d') ?? date('Y-m-d') }}" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes (Optional)</label>
                        <textarea name="notes" id="notes" rows="3"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" data-call="closeDepreciationModal" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Record Depreciation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dispose Modal -->
    <div id="disposeModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Dispose Asset</h3>
                <form action="{{ route('fixed-assets.dispose', $fixedAsset) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="disposal_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Disposal Method *</label>
                        <select name="disposal_method" id="disposal_method" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Method</option>
                            <option value="sale">Sale</option>
                            <option value="scrapped">Scrapped</option>
                            <option value="donated">Donated</option>
                            <option value="lost">Lost/Stolen</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="disposal_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Disposal Amount</label>
                        <input type="number" name="disposal_amount" id="disposal_amount" step="0.01" min="0" value="0"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="disposal_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Disposal Date *</label>
                        <input type="date" name="disposal_date" id="disposal_date" value="{{ date('Y-m-d') }}" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="disposal_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                        <textarea name="disposal_notes" id="disposal_notes" rows="3"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" data-call="closeDisposeModal" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            Dispose Asset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script nonce="{{ app('csp-nonce') }}">
        function openDepreciationModal() {
            document.getElementById('depreciationModal').classList.remove('hidden');
        }
        function closeDepreciationModal() {
            document.getElementById('depreciationModal').classList.add('hidden');
        }
        function openDisposeModal() {
            document.getElementById('disposeModal').classList.remove('hidden');
        }
        function closeDisposeModal() {
            document.getElementById('disposeModal').classList.add('hidden');
        }
    </script>
</x-app-layout>
