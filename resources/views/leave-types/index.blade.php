<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Leave Types') }}</h2>
            @can('create leave-types')
                <a href="{{ route('leave-types.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition ease-in-out duration-150">Add Leave Type</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @foreach(['success' => 'green', 'error' => 'red'] as $key => $color)
                @if(session($key))
                    <div class="mb-4 rounded-lg border border-{{ $color }}-200 bg-{{ $color }}-50 dark:bg-{{ $color }}-900/20 dark:border-{{ $color }}-800 p-4 text-sm text-{{ $color }}-800 dark:text-{{ $color }}-200">{{ session($key) }}</div>
                @endif
            @endforeach

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                @if($leaveTypes->isEmpty())
                    <div class="p-10 text-center">
                        <p class="text-gray-700 dark:text-gray-300 font-medium">No leave types yet</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add the kinds of leave your staff can take, such as annual, sick or maternity leave.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Code</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Days / year</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Paid</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Carry forward</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Requests</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($leaveTypes as $type)
                                    <tr>
                                        <td class="px-6 py-3 text-sm"><a href="{{ route('leave-types.show', $type) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $type->name }}</a></td>
                                        <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $type->code ?: '—' }}</td>
                                        <td class="px-6 py-3 text-sm text-right text-gray-700 dark:text-gray-300">{{ $type->days_per_year }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $type->is_paid ? 'Yes' : 'No' }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $type->is_carry_forward ? 'Up to '.$type->max_carry_forward_days.' days' : 'No' }}</td>
                                        <td class="px-6 py-3 text-sm text-right text-gray-700 dark:text-gray-300">{{ $type->leaves_count }}</td>
                                        <td class="px-6 py-3 text-sm">
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $type->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">{{ $type->is_active ? 'Active' : 'Inactive' }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-sm text-right whitespace-nowrap">
                                            @can('edit leave-types')
                                                <a href="{{ route('leave-types.edit', $type) }}" class="text-yellow-600 dark:text-yellow-400 hover:underline">Edit</a>
                                            @endcan
                                            @can('delete leave-types')
                                                @if($type->leaves_count === 0)
                                                    <form action="{{ route('leave-types.destroy', $type) }}" method="POST" class="inline ml-3" onsubmit="return confirm('Delete this leave type?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3">{{ $leaveTypes->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
