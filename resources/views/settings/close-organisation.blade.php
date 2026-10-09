<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Close Organisation') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Success and error messages are shown by the layout (U9). --}}
            @if ($tenant->isClosing())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 border-l-4 border-red-500">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $tenant->name }} is closing</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Closing was requested on {{ $tenant->closure_requested_at?->format('j F Y') }}.
                        All of this business's data (books, customers, employees, users and uploaded files) will be erased on
                        <strong>{{ $tenant->closure_purge_at?->format('j F Y') }}</strong>. Until then everything keeps working,
                        so download any reports or exports you need to keep.
                    </p>
                    <form method="POST" action="{{ route('settings.close-organisation.cancel') }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700">
                            Cancel closing
                        </button>
                    </form>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Close {{ $tenant->name }} and erase its data</h3>
                    <div class="mt-2 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <p>Under the Nigeria Data Protection Act you can ask us to erase your business's data. When you close the business:</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Nothing is erased for {{ \App\Models\Tenant::CLOSURE_GRACE_DAYS }} days. The business keeps working, and you can cancel at any time in that period.</li>
                            <li>After {{ \App\Models\Tenant::CLOSURE_GRACE_DAYS }} days all of its data is erased for good: books, customers, vendors, employees, payroll, users and uploaded files.</li>
                            <li>Copies in our backups are removed as those backups expire ({{ config('mybooks.backup.keep_days') }} days).</li>
                            <li>You may need to keep accounting and payroll records for tax purposes, so export what you need first.</li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('settings.close-organisation.store') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label for="confirm_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type the business name, <strong>{{ $tenant->name }}</strong>, to confirm</label>
                            <input type="text" name="confirm_name" id="confirm_name" value="{{ old('confirm_name') }}" autocomplete="off" required
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm">
                            @error('confirm_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Your password</label>
                            <input type="password" name="password" id="password" autocomplete="current-password" required
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm">
                            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason (optional)</label>
                            <textarea name="reason" id="reason" rows="2"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm">{{ old('reason') }}</textarea>
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
                            Close organisation
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
