<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center">
            <a href="{{ route('activity-logs.index') }}" class="mr-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Activity Log Details') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <!-- Header Info -->
                    <div class="flex items-start justify-between mb-6">
                        <div>
                            <div class="flex items-center space-x-3">
                                @php
                                    $colorClasses = match($activityLog->action_color) {
                                        'green' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                                        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
                                        'red' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                                        'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100',
                                        'indigo' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-800 dark:text-indigo-100',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $colorClasses }}">
                                    {{ $activityLog->action_label }}
                                </span>
                                @if($activityLog->model_type_short)
                                    <span class="text-gray-500 dark:text-gray-400">
                                        {{ $activityLog->model_type_short }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-lg text-gray-900 dark:text-gray-100">
                                {{ $activityLog->description }}
                            </p>
                        </div>
                        <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                            <div>{{ $activityLog->created_at->format('M d, Y') }}</div>
                            <div>{{ $activityLog->created_at->format('H:i:s') }}</div>
                            <div class="text-xs">{{ $activityLog->created_at->diffForHumans() }}</div>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- User Info -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                                User Information
                            </h3>
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Name:</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $activityLog->user_name ?? 'System' }}
                                    </dd>
                                </div>
                                @if($activityLog->user)
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-500 dark:text-gray-400">Email:</dt>
                                        <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $activityLog->user->email }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        </div>

                        <!-- Request Info -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                                Request Information
                            </h3>
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">IP Address:</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $activityLog->ip_address ?? '-' }}
                                    </dd>
                                </div>
                                @if($activityLog->user_agent)
                                    <div>
                                        <dt class="text-sm text-gray-500 dark:text-gray-400 mb-1">User Agent:</dt>
                                        <dd class="text-xs text-gray-700 dark:text-gray-300 break-all">
                                            {{ $activityLog->user_agent }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <!-- Model Info -->
                    @if($activityLog->model_type)
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                                Record Information
                            </h3>
                            <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Module:</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $activityLog->model_type_short }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Record ID:</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $activityLog->model_id }}
                                    </dd>
                                </div>
                                @if($activityLog->model_name)
                                    <div>
                                        <dt class="text-sm text-gray-500 dark:text-gray-400">Identifier:</dt>
                                        <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $activityLog->model_name }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @endif

                    <!-- Changed Fields -->
                    @if($activityLog->changed_fields && count($activityLog->changed_fields) > 0)
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                                Changed Fields
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($activityLog->changed_fields as $field)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                                        {{ str_replace('_', ' ', ucfirst($field)) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Old and New Values -->
                    @if($activityLog->old_values || $activityLog->new_values)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @if($activityLog->old_values)
                                <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-300 uppercase tracking-wider mb-3">
                                        @if($activityLog->action === 'deleted')
                                            Deleted Values
                                        @else
                                            Previous Values
                                        @endif
                                    </h3>
                                    <div class="space-y-2 max-h-96 overflow-y-auto">
                                        @foreach($activityLog->old_values as $key => $value)
                                            <div class="flex justify-between py-1 border-b border-red-100 dark:border-red-800 last:border-0">
                                                <span class="text-sm text-red-600 dark:text-red-400">
                                                    {{ str_replace('_', ' ', ucfirst($key)) }}:
                                                </span>
                                                <span class="text-sm font-medium text-red-800 dark:text-red-200 ml-2 text-right break-all max-w-xs">
                                                    @if(is_array($value))
                                                        {{ json_encode($value) }}
                                                    @elseif(is_bool($value))
                                                        {{ $value ? 'Yes' : 'No' }}
                                                    @elseif(is_null($value))
                                                        <em class="text-gray-400">null</em>
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($activityLog->new_values)
                                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                    <h3 class="text-sm font-medium text-green-800 dark:text-green-300 uppercase tracking-wider mb-3">
                                        @if($activityLog->action === 'created')
                                            Created Values
                                        @else
                                            New Values
                                        @endif
                                    </h3>
                                    <div class="space-y-2 max-h-96 overflow-y-auto">
                                        @foreach($activityLog->new_values as $key => $value)
                                            <div class="flex justify-between py-1 border-b border-green-100 dark:border-green-800 last:border-0">
                                                <span class="text-sm text-green-600 dark:text-green-400">
                                                    {{ str_replace('_', ' ', ucfirst($key)) }}:
                                                </span>
                                                <span class="text-sm font-medium text-green-800 dark:text-green-200 ml-2 text-right break-all max-w-xs">
                                                    @if(is_array($value))
                                                        {{ json_encode($value) }}
                                                    @elseif(is_bool($value))
                                                        {{ $value ? 'Yes' : 'No' }}
                                                    @elseif(is_null($value))
                                                        <em class="text-gray-400">null</em>
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
