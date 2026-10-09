<?php

namespace App\Services\EInvoicing;

/**
 * NRS's answer to a submission (session 18), whatever the driver.
 *
 * outcome: accepted, pending (NRS has it but has not finished), rejected
 * (NRS refuses it: the document needs fixing) or failed (could not be
 * sent or NRS had a problem: try again later). message is plain English.
 */
final class NrsResult
{
    public function __construct(
        public readonly string $outcome,
        public readonly ?string $irn = null,
        public readonly ?string $csid = null,
        public readonly ?string $qrImage = null,
        public readonly ?string $qrPayload = null,
        public readonly ?string $message = null,
        public readonly bool $simulated = false,
    ) {}

    public static function accepted(?string $irn, ?string $csid, ?string $qrImage, ?string $qrPayload = null, bool $simulated = false): self
    {
        return new self('accepted', $irn, $csid, $qrImage, $qrPayload, null, $simulated);
    }

    public static function pending(?string $irn = null, ?string $message = null): self
    {
        return new self('pending', $irn, message: $message);
    }

    public static function rejected(string $message): self
    {
        return new self('rejected', message: $message);
    }

    public static function failed(string $message): self
    {
        return new self('failed', message: $message);
    }
}
