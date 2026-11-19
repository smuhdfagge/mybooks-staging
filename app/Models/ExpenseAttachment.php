<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A receipt (photo or PDF) on an expense (mobile plan A1). The file lives on
 * the private "local" disk; deleting the row deletes the file.
 */
class ExpenseAttachment extends Model
{
    use BelongsToTenant, LogsActivity;

    public const DISK = 'local';

    /** Most receipts one expense can have. */
    public const MAX_PER_EXPENSE = 10;

    /** Largest file, in KB. A phone photo is often 2-4 MB, so more than the web's 2 MB image limit. */
    public const MAX_KB = 5120;

    /** File types allowed (checked from the file's content, not its name). */
    public const MIMES = 'jpg,jpeg,png,webp,pdf';

    protected $fillable = [
        'tenant_id',
        'expense_id',
        'file_path',
        'file_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleted(function (ExpenseAttachment $attachment) {
            Storage::disk(self::DISK)->delete($attachment->file_path);
        });
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
