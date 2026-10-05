@php $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.'); @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Bills of materials</h2>
            @can('create items')
                <a href="{{ route('bill-of-materials.create') }}" class="btn-primary">New bill of materials</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card>
                <div class="p-4 sm:p-6">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">A bill of materials is the recipe for a finished item: what goes into one batch and what else it costs. For example 40 bags of feed from 700 kg maize, 250 kg soya and 50 kg premix, plus labour.</p>
                    <form method="GET" class="mb-4 max-w-sm">
                        <label for="bom-search" class="form-label">Search</label>
                        <input type="search" id="bom-search" name="search" value="{{ $search }}" placeholder="Name or item..." class="form-control text-sm">
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase text-gray-500 dark:text-gray-300">
                                <tr>
                                    <th class="px-4 py-3 text-left">Bill</th>
                                    <th class="px-4 py-3 text-left">Makes</th>
                                    <th class="px-4 py-3 text-right hidden sm:table-cell">Components</th>
                                    <th class="px-4 py-3 text-right hidden md:table-cell">Builds</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                @forelse($boms as $bom)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3"><a href="{{ route('bill-of-materials.show', $bom) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $bom->label() }}</a></td>
                                        <td class="px-4 py-3">{{ $fmt($bom->output_quantity) }} {{ $bom->item?->unit }} {{ $bom->item?->name }}</td>
                                        <td class="px-4 py-3 text-right hidden sm:table-cell">{{ $bom->components->count() }}</td>
                                        <td class="px-4 py-3 text-right hidden md:table-cell">{{ $bom->assembly_orders_count }}</td>
                                        <td class="px-4 py-3"><x-status-badge :status="$bom->is_active ? 'active' : 'draft'" :label="$bom->is_active ? 'In use' : 'Not in use'" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No bills of materials yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($boms->hasPages())<div class="mt-4">{{ $boms->links() }}</div>@endif
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
