<?php

namespace App\Enums;

/**
 * A document status enum with allowed moves (finding Q3).
 */
interface DocumentStatus extends \BackedEnum
{
    public function canMoveTo(DocumentStatus $to): bool;

    public function label(): string;
}
