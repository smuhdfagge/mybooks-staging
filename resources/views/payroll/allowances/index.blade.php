<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Allowances" description="Extra pay on top of basic, such as housing or transport. Add them to salary structures.">
            <x-slot name="more">
                <x-table.menu-item :href="route('salary-structures.index')">Salary structures</x-table.menu-item>
                <x-table.menu-item :href="route('deductions.index')">Deductions</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                @can('create payroll')
                    <a href="{{ route('allowances.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New allowance
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:allowances.allowances-table />
</x-app-layout>
