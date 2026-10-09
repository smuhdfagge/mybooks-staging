{{-- Warehouse fields (session 12). --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div><x-field name="name" label="Name" :value="old('name', $warehouse->name)" required maxlength="255" placeholder="e.g. Kano shop" /></div>
    <div><x-field name="code" label="Short code" :value="old('code', $warehouse->code)" required maxlength="20" placeholder="e.g. KANO" help="Shown on lists. Must be different for each warehouse." /></div>
    <div class="sm:col-span-2"><x-field name="address" label="Address" type="textarea" rows="2" :value="old('address', $warehouse->address)" /></div>
    <div><x-field name="contact_person" label="Contact person" :value="old('contact_person', $warehouse->contact_person)" /></div>
    <div><x-field name="phone" label="Phone" :value="old('phone', $warehouse->phone)" /></div>
    <div class="sm:col-span-2 sm:w-1/2"><x-field name="email" label="Email" type="email" :value="old('email', $warehouse->email)" /></div>
</div>

<div class="mt-4 space-y-3">
    <label class="flex items-start gap-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $warehouse->is_active ?? true))
            class="mt-1 rounded border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
        <span>
            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">In use</span>
            <span class="block form-help">Untick to stop choosing this warehouse on new documents. Only possible when it holds no stock.</span>
        </span>
    </label>
    @error('is_active')<p class="form-error">{{ $message }}</p>@enderror

    <label class="flex items-start gap-3">
        <input type="hidden" name="is_default" value="0">
        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $warehouse->is_default))
            class="mt-1 rounded border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
        <span>
            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Default warehouse</span>
            <span class="block form-help">Used whenever a document doesn't say which warehouse. There is always exactly one.</span>
        </span>
    </label>
    @error('is_default')<p class="form-error">{{ $message }}</p>@enderror
</div>
