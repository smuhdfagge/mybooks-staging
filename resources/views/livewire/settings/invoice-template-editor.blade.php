<div class="flex flex-col lg:flex-row gap-6" x-data="invoiceTemplateEditor()" x-init="init()">
    {{-- Left Panel: Settings --}}
    <div class="w-full lg:w-96 flex-shrink-0">
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden sticky top-6">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Template Settings</h3>
            </div>

            <div class="p-4 space-y-6 max-h-[calc(100vh-200px)] overflow-y-auto">
                {{-- Template Name --}}
                <div>
                    <label for="name" class="form-label">Template Name</label>
                    <input id="name" type="text" wire:model.live="name"
                        class="form-control text-sm">
                    @error('name') <p id="name-error" class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Layout --}}
                <div>
                    <label for="layout" class="form-label">Layout Style</label>
                    <select id="layout" wire:model.live="layout"
                        class="form-control text-sm">
                        @foreach($layoutOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Colors Section --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                        Colors
                    </h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="primary_color" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Primary</label>
                            <div class="flex items-center gap-2">
                                <input id="primary_color" type="color" wire:model.live="primary_color" class="h-8 w-8 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                                <input aria-label="Primary color" type="text" wire:model.live="primary_color" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs" maxlength="7">
                            </div>
                        </div>
                        <div>
                            <label for="secondary_color" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Secondary</label>
                            <div class="flex items-center gap-2">
                                <input id="secondary_color" type="color" wire:model.live="secondary_color" class="h-8 w-8 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                                <input aria-label="Secondary color" type="text" wire:model.live="secondary_color" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs" maxlength="7">
                            </div>
                        </div>
                        <div>
                            <label for="accent_color" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Accent</label>
                            <div class="flex items-center gap-2">
                                <input id="accent_color" type="color" wire:model.live="accent_color" class="h-8 w-8 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                                <input aria-label="Accent color" type="text" wire:model.live="accent_color" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs" maxlength="7">
                            </div>
                        </div>
                        <div>
                            <label for="header_bg_color" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Header BG</label>
                            <div class="flex items-center gap-2">
                                <input id="header_bg_color" type="color" wire:model.live="header_bg_color" class="h-8 w-8 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                                <input aria-label="Header bg color" type="text" wire:model.live="header_bg_color" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs" maxlength="7">
                            </div>
                        </div>
                        <div>
                            <label for="header_text_color" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Header Text</label>
                            <div class="flex items-center gap-2">
                                <input id="header_text_color" type="color" wire:model.live="header_text_color" class="h-8 w-8 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                                <input aria-label="Header text color" type="text" wire:model.live="header_text_color" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs" maxlength="7">
                            </div>
                        </div>
                        <div>
                            <label for="table_header_bg" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Table Header</label>
                            <div class="flex items-center gap-2">
                                <input id="table_header_bg" type="color" wire:model.live="table_header_bg" class="h-8 w-8 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                                <input aria-label="Table header bg" type="text" wire:model.live="table_header_bg" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs" maxlength="7">
                            </div>
                        </div>
                        <div>
                            <label for="footer_bg_color" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Footer BG</label>
                            <div class="flex items-center gap-2">
                                <input id="footer_bg_color" type="color" wire:model.live="footer_bg_color" class="h-8 w-8 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                                <input aria-label="Footer bg color" type="text" wire:model.live="footer_bg_color" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs" maxlength="7">
                            </div>
                        </div>
                        <div>
                            <label for="footer_text_color" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Footer Text</label>
                            <div class="flex items-center gap-2">
                                <input id="footer_text_color" type="color" wire:model.live="footer_text_color" class="h-8 w-8 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                                <input aria-label="Footer text color" type="text" wire:model.live="footer_text_color" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs" maxlength="7">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Typography --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/></svg>
                        Typography
                    </h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="font_family" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Font Family</label>
                            <select id="font_family" wire:model.live="font_family"
                                class="form-control text-xs">
                                @foreach($fontOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="font_size" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Font Size (px)</label>
                            <input id="font_size" type="number" wire:model.live="font_size" min="8" max="20"
                                class="form-control text-xs">
                        </div>
                    </div>
                </div>

                {{-- Border --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">Header Border</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="border_style" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Style</label>
                            <select id="border_style" wire:model.live="border_style"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs">
                                <option value="solid">Solid</option>
                                <option value="dashed">Dashed</option>
                                <option value="dotted">Dotted</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                        <div>
                            <label for="border_width" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Width (px)</label>
                            <input id="border_width" type="number" wire:model.live="border_width" min="0" max="10"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs">
                        </div>
                    </div>
                </div>

                {{-- Visibility Toggles --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Display Options
                    </h4>
                    <div class="space-y-2">
                        @foreach([
                            'show_logo' => 'Company Logo',
                            'show_status_badge' => 'Status Badge',
                            'show_tax_column' => 'Tax Column',
                            'show_payment_info' => 'Payment Info',
                            'show_notes' => 'Notes Section',
                            'show_terms' => 'Terms Section',
                            'show_footer' => 'Footer',
                        ] as $prop => $label)
                        <label class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $label }}</span>
                            <div class="relative">
                                <input type="checkbox" wire:model.live="{{ $prop }}" class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-brand-300 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-500 peer-checked:bg-brand-600 cursor-pointer"></div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Footer Text --}}
                @if($show_footer)
                <div>
                    <label for="footer_text" class="form-label">Footer Message</label>
                    <input id="footer_text" type="text" wire:model.live="footer_text"
                        class="form-control text-sm">
                </div>
                @endif

                {{-- Actions --}}
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-2">
                    <button wire:click="save" wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 disabled:opacity-50">
                        <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="save">Save Template</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </button>

                    @if($template && !$template->is_default)
                    <button wire:click="setAsDefault" wire:loading.attr="disabled"
                        class="btn-secondary w-full">
                        Set as Default
                    </button>
                    @elseif($template && $template->is_default)
                    <div class="text-center">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">
                            <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Default Template
                        </span>
                    </div>
                    @endif

                    <button wire:click="resetToDefaults" type="button"
                        class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                        Reset to Defaults
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Right Panel: Live Preview --}}
    <div class="flex-1 min-w-0">
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Live Preview</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">Changes preview in real-time</span>
            </div>
            <div class="p-4 bg-gray-100 dark:bg-gray-900">
                <div class="mx-auto" style="max-width: 800px; transform-origin: top center;"
                     x-ref="previewContainer">
                    @include('invoices.templates.preview', ['settings' => $this->getSettingsArray()])
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    Alpine.data('invoiceTemplateEditor', () => ({
        init() {
            // The preview updates automatically via Livewire re-render
        }
    }));
</script>
@endscript
