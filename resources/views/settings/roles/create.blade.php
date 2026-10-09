<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Create Role
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Define a new role with specific permissions</p>
            </div>
            <a href="{{ route('settings.roles') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('settings.roles.store') }}" method="POST" class="p-6">
                    @csrf

                    <div class="mb-6">
                        <label for="name" class="form-label">Role Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                            placeholder="e.g., Manager, Accountant, Sales Rep"
                            class="w-full max-w-md rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 @error('name') border-red-500 @enderror" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                        @error('name')
                            <p id="name-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Permissions</label>
                            <button type="button" id="toggle-all" class="text-sm text-brand-600 dark:text-brand-300 hover:text-brand-800 dark:hover:text-brand-300">
                                Select All
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Resource</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">View</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">Create</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">Edit</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">Delete</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">Other</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">All</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @php
                                        $standardActions = ['view', 'create', 'edit', 'delete'];
                                        $oldPermissions = old('permissions', []);
                                    @endphp
                                    @foreach($permissions as $resource => $resourcePermissions)
                                        @php
                                            $permissionMap = [];
                                            $otherPermissions = [];
                                            foreach ($resourcePermissions as $perm) {
                                                $parts = explode(' ', $perm->name, 2);
                                                $action = $parts[0];
                                                if (in_array($action, $standardActions)) {
                                                    $permissionMap[$action] = $perm->name;
                                                } else {
                                                    $otherPermissions[] = $perm;
                                                }
                                            }
                                            $resourceLabel = ucwords(str_replace('-', ' ', $resource));
                                        @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $resourceLabel }}</td>
                                            @foreach($standardActions as $action)
                                                <td class="px-4 py-3 text-center">
                                                    @if(isset($permissionMap[$action]))
                                                        <input type="checkbox" name="permissions[]" value="{{ $permissionMap[$action] }}"
                                                            class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 permission-checkbox dark:text-brand-300"
                                                            data-resource="{{ $resource }}"
                                                            {{ in_array($permissionMap[$action], $oldPermissions) ? 'checked' : '' }}>
                                                    @else
                                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="px-4 py-3 text-center">
                                                @if(count($otherPermissions) > 0)
                                                    <div class="flex flex-wrap justify-center gap-1">
                                                        @foreach($otherPermissions as $otherPerm)
                                                            @php
                                                                $otherAction = explode(' ', $otherPerm->name, 2)[0];
                                                            @endphp
                                                            <label class="inline-flex items-center text-xs" title="{{ $otherPerm->name }}">
                                                                <input type="checkbox" name="permissions[]" value="{{ $otherPerm->name }}"
                                                                    class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 permission-checkbox dark:text-brand-300"
                                                                    data-resource="{{ $resource }}"
                                                                    {{ in_array($otherPerm->name, $oldPermissions) ? 'checked' : '' }}>
                                                                <span class="ml-1 text-gray-600 dark:text-gray-400">{{ ucwords(str_replace('-', ' ', $otherAction)) }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 row-toggle dark:text-brand-300"
                                                    data-resource="{{ $resource }}" title="Toggle all for {{ $resourceLabel }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('settings.roles') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Create Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        // Row toggle (All checkbox for each resource)
        document.querySelectorAll('.row-toggle').forEach(function(rowToggle) {
            const resource = rowToggle.dataset.resource;
            const permissionCheckboxes = document.querySelectorAll(`.permission-checkbox[data-resource="${resource}"]`);
            
            // Set initial state
            updateRowToggleState(rowToggle, permissionCheckboxes);
            
            rowToggle.addEventListener('change', function() {
                permissionCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = rowToggle.checked;
                });
            });
        });

        // Update row toggle when individual permissions change
        document.querySelectorAll('.permission-checkbox').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const resource = this.dataset.resource;
                const rowToggle = document.querySelector(`.row-toggle[data-resource="${resource}"]`);
                const permissionCheckboxes = document.querySelectorAll(`.permission-checkbox[data-resource="${resource}"]`);
                updateRowToggleState(rowToggle, permissionCheckboxes);
            });
        });

        function updateRowToggleState(rowToggle, permissionCheckboxes) {
            const allChecked = Array.from(permissionCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(permissionCheckboxes).some(cb => cb.checked);
            rowToggle.checked = allChecked;
            rowToggle.indeterminate = someChecked && !allChecked;
        }

        // Toggle all permissions
        const toggleAllBtn = document.getElementById('toggle-all');
        let allSelected = false;
        
        toggleAllBtn.addEventListener('click', function() {
            allSelected = !allSelected;
            document.querySelectorAll('.permission-checkbox').forEach(function(checkbox) {
                checkbox.checked = allSelected;
            });
            document.querySelectorAll('.row-toggle').forEach(function(toggle) {
                toggle.checked = allSelected;
                toggle.indeterminate = false;
            });
            toggleAllBtn.textContent = allSelected ? 'Deselect All' : 'Select All';
        });
    </script>
    @endpush
</x-app-layout>
