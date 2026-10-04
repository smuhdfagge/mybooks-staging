{{-- Lock dates card and history (session 11). Needs $lockDates and $lockHistory. --}}
@php
    $staff = $lockDates['staff'] ?? null;
    $all = $lockDates['all_users'] ?? null;
    $canManage = auth()->user()->can('manage lock-dates');
@endphp
<x-card class="mb-6" id="lock-dates">
    <div class="p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Lock dates</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-2xl">
                    Stop changes to finished months, for example once the accounts or a tax return are done.
                    Nothing dated on or before a lock date can be added, edited, deleted or voided. Reports still show everything.
                </p>
            </div>
            <div class="text-sm sm:text-right shrink-0">
                @if($staff)
                    <p class="font-medium text-gray-900 dark:text-gray-100">Books locked up to {{ $staff->format('j M Y') }}</p>
                    @if($all)
                        <p class="text-gray-500 dark:text-gray-400">For everyone up to {{ $all->format('j M Y') }}</p>
                    @endif
                @else
                    <p class="text-gray-500 dark:text-gray-400">No lock date set</p>
                @endif
            </div>
        </div>

        @if($canManage)
            <form method="POST" action="{{ route('lock-dates.update') }}" class="space-y-4"
                  x-data="{
                      oldStaff: @js($staff?->toDateString() ?? ''), oldAll: @js($all?->toDateString() ?? ''),
                      staff: @js(old('staff_lock_date', $staff?->toDateString() ?? '')), all: @js(old('all_users_lock_date', $all?->toDateString() ?? '')),
                      back(o, n) { return o !== '' && (n === '' || n < o); },
                      get movingBack() { return this.back(this.oldStaff, this.staff) || this.back(this.oldAll, this.all); }
                  }">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-field name="staff_lock_date" label="Staff lock date" type="date" x-model="staff" :max="now()->toDateString()"
                                 help="Staff can't change anything on or before this date. Admins and accountants see a warning but can still save." />
                    </div>
                    <div>
                        <x-field name="all_users_lock_date" label="Lock date for everyone" type="date" x-model="all" :max="now()->toDateString()"
                                 help="Nobody can change anything on or before this date, admins included. It must be on or before the staff lock date." />
                    </div>
                </div>
                <div x-show="movingBack" x-cloak>
                    <x-field name="reason" label="Reason for opening the books again" type="textarea" rows="2" :value="old('reason')"
                             x-bind:required="movingBack"
                             help="Moving a lock date back or removing it needs a reason. It is kept in the history below." />
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary">Save lock dates</button>
                </div>
            </form>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Only an admin can change the lock dates.</p>
        @endif

        <div>
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">History</h4>
            @if($lockHistory->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No changes yet.</p>
            @else
                {{-- Phones: one block per change, so the description gets the full width. --}}
                <ul class="sm:hidden divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    @foreach($lockHistory as $change)
                        <li class="py-3">
                            <p class="text-gray-900 dark:text-gray-100">{{ $change->description }}</p>
                            @if($change->reason)
                                <p class="text-gray-700 dark:text-gray-300 mt-0.5">Reason: {{ $change->reason }}</p>
                            @endif
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $change->created_at->format('j M Y, g:i a') }} · {{ $change->user?->name ?? 'System' }}</p>
                        </li>
                    @endforeach
                </ul>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">When</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">What</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Reason</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($lockHistory as $change)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $change->created_at->format('j M Y, g:i a') }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $change->description }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $change->reason ?? '-' }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $change->user?->name ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-card>
