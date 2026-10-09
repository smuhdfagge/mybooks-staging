<?php

namespace App\Services\BankFeeds;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/** A MyBooks record that might be the same money movement as a bank line. */
final class Candidate
{
    /** @param  list<string>  $references numbers and references to look for in the bank's narration */
    public function __construct(
        public readonly Model $record,
        /** e.g. "Payment received" */
        public readonly string $kind,
        /** e.g. "PR-000012" */
        public readonly string $number,
        public readonly CarbonInterface $date,
        public readonly float $amount,
        public readonly ?string $party,
        public readonly array $references,
        public readonly ?string $url,
    ) {}

    public function key(): string
    {
        return $this->record::class.':'.$this->record->getKey();
    }

    public function describe(): string
    {
        return trim($this->kind.' '.$this->number.($this->party ? ' · '.$this->party : ''));
    }
}
