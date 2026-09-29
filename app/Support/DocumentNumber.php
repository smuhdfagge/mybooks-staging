<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Document numbers (INV-000123, JE-000045, ...) from a per-business
 * sequence (finding R2).
 *
 * The old "last number + 1" read the latest row without a lock, so two
 * saves at the same moment got the same number and the second failed; and
 * an imported number in another shape (e.g. "2024/001") as the latest row
 * broke every later create.
 *
 * Here the business's row in document_sequences is locked and incremented
 * (inside the caller's transaction when there is one). The first time a
 * type is numbered, the sequence starts after the highest existing number
 * with the same prefix; numbers already taken are skipped.
 */
class DocumentNumber
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function next(int $tenantId, string $model, string $column, string $prefix, int $pad = 6): string
    {
        $type = class_basename($model).'.'.$column;

        return DB::transaction(function () use ($tenantId, $model, $column, $prefix, $pad, $type) {
            $row = DB::table('document_sequences')
                ->where('tenant_id', $tenantId)
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $start = static::highestUsed($tenantId, $model, $column, $prefix) + 1;
                try {
                    DB::table('document_sequences')->insert([
                        'tenant_id' => $tenantId, 'type' => $type, 'next_number' => $start,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Someone else created it a moment ago; use theirs.
                }
                $row = DB::table('document_sequences')
                    ->where('tenant_id', $tenantId)
                    ->where('type', $type)
                    ->lockForUpdate()
                    ->first();
            }

            $number = (int) $row->next_number;
            do {
                $candidate = $prefix.str_pad((string) $number, $pad, '0', STR_PAD_LEFT);
                $taken = $model::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where($column, $candidate)
                    ->exists();
                $number++;
            } while ($taken);

            DB::table('document_sequences')
                ->where('id', $row->id)
                ->update(['next_number' => $number, 'updated_at' => now()]);

            return $candidate;
        });
    }

    /**
     * The number the next save will probably get, for showing on a new
     * form. Doesn't use up a number; the real one is given on save.
     *
     * @param  class-string<Model>  $model
     */
    public static function preview(int $tenantId, string $model, string $column, string $prefix, int $pad = 6): string
    {
        $type = class_basename($model).'.'.$column;
        $number = (int) (DB::table('document_sequences')->where('tenant_id', $tenantId)->where('type', $type)->value('next_number')
            ?? static::highestUsed($tenantId, $model, $column, $prefix) + 1);

        while ($model::withoutGlobalScopes()->where('tenant_id', $tenantId)->where($column, $prefix.str_pad((string) $number, $pad, '0', STR_PAD_LEFT))->exists()) {
            $number++;
        }

        return $prefix.str_pad((string) $number, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected static function highestUsed(int $tenantId, string $model, string $column, string $prefix): int
    {
        return (int) $model::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where($column, 'like', $prefix.'%')
            ->pluck($column)
            ->map(fn ($value) => substr((string) $value, strlen($prefix)))
            ->filter(fn ($suffix) => $suffix !== '' && ctype_digit($suffix))
            ->map(fn ($suffix) => (int) $suffix)
            ->max();
    }
}
