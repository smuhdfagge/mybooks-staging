{{--
    "Email statements" on the customers / suppliers list (session 10).
    $side ('customers' or 'suppliers'), $selected (ticked ids from the table).
    $noButton: true when the list puts its own "Email statements" button in
    the toolbar (tables plan); the button just needs data-open-modal="bulk-statements".
--}}
@php
    $isCustomer = $side === \App\Services\Statements\Subledger::CUSTOMERS;
    $who = $isCustomer ? 'customers' : 'suppliers';
    $mailer = app(\App\Services\Statements\StatementMailer::class);
    $count = count($selected);
@endphp
@unless ($noButton ?? false)
<div class="mb-4 flex flex-wrap items-center justify-end gap-2">
    <button type="button" data-open-modal="bulk-statements"
        class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
        <svg class="w-4 h-4 mr-2" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        Email statements
    </button>
</div>
@endunless

<x-modal name="bulk-statements" title="Email statements" maxWidth="lg">
    <form method="POST" action="{{ route($isCustomer ? 'customers.statements.send' : 'vendors.statements.send') }}" class="p-6 space-y-4"
        x-data="{ type: 'activity', scope: @js($count > 0 ? 'selected' : 'balance') }">
        @csrf
        @foreach($selected as $id)
            <input type="hidden" name="ids[]" value="{{ $id }}">
        @endforeach
        <fieldset class="space-y-2">
            <legend class="form-label">Send to</legend>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="radio" name="scope" value="selected" x-model="scope" @disabled($count === 0)>
                The {{ $count }} {{ $count === 1 ? \Illuminate\Support\Str::singular($who) : $who }} ticked in the list
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="radio" name="scope" value="balance" x-model="scope">
                All {{ $who }} with a balance
            </label>
            <p class="form-help">Anyone without an email address is skipped; you'll see who.</p>
        </fieldset>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label for="bulk-type" class="form-label">Statement type</label>
                <select id="bulk-type" name="type" x-model="type" class="form-control">
                    <option value="activity">Activity</option>
                    <option value="open">Open items</option>
                </select>
            </div>
            <div x-show="type === 'activity'">
                <label for="bulk-from" class="form-label">From</label>
                <input type="date" id="bulk-from" name="from" value="{{ now()->startOfMonth()->toDateString() }}" class="form-control">
            </div>
            <div>
                <label for="bulk-to" class="form-label"><span x-text="type === 'activity' ? 'To' : 'As at'">To</span></label>
                <input type="date" id="bulk-to" name="to" value="{{ now()->toDateString() }}" class="form-control">
            </div>
        </div>
        <div><x-field name="subject" id="bulk-subject" label="Subject" :value="$mailer->defaultSubject($side)" required maxlength="200" /></div>
        <div><x-field name="message" id="bulk-message" label="Message" type="textarea" rows="5" :value="$mailer->defaultMessage($side)" required
            help="{name}, {period}, {balance} and {business} are filled in for each one." /></div>
        <div class="flex justify-end gap-3">
            <button type="button" data-close-modal="bulk-statements" class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-xs font-semibold uppercase tracking-widest">Cancel</button>
            <button type="submit" class="btn-primary">Send statements</button>
        </div>
    </form>
</x-modal>
