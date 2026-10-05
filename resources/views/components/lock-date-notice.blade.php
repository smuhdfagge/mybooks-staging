{{--
    Lock date banner on transaction forms (session 11). Watches the form's
    date field and says when the date is on or before a lock date: blocked
    for staff (and for everyone behind the all-users lock), a warning for
    users who may override the staff lock. Nothing shows when no lock date
    is set.

    <x-lock-date-notice field="invoice_date" />
--}}
@props(['field'])

@php
    $lock = \App\Services\Accounting\LockDates::instance();
    $user = auth()->user();
    $dates = $user ? $lock->dates((int) $user->tenant_id) : ['staff' => null, 'all_users' => null];
    $override = \App\Services\Accounting\LockDates::canOverride($user);
@endphp

@if($dates['staff'] || $dates['all_users'])
    <div x-data="{
            field: @js($field), staff: @js($dates['staff']?->toDateString()), all: @js($dates['all_users']?->toDateString()),
            staffLabel: @js($dates['staff']?->format('j M Y')), allLabel: @js($dates['all_users']?->format('j M Y')),
            override: @js($override), date: '',
            init() {
                const input = (this.$el.closest('form') || document).querySelector(`[name='${this.field}']`) || document.querySelector(`[name='${this.field}']`);
                if (! input) return;
                const read = () => { this.date = input.value || ''; };
                read();
                input.addEventListener('input', read);
                input.addEventListener('change', read);
            },
            get state() {
                if (! this.date) return '';
                if (this.all && this.date <= this.all) return 'all';
                if (this.staff && this.date <= this.staff) return this.override ? 'warn' : 'staff';
                return '';
            },
         }"
         x-show="state !== ''" x-cloak role="status" data-lock-date-notice
         {{ $attributes->merge(['class' => 'mb-4']) }}>
        <div x-show="state === 'all'" class="rounded-md border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-700 p-3 text-sm text-red-800 dark:text-red-200">
            The books are locked for everyone up to <span x-text="allLabel"></span>. Pick a later date, or an admin must move the lock date back first.
        </div>
        @unless($override)
        <div x-show="state === 'staff'" class="rounded-md border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-700 p-3 text-sm text-red-800 dark:text-red-200">
            The books are locked up to <span x-text="staffLabel"></span>. Pick a later date, or ask an admin to change the lock date.
        </div>
        @else
        <div x-show="state === 'warn'" class="rounded-md border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 p-3 text-sm text-amber-800 dark:text-amber-200">
            This date is on or before the lock date (<span x-text="staffLabel"></span>). You can still save because you are allowed to override the lock; other staff cannot.
        </div>
        @endunless
    </div>
@endif
