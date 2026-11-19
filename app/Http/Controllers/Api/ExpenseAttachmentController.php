<?php

namespace App\Http\Controllers\Api;

use App\Models\Expense;
use App\Models\ExpenseAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Receipts on an expense (mobile plan A1): list, upload, download, delete.
 * Listing and downloading need "view expenses", uploading and deleting
 * "edit expenses" (see routes/api.php). Files stay on the private disk.
 */
class ExpenseAttachmentController extends BaseApiController
{
    public function index(Expense $expense): JsonResponse
    {
        return $this->success($expense->attachments()->with('uploader:id,name')->get()->map(fn ($a) => $this->present($expense, $a))->values());
    }

    public function store(Request $request, Expense $expense): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:'.ExpenseAttachment::MIMES, 'max:'.ExpenseAttachment::MAX_KB],
        ], [
            'file.mimes' => 'A receipt must be a photo (JPG, PNG or WebP) or a PDF.',
            'file.max' => 'A receipt can be at most '.(ExpenseAttachment::MAX_KB / 1024).' MB.',
        ]);

        if ($expense->attachments()->count() >= ExpenseAttachment::MAX_PER_EXPENSE) {
            return $this->error('An expense can have at most '.ExpenseAttachment::MAX_PER_EXPENSE.' receipts. Delete one first.', 422, [
                'file' => ['An expense can have at most '.ExpenseAttachment::MAX_PER_EXPENSE.' receipts.'],
            ]);
        }

        $file = $request->file('file');
        $path = $file->store("expense-receipts/{$expense->tenant_id}/{$expense->id}", ExpenseAttachment::DISK);
        if ($path === false) {
            return $this->error('The receipt could not be saved. Please try again.', 500);
        }

        $attachment = $expense->attachments()->create([
            'tenant_id' => $expense->tenant_id,
            'file_path' => $path,
            'file_name' => mb_substr(basename((string) $file->getClientOriginalName()), 0, 200) ?: 'receipt',
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $request->user()?->id,
        ]);

        return $this->created($this->present($expense, $attachment->load('uploader:id,name')), 'Receipt added.');
    }

    public function show(Expense $expense, ExpenseAttachment $attachment): StreamedResponse|JsonResponse
    {
        if (! Storage::disk(ExpenseAttachment::DISK)->exists($attachment->file_path)) {
            return $this->error('This receipt file is missing.', 404);
        }

        return Storage::disk(ExpenseAttachment::DISK)->download($attachment->file_path, $attachment->file_name, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function destroy(Expense $expense, ExpenseAttachment $attachment): JsonResponse
    {
        $attachment->delete();

        return $this->success(null, 'Receipt deleted.');
    }

    /** @return array<string, mixed> */
    private function present(Expense $expense, ExpenseAttachment $a): array
    {
        return [
            'id' => $a->id,
            'expense_id' => $a->expense_id,
            'file_name' => $a->file_name,
            'mime_type' => $a->mime_type,
            'size' => $a->size,
            'uploaded_by' => $a->uploader ? ['id' => $a->uploader->id, 'name' => $a->uploader->name] : null,
            'created_at' => $a->created_at?->toISOString(),
            'download_url' => route('api.expenses.attachments.show', [$expense, $a]),
        ];
    }
}
