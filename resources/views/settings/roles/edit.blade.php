<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="(($isCustomizing ?? false) ? 'Customise role: ' : 'Edit role: ').$role->name" :description="($isCustomizing ?? false) ? 'Makes your own copy of this built-in role, with the permissions you choose.' : 'Choose what people with this role can see and do.'" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if($isCustomizing ?? false)
                <div class="mb-4 bg-brand-50 dark:bg-brand-900/50 border border-brand-200 dark:border-brand-700 rounded-lg p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm text-brand-700 dark:text-brand-300">
                                You are customizing a system role. This will create a new role specific to your organization with your selected permissions.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('settings.roles.update', $role) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <div class="mb-6">
                        <label for="name" class="form-label">Role Name <span class="text-red-600 dark:text-red-300">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $role->name) }}" required
                            class="w-full max-w-md rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 @error('name') border-red-500 @enderror" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                        @error('name')
                            <p id="name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @if($isCustomizing ?? false)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">You can keep the same name or give it a custom name for your organization.</p>
                        @endif
                    </div>

                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Permissions</label>
                            <button type="button" id="toggle-all" class="text-sm text-brand-600 dark:text-brand-300 hover:text-brand-800 dark:hover:text-brand-300">
                                Select All
                            </button>
                        </div>
                        
                        <x-table caption="Permissions">
<x-slot name="head">
                                        <x-table.th>Resource</x-table.th>
                                        <x-table.th class="text-center w-20">View</x-table.th>
                                        <x-table.th class="text-center w-20">Create</x-table.th>
                                        <x-table.th class="text-center w-20">Edit</x-table.th>
                                        <x-table.th class="text-center w-20">Delete</x-table.th>
                                        <x-table.th class="text-center w-20">Other</x-table.th>
                                        <x-table.th class="text-center w-20">All</x-table.th>
</x-slot>

                                    @php
                                        $standardActions = ['view', 'create', 'edit', 'delete'];
                                        $oldPermissions = old('permissions', $rolePermissions);
                                    @endphp
                                    @foreach($permissions as $resource => $resourcePermissions)
                                        @continue($resource === 'dashboard-widgets')
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
                                        <tr>
                                            <td class="font-medium">{{ $resourceLabel }}</td>
                                            @foreach($standardActions as $action)
                                                <td class="text-center">
                                                    @if(isset($permissionMap[$action]))
                                                        <input type="checkbox" name="permissions[]" value="{{ $permissionMap[$action] }}"
                                                            class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 permission-checkbox dark:text-brand-300"
                                                            aria-label="{{ $resourceLabel }}: {{ ucwords(str_replace('-', ' ', $action)) }}" data-resource="{{ $resource }}"
                                                            {{ in_array($permissionMap[$action], $oldPermissions) ? 'checked' : '' }}>
                                                    @else
                                                        <span class="tbl-zero" aria-hidden="true">—</span><span class="sr-only">Not used</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="text-center">
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
                                                    <span class="tbl-zero" aria-hidden="true">—</span><span class="sr-only">Not used</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 row-toggle dark:text-brand-300"
                                                    data-resource="{{ $resource }}" title="Toggle all for {{ $resourceLabel }}" aria-label="Toggle all for {{ $resourceLabel }}">
                                            </td>
                                        </tr>
                                    @endforeach
</x-table>
                        @include('settings.roles._dashboard-widgets', ['selected' => $oldPermissions])
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('settings.roles') }}" class="btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            @if($isCustomizing ?? false)
                                Create Custom Role
                            @else
                                Update Role
                            @endif
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
        let allSelected = document.querySelectorAll('.permission-checkbox:checked').length === document.querySelectorAll('.permission-checkbox').length;
        toggleAllBtn.textContent = allSelected ? 'Deselect All' : 'Select All';
        
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
