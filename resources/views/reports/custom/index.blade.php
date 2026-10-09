<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Custom Reports') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Build and manage your custom reports</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Reports
                </a>
                <a href="{{ route('reports.custom.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    New Report
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">

        <!-- My Reports -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">My Reports</h3>
                
                @if($myReports->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($myReports as $report)
                            <div class="relative bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <!-- Favorite Badge -->
                                @if($report->is_favorite)
                                    <div class="absolute top-2 right-2">
                                        <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                    </div>
                                @endif
                                
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">{{ $report->name }}</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $report->data_source)) }}</p>
                                    </div>
                                </div>
                                
                                @if($report->description)
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-3 line-clamp-2">{{ $report->description }}</p>
                                @endif
                                
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-3">
                                    <span>{{ count($report->columns) }} columns</span>
                                    @if($report->last_run_at)
                                        <span>Last run: {{ $report->last_run_at->diffForHumans() }}</span>
                                    @endif
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-1">
                                        @if($report->is_public)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200">
                                                Shared
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <form action="{{ route('reports.custom.toggle-favorite', $report) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-gray-500 dark:text-gray-400 hover:text-yellow-500 transition-colors" title="{{ $report->is_favorite ? 'Remove from favorites' : 'Add to favorites' }}">
                                                <svg class="w-5 h-5" fill="{{ $report->is_favorite ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                                </svg>
                                            </button>
                                        </form>
                                        
                                        <a href="{{ route('reports.custom.run', $report) }}" class="inline-flex items-center px-3 py-1.5 bg-green-700 text-white text-xs font-medium rounded hover:bg-green-700 transition-colors">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Run
                                        </a>
                                        
                                        <a href="{{ route('reports.custom.edit', $report) }}" class="text-gray-500 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-300" title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        
                                        <form action="{{ route('reports.custom.destroy', $report) }}" method="POST" class="inline" data-confirm="Are you sure you want to delete this report?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400" title="Delete">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No custom reports</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first custom report.</p>
                        <div class="mt-6">
                            <a href="{{ route('reports.custom.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-brand-600 hover:bg-brand-700">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Create Report
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Shared Reports -->
        @if($sharedReports->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <svg class="w-5 h-5 inline-block mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Shared with Me
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($sharedReports as $report)
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow border-l-4 border-brand-500">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">{{ $report->name }}</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $report->data_source)) }}</p>
                                    </div>
                                </div>
                                
                                @if($report->description)
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-3 line-clamp-2">{{ $report->description }}</p>
                                @endif
                                
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-3">
                                    <span>By: {{ $report->creator->name ?? 'Unknown' }}</span>
                                    <span>{{ count($report->columns) }} columns</span>
                                </div>

                                <div class="flex justify-end">
                                    <a href="{{ route('reports.custom.run', $report) }}" class="inline-flex items-center px-3 py-1.5 bg-green-700 text-white text-xs font-medium rounded hover:bg-green-700 transition-colors">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Run
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
