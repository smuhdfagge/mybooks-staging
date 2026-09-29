<?php

namespace App\Enums;

/**
 * Shared helpers for document status enums (finding Q3).
 */
trait StatusTransitions
{
    /** @return array<int, self> */
    abstract public function allowedNext(): array;

    public function canMoveTo(DocumentStatus $to): bool
    {
        return $to === $this || in_array($to, $this->allowedNext(), true);
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
