<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $department->name }}
                @if($department->code)<span class="ml-2 text-sm font-normal text-gray-500">({{ $department->code }})</span>@endif
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('departments.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                    Back to List
                </a>
                @can('edit departments')
                    <a href="{{ route('departments.edit', $department) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 transition ease-in-out duration-150">
                        Edit
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $department->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ $department->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Manager</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $department->manager?->full_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Parent department</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            @if($department->parent)
                                <a href="{{ route('departments.show', $department->parent) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $department->parent->name }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Employees</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $department->employees->count() }}</dd>
                    </div>
                </dl>
                @if($department->description)
                    <p class="mt-6 text-sm text-gray-700 dark:text-gray-300">{{ $department->description }}</p>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <h3 class="px-6 py-4 text-lg font-medium text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700">Employees</h3>
                @if($department->employees->isEmpty())
                    <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">No employees in this department yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Employee ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($department->employees as $employee)
                                    <tr>
                                        <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $employee->employee_id }}</td>
                                        <td class="px-6 py-3 text-sm">
                                            @can('view employees')
                                                <a href="{{ route('employees.show', $employee) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $employee->full_name }}</a>
                                            @else
                                                <span class="text-gray-900 dark:text-gray-100">{{ $employee->full_name }}</span>
                                            @endcan
                                        </td>
                                        <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $employee->email }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ ucfirst((string) $employee->status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if($department->children->isNotEmpty() || $department->designations->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Sub-departments</h3>
                        @forelse($department->children as $child)
                            <a href="{{ route('departments.show', $child) }}" class="block py-1 text-sm text-brand-600 dark:text-brand-300 hover:underline">{{ $child->name }}</a>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">None</p>
                        @endforelse
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Designations</h3>
                        @forelse($department->designations as $designation)
                            <p class="py-1 text-sm text-gray-700 dark:text-gray-300">{{ $designation->name }}</p>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">None</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
