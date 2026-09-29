<?php

namespace App\Traits;

use Illuminate\Validation\ValidationException;

/**
 * Refuses a status change the document's status enum doesn't allow
 * (finding Q3), for example re-opening a cancelled invoice or putting a
 * paid bill back to draft, whichever screen, API call or command tries it.
 * The refusal is a validation error, so the web shows it on the form and
 * the API answers 422.
 *
 * Models define statusEnum(): the enum class. Mass updates on a query
 * (Model::whereIn(...)->update()) skip model events; don't use them for
 * status changes.
 */
trait GuardsStatusTransitions
{
    protected static function bootGuardsStatusTransitions(): void
    {
        static::saving(function ($document) {
            if (! $document->isDirty('status')) {
                return;
            }

            /** @var class-string<\App\Enums\DocumentStatus> $enum */
            $enum = static::statusEnum();
            $to = $enum::tryFrom((string) $document->status);
            if (! $to) {
                throw ValidationException::withMessages(['status' => "\"{$document->status}\" is not a status for this document."]);
            }

            $original = $document->exists ? $document->getOriginal('status') : null;
            $from = $original !== null ? $enum::tryFrom((string) $original) : null;
            if ($from && ! $from->canMoveTo($to)) {
                throw ValidationException::withMessages(['status' => "A {$from->label()} ".static::statusDocumentName()." can't be marked {$to->label()}."]);
            }
        });
    }

    /** @return class-string<\App\Enums\DocumentStatus> */
    abstract protected static function statusEnum(): string;

    protected static function statusDocumentName(): string
    {
        return strtolower(str_replace('_', ' ', \Illuminate\Support\Str::snake(class_basename(static::class))));
    }
}
