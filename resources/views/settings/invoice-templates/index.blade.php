<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Invoice Templates') }}
            </h2>
            <a href="{{ route('settings.invoice-templates.create') }}"
                class="inline-flex items-center px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Template
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($templates as $template)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden group relative {{ $template->is_default ? 'ring-2 ring-brand-500' : '' }}">
                    {{-- Template Preview Thumbnail --}}
                    <div class="p-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700" style="height: 280px; overflow: hidden;">
                        <div style="transform: scale(0.35); transform-origin: top left; width: 286%; pointer-events: none;">
                            @include('invoices.templates.preview', ['settings' => $template->settings ?? \App\Models\InvoiceTemplate::getDefaultSettings()])
                        </div>
                    </div>

                    {{-- Template Info --}}
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $template->name }}</h3>
                            @if($template->is_default)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">
                                Default
                            </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            Layout: {{ ucfirst($template->settings['layout'] ?? 'classic') }}
                        </p>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('settings.invoice-templates.edit', $template) }}"
                                class="flex-1 inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-brand-700 dark:text-brand-300 bg-brand-50 dark:bg-brand-900/50 rounded-md hover:bg-brand-100 dark:hover:bg-brand-900/80 transition">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Edit
                            </a>
                            @if(!$template->is_default)
                            <form action="{{ route('settings.invoice-templates.set-default', $template) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/50 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/80 transition">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Set Default
                                </button>
                            </form>
                            <form action="{{ route('settings.invoice-templates.destroy', $template) }}" method="POST"
                                  data-confirm="Delete this template?">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center justify-center px-2 py-1.5 text-xs font-medium text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/50 rounded-md hover:bg-red-100 dark:hover:bg-red-900/80 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>

                    {{-- Color Swatches --}}
                    <div class="px-4 pb-3 flex items-center gap-1">
                        <div class="w-4 h-4 rounded-full border border-gray-200" style="background: {{ $template->settings['primary_color'] ?? '#1F4E79' }};"></div>
                        <div class="w-4 h-4 rounded-full border border-gray-200" style="background: {{ $template->settings['secondary_color'] ?? '#1F2937' }};"></div>
                        <div class="w-4 h-4 rounded-full border border-gray-200" style="background: {{ $template->settings['accent_color'] ?? '#059669' }};"></div>
                    </div>
                </div>
                @empty
                <div class="col-span-full text-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No templates</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first invoice template.</p>
                    <div class="mt-6">
                        <a href="{{ route('settings.invoice-templates.create') }}"
                            class="inline-flex items-center px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700">
                            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            New Template
                        </a>
                    </div>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
