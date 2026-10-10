{{--
    Dashboard cards for a role (dashboard upgrade). One tick per card, named
    as on the dashboard; the list and descriptions are in config/dashboard.php.
    $selected: permission names already ticked.
--}}
@php
    $widgets = config('dashboard.widgets');
    $existing = \Spatie\Permission\Models\Permission::where('name', 'like', '% dashboard-widgets')->pluck('name')->all();
@endphp
<fieldset class="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
    <legend class="px-1 text-sm font-medium text-gray-800 dark:text-gray-200">Dashboard cards</legend>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600 dark:text-gray-400">Choose which cards people with this role see on their dashboard. They also need "View" on Dashboard above.</p>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" class="row-toggle rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-gray-600 dark:text-brand-300" data-resource="dashboard-widgets">
            All cards
        </label>
    </div>
    <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
        @foreach ($widgets as $key => $widget)
            @php $name = "{$key} dashboard-widgets"; @endphp
            @continue(! in_array($name, $existing, true))
            <label class="flex items-start gap-3 text-sm" data-dashboard-widget="{{ $key }}">
                <input type="checkbox" name="permissions[]" value="{{ $name }}" data-resource="dashboard-widgets"
                    class="permission-checkbox mt-0.5 rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-gray-600 dark:text-brand-300"
                    @checked(in_array($name, $selected, true))>
                <span>
                    <span class="block font-medium text-gray-900 dark:text-gray-100">{{ $widget['label'] }}</span>
                    <span class="block text-gray-600 dark:text-gray-400">{{ $widget['help'] }}</span>
                </span>
            </label>
        @endforeach
    </div>
</fieldset>
