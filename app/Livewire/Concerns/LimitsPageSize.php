<?php

namespace App\Livewire\Concerns;

/**
 * List tables only accept the page sizes they offer (P6). Any other
 * value sent from the browser falls back to the table's default, so a
 * request cannot ask for 100,000 rows.
 */
trait LimitsPageSize
{
    /** @var array<int, int> */
    public const PAGE_SIZES = [10, 15, 25, 50, 100];

    protected function pageSize(): int
    {
        $size = filter_var($this->perPage, FILTER_VALIDATE_INT);

        if (! in_array($size, self::PAGE_SIZES, true)) {
            $default = (new \ReflectionClass($this))->getDefaultProperties()['perPage'] ?? 10;
            $size = in_array($default, self::PAGE_SIZES, true) ? $default : 10;
            $this->perPage = $size;
        }

        return $size;
    }
}
