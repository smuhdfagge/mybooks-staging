<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Salary structures" :description="'Basic pay with its allowances and deductions, one for each grade of staff. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('allowances.index')">Allowances</x-table.menu-item>
                <x-table.menu-item :href="route('deductions.index')">Deductions</x-table.menu-item>
                <x-table.menu-item :href="route('payroll.index')">Payroll runs</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                @can('create payroll')
                    <a href="{{ route('salary-structures.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New structure
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:salary-structures.salary-structures-table />
</x-app-layout>
