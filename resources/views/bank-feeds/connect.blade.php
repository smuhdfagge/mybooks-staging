<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Connect a bank account</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @include('bank-feeds._not-set-up')

            <x-card class="p-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    You will pick your bank and log in on Mono's secure page (Mono is licensed to read bank data in Nigeria). MyBooks never sees your login details.
                    Only naira accounts can be linked. {{ $limitSummary }}
                </p>

                <form method="POST" action="{{ route('bank-feeds.start') }}" class="mt-6 space-y-5" x-data="{ mode: '{{ $selected || $banks->isNotEmpty() ? 'existing' : 'new' }}' }">
                    @csrf
                    <fieldset>
                        <legend class="form-label">Which MyBooks account should it fill?</legend>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                                <input type="radio" x-model="mode" value="existing" @disabled($banks->isEmpty())> An account I already have in MyBooks
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                                <input type="radio" x-model="mode" value="new"> A new account (named below)
                            </label>
                        </div>
                    </fieldset>

                    <div x-show="mode === 'existing'">
                        <x-field name="bank_id" label="Bank account" type="select" x-bind:disabled="mode !== 'existing'">
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}" @selected((int) old('bank_id', $selected) === $bank->id)>{{ $bank->name }}{{ $bank->bank_name ? ' ('.$bank->bank_name.')' : '' }}</option>
                            @endforeach
                        </x-field>
                        <p class="form-help">Accounts that already have a feed are not listed. Check its opening balance and date are right, so the balance in MyBooks matches the bank.</p>
                    </div>
                    <div x-show="mode === 'new'" x-cloak>
                        <x-field name="new_bank_name" label="Name for the new account" :value="old('new_bank_name')" maxlength="100" placeholder="e.g. GTBank current" x-bind:disabled="mode !== 'new'" />
                        <p class="form-help">The new account starts at 0.00. Set its opening balance afterwards if the account had money before the dates read.</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn-primary" @disabled(! $live || $limitReached)>Continue to my bank</button>
                        <a href="{{ route('bank-feeds.index') }}" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
