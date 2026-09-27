{{-- Auto-save form data to localStorage using Alpine.js --}}
{{-- Usage: Add x-data="formAutoSave('unique-form-key')" to your form, or wrap the form --}}
{{-- The component saves all form inputs to localStorage on change and restores them on load --}}
@props(['formKey'])

<div x-data="formAutoSave('{{ $formKey }}')" x-init="restore()" @input.debounce.1000ms="save()" class="relative">
    {{-- Auto-save indicator --}}
    <div x-show="showSaved" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed bottom-4 left-4 z-50 flex items-center gap-2 px-3 py-2 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/50 rounded-lg shadow-sm border border-green-200 dark:border-green-800"
         x-cloak>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Draft auto-saved
    </div>

    {{-- Restore banner --}}
    <div x-show="hasRestored" 
         class="mb-4 rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 p-3"
         x-cloak>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm text-blue-700 dark:text-blue-300">Unsaved draft restored from your last session.</span>
            </div>
            <button type="button" @click="clearDraft(); hasRestored = false" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                Discard draft
            </button>
        </div>
    </div>

    {{ $slot }}
</div>

@once
@push('scripts')
<script nonce="{{ app('csp-nonce') }}">
    document.addEventListener('alpine:init', () => {
        Alpine.data('formAutoSave', (formKey) => ({
            showSaved: false,
            hasRestored: false,
            storageKey: 'mybooks_draft_' + formKey,

            save() {
                const form = this.$el.querySelector('form') || this.$el.closest('form');
                if (!form) return;

                const data = {};
                const formData = new FormData(form);
                for (const [key, value] of formData.entries()) {
                    if (key === '_token' || key === '_method') continue;
                    data[key] = value;
                }

                // Also save text inputs, selects, textareas directly
                form.querySelectorAll('input:not([type="hidden"]):not([type="file"]), select, textarea').forEach(el => {
                    if (el.name && el.type !== 'password') {
                        if (el.type === 'checkbox') {
                            data[el.name] = el.checked;
                        } else {
                            data[el.name] = el.value;
                        }
                    }
                });

                try {
                    localStorage.setItem(this.storageKey, JSON.stringify(data));
                    this.showSaved = true;
                    setTimeout(() => { this.showSaved = false; }, 2000);
                } catch (e) {
                    // localStorage full or inaccessible
                }
            },

            restore() {
                try {
                    const saved = localStorage.getItem(this.storageKey);
                    if (!saved) return;

                    const data = JSON.parse(saved);
                    const form = this.$el.querySelector('form') || this.$el.closest('form');
                    if (!form) return;

                    let restored = false;
                    Object.entries(data).forEach(([key, value]) => {
                        const el = form.querySelector(`[name="${key}"]`);
                        if (el && !el.disabled && el.type !== 'hidden') {
                            if (el.type === 'checkbox') {
                                el.checked = value === true || value === 'true';
                            } else {
                                el.value = value;
                                el.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                            restored = true;
                        }
                    });

                    if (restored) {
                        this.hasRestored = true;
                    }
                } catch (e) {
                    // corrupted data
                    localStorage.removeItem(this.storageKey);
                }
            },

            clearDraft() {
                localStorage.removeItem(this.storageKey);
            }
        }));
    });

    // Clear drafts on successful form submission
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.tagName === 'FORM') {
            const autoSaveEl = form.closest('[x-data*="formAutoSave"]') || form.querySelector('[x-data*="formAutoSave"]');
            if (autoSaveEl) {
                const keyMatch = autoSaveEl.getAttribute('x-data')?.match(/formAutoSave\('([^']+)'\)/);
                if (keyMatch) {
                    localStorage.removeItem('mybooks_draft_' + keyMatch[1]);
                }
            }
        }
    });
</script>
@endpush
@endonce
