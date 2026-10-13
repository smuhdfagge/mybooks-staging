<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$fixedAsset->name" :description="$fixedAsset->asset_number.($fixedAsset->category ? ' · '.$fixedAsset->category->name : '')">
            <x-slot name="badge"><x-status-badge :status="['active' => 'active', 'under_maintenance' => 'pending', 'idle' => 'inactive', 'fully_depreciated' => 'completed', 'disposed' => 'cancelled', 'sold' => 'closed'][$fixedAsset->status] ?? 'draft'" :label="\App\Livewire\FixedAssets\FixedAssetsTable::LABELS[$fixedAsset->status] ?? ucfirst(str_replace('_', ' ', $fixedAsset->status))" /></x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('fixed-assets.schedule', $fixedAsset)">Depreciation schedule</x-table.menu-item>
                @can('edit fixed-assets')
                    @if ($fixedAsset->canDispose())
                        <x-table.menu-item danger data-open-modal="dispose-asset">Dispose of this asset</x-table.menu-item>
                    @endif
                @endcan
            </x-slot>
            @can('edit fixed-assets')
                <x-slot name="actions">
                    <a href="{{ route('fixed-assets.edit', $fixedAsset) }}" class="btn-secondary">Edit</a>
                    @if ($fixedAsset->canDepreciate())
                        <button type="button" data-open-modal="record-depreciation" class="btn-new">Record depreciation</button>
                    @endif
                </x-slot>
            @endcan
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Purchase Cost</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($fixedAsset->purchase_cost, 2) }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $fixedAsset->purchase_date->format('j M Y') }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Accumulated Depreciation</div>
                    <div class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-300">{{ number_format($fixedAsset->accumulated_depreciation, 2) }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ number_format($fixedAsset->depreciation_percentage, 1) }}% depreciated</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Book Value</div>
                    <div class="mt-1 text-2xl font-semibold text-green-700 dark:text-green-400">{{ number_format($fixedAsset->book_value, 2) }}</div>
                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 mt-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ 100 - $fixedAsset->depreciation_percentage }}%"></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Remaining Life</div>
                    <div class="mt-1 text-2xl font-semibold text-brand-600 dark:text-brand-300">{{ $fixedAsset->remaining_useful_life_months }}</div>
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
                                <a href="{{ route('fixed-assets.schedule', $fixedAsset) }}" class="text-sm text-brand-600 hover:text-brand-700 dark:text-brand-300">
                                    View Full Schedule →
                                </a>
                            </div>
                            <x-table caption="Depreciation plan">
<x-slot name="head">
                                            <x-table.th>Period</x-table.th>
                                            <x-table.th>Date</x-table.th>
                                            <x-table.th num>Depreciation</x-table.th>
                                            <x-table.th num>Book value</x-table.th>
</x-slot>

                                        @forelse(collect($schedule)->take(12) as $period)
                                        <tr>
                                            <td class="dark:text-white">{{ $period['period'] }}</td>
                                            <td>{{ $period['date']->format('M Y') }}</td>
                                            <td class="dark:text-white num">@fig($period['depreciation_amount'], false)</td>
                                            <td class="dark:text-white num">@fig($period['book_value'], false)</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="text-center">
                                                No depreciation schedule available
                                            </td>
                                        </tr>
                                        @endforelse
</x-table>
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
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->in_service_date->format('j M Y') }}</dd>
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
                                    <dd class="mt-1 text-sm text-brand-600 dark:text-brand-300">{{ $fixedAsset->next_depreciation_date->format('j M Y') }}</dd>
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
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $fixedAsset->disposal_date->format('j M Y') }}</dd>
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
                                    <dd class="mt-1 text-sm {{ $fixedAsset->gain_loss_on_disposal >= 0 ? 'text-green-700' : 'text-red-600' }}">
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
    <x-modal name="record-depreciation" title="Record Depreciation" maxWidth="md">
        <div class="px-6 pb-5">
            <div class="mt-4">
                <form action="{{ route('fixed-assets.depreciate', $fixedAsset) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="depreciation_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Depreciation Date</label>
                        <input type="date" name="depreciation_date" id="depreciation_date" value="{{ $fixedAsset->next_depreciation_date?->format('Y-m-d') ?? date('Y-m-d') }}" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="mb-4">
                        <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes (Optional)</label>
                        <textarea name="notes" id="notes" rows="3"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" data-close-modal="record-depreciation" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" class="btn-primary">
                            Record Depreciation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </x-modal>

    <!-- Dispose Modal -->
    <x-modal name="dispose-asset" title="Dispose Asset" maxWidth="md">
        <div class="px-6 pb-5">
            <div class="mt-4">
                <form action="{{ route('fixed-assets.dispose', $fixedAsset) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="disposal_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Disposal Method *</label>
                        <select name="disposal_method" id="disposal_method" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
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
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="mb-4">
                        <label for="disposal_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Disposal Date *</label>
                        <input type="date" name="disposal_date" id="disposal_date" value="{{ date('Y-m-d') }}" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="mb-4">
                        <label for="disposal_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                        <textarea name="disposal_notes" id="disposal_notes" rows="3"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" data-close-modal="dispose-asset" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" class="btn-danger">
                            Dispose Asset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </x-modal>
</x-app-layout>
