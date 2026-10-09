<?php

namespace App\Models;

use App\Enums\EInvoiceStatus;
use App\Services\EInvoicing\QrImage;
use App\Traits\BelongsToTenant;
use App\Traits\GuardsStatusTransitions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What happened when one invoice or credit note was sent to NRS (session
 * 18). Only records status: it never touches the books. One row per
 * document.
 *
 * @property EInvoiceStatus|string $status
 */
class EInvoiceSubmission extends Model
{
    use BelongsToTenant, GuardsStatusTransitions;

    /** What to tell a person who tries to change a document NRS has accepted. */
    public static function lockedMessage(Invoice|CreditNote $document): string
    {
        $what = $document instanceof Invoice ? "Invoice {$document->invoice_number}" : "Credit note {$document->credit_note_number}";

        return "{$what} was accepted by NRS, so it can no longer be changed or cancelled. To correct it, issue a credit note.";
    }

    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = ['csid', 'qr_image'];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'late_warned_at' => 'datetime',
    ];

    protected static function statusEnum(): string
    {
        return EInvoiceStatus::class;
    }

    protected static function statusDocumentName(): string
    {
        return 'e-invoice';
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<CreditNote, $this> */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function state(): EInvoiceStatus
    {
        return EInvoiceStatus::tryFrom((string) $this->status) ?? EInvoiceStatus::NotSubmitted;
    }

    public function isAccepted(): bool
    {
        return $this->status === EInvoiceStatus::Accepted->value;
    }

    /** The row for a document, or null when nothing has been tried. */
    public static function forDocument(Invoice|CreditNote $document): ?self
    {
        $column = $document instanceof Invoice ? 'invoice_id' : 'credit_note_id';

        return self::query()->withoutGlobalScopes()->where($column, $document->getKey())->first();
    }

    /** NRS has cleared this document: its money and customer must not change. */
    public static function isLocked(Invoice|CreditNote $document): bool
    {
        if (! $document->exists) {
            return false;
        }
        $column = $document instanceof Invoice ? 'invoice_id' : 'credit_note_id';

        return self::query()->withoutGlobalScopes()->where($column, $document->getKey())
            ->where('status', EInvoiceStatus::Accepted->value)->exists();
    }

    /** The QR as an image address for an <img>, or null. */
    public function qrSrc(): ?string
    {
        if (filled($this->qr_image)) {
            $image = (string) $this->qr_image;

            return str_starts_with($image, 'data:') ? $image : 'data:image/png;base64,'.$image;
        }
        if (filled($this->qr_payload)) {
            return 'data:image/svg+xml;base64,'.base64_encode(QrImage::svg((string) $this->qr_payload));
        }

        return null;
    }
}
