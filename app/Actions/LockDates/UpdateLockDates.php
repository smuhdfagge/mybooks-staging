<?php

namespace App\Actions\LockDates;

use App\Models\LockDateChange;
use App\Models\Tenant;
use App\Services\Accounting\LockDates;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Set, move or clear a business's two lock dates (session 11).
 * - The all-users date can't be later than the staff date, and needs a
 *   staff date (it can be the same day).
 * - Neither can be in the future.
 * - Moving a date back (earlier, or clearing it) needs a reason.
 * - Every change is kept in the lock date history.
 */
class UpdateLockDates
{
    /**
     * @param  array{staff_lock_date?: ?string, all_users_lock_date?: ?string, reason?: ?string}  $data
     * @return array<int, LockDateChange> the history lines written
     */
    public function handle(Tenant $tenant, array $data, ?int $userId = null): array
    {
        $staff = $this->date($data['staff_lock_date'] ?? null);
        $all = $this->date($data['all_users_lock_date'] ?? null);
        $reason = trim((string) ($data['reason'] ?? ''));

        $errors = [];
        foreach (['staff_lock_date' => $staff, 'all_users_lock_date' => $all] as $field => $date) {
            if ($date && $date->gt(today())) {
                $errors[$field] = 'A lock date can\'t be in the future.';
            }
        }
        if ($all && ! $staff) {
            $errors['staff_lock_date'] = 'Set the staff lock date too. It can be the same day as the lock date for everyone.';
        } elseif ($all && $all->gt($staff)) {
            $errors['all_users_lock_date'] = 'The lock date for everyone must be on or before the staff lock date.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($tenant, $staff, $all, $reason, $userId) {
            $tenant = Tenant::lockForUpdate()->findOrFail($tenant->id);
            $old = [LockDates::STAFF => $tenant->staff_lock_date, LockDates::ALL_USERS => $tenant->all_users_lock_date];
            $new = [LockDates::STAFF => $staff, LockDates::ALL_USERS => $all];

            $movedBack = false;
            foreach ($new as $lock => $date) {
                if ($old[$lock] && (! $date || $date->lt($old[$lock]))) {
                    $movedBack = true;
                }
            }
            if ($movedBack && mb_strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'reason' => 'Say why the books are being opened again (at least 5 characters). It is kept in the history.',
                ]);
            }

            $changes = [];
            foreach ($new as $lock => $date) {
                $before = $old[$lock];
                if ($before?->toDateString() === $date?->toDateString()) {
                    continue;
                }
                $label = $lock === LockDates::STAFF ? 'Staff lock date' : 'Lock date for everyone';
                $back = $before && (! $date || $date->lt($before));
                $description = match (true) {
                    ! $date => "{$label} removed (was {$before->format('j M Y')})",
                    ! $before => "{$label} set to {$date->format('j M Y')}",
                    $back => "{$label} moved back from {$before->format('j M Y')} to {$date->format('j M Y')}",
                    default => "{$label} moved forward from {$before->format('j M Y')} to {$date->format('j M Y')}",
                };
                $changes[] = LockDateChange::record($tenant->id, $lock, $before, $date, $back ? $reason : ($reason ?: null), $description, $userId);
            }

            $tenant->forceFill([
                'staff_lock_date' => $staff?->toDateString(),
                'all_users_lock_date' => $all?->toDateString(),
            ])->save();

            return $changes;
        });
    }

    private function date(?string $value): ?Carbon
    {
        return $value !== null && trim($value) !== '' ? Carbon::parse($value)->startOfDay() : null;
    }
}
