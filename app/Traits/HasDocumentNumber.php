<?php

namespace App\Traits;

use App\Support\DocumentNumber;

/**
 * Document numbering from the locked per-business sequence (finding R2).
 * The model says which column, prefix and length through
 * documentNumberFormat(): [column, prefix, digits].
 */
trait HasDocumentNumber
{
    /** @return array{0: string, 1: string, 2: int} */
    abstract protected static function documentNumberFormat(): array;

    /** Give out the next number (uses it up). Call when saving. */
    public static function generateNumber($tenantId): string
    {
        [$column, $prefix, $pad] = static::documentNumberFormat();

        return DocumentNumber::next((int) $tenantId, static::class, $column, $prefix, $pad);
    }

    /** The number a new document will probably get, for showing on a form. */
    public static function previewNumber($tenantId): string
    {
        [$column, $prefix, $pad] = static::documentNumberFormat();

        return DocumentNumber::preview((int) $tenantId, static::class, $column, $prefix, $pad);
    }
}
