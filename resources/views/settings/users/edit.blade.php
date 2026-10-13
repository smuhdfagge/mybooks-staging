<x-app-layout>
    <x-slot name="header">
        <x-table.page-header description="Update user information and roles">
            <x-slot name="title">Edit user {{ $user->name }}</x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('settings.users.update', $user) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Name -->
                        <div>
                            <x-field name="name" label="Name" :value="old('name', $user->name)" required />
                            @error('name')
                                <p id="name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <x-field name="email" label="Email" type="email" :value="old('email', $user->email)" required />
                            @error('email')
                                <p id="email-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div>
                            <x-field name="phone" label="Phone" :value="old('phone', $user->phone)" />
                            @error('phone')
                                <p id="phone-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div>
                            <label for="is_active" class="form-label">Status</label>
                            <select name="is_active" id="is_active" {{ $isSelf ? 'disabled' : '' }}
                                class="form-control">
                                <option value="1" {{ old('is_active', $user->is_active ?? true) ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ !old('is_active', $user->is_active ?? true) ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>

                        <!-- Password -->
                        <div>
                            <x-field name="password" label="New Password" type="password" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank to keep current password</p>
                            @error('password')
                                <p id="password-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <x-field name="password_confirmation" label="Confirm Password" type="password" />
                        </div>
                    </div>

                    <!-- Roles -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Roles</label>
                        @if($isSelf)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">You can't change your own roles or status. Ask another administrator.</p>
                        @endif
                        <div class="flex flex-wrap gap-4">
                            @foreach($roles as $role)
                                <label class="flex items-center">
                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                                        class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:text-brand-300"
                                        {{ in_array($role->id, array_map('intval', old('roles', $user->roles->pluck('id')->toArray()))) ? 'checked' : '' }}
                                        {{ $isSelf ? 'disabled' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('settings.users') }}" class="btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update user
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
