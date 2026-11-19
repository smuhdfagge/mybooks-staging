<?php

namespace Tests\Feature\Api;

use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mobile plan A1: receipts (photos or PDFs) on an expense over the API,
 * kept on the private disk and reached only through the expense.
 */
class ExpenseAttachmentApiTest extends TestCase
{
    private Expense $expense;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ExpenseAttachment::DISK);
        $this->createAuthenticatedUser(['view expenses', 'edit expenses']);
        $this->expense = Expense::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function url(?Expense $expense = null): string
    {
        return '/api/v1/expenses/'.($expense ?? $this->expense)->id.'/attachments';
    }

    public function test_a_receipt_can_be_uploaded_listed_downloaded_and_deleted(): void
    {
        $response = $this->postJson($this->url(), ['file' => UploadedFile::fake()->image('fuel receipt.jpg', 800, 1200)])
            ->assertCreated()
            ->assertJsonPath('data.file_name', 'fuel receipt.jpg')
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonPath('data.uploaded_by.id', $this->user->id);
        $id = $response->json('data.id');
        $attachment = ExpenseAttachment::findOrFail($id);
        Storage::disk(ExpenseAttachment::DISK)->assertExists($attachment->file_path);
        $this->assertStringStartsWith("expense-receipts/{$this->tenant->id}/{$this->expense->id}/", $attachment->file_path);

        $this->postJson($this->url(), ['file' => UploadedFile::fake()->create('invoice.pdf', 300, 'application/pdf')])->assertCreated();

        $this->getJson($this->url())->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.download_url', route('api.expenses.attachments.show', [$this->expense, $attachment]));

        $this->get($this->url()."/{$id}")->assertOk()->assertDownload('fuel receipt.jpg');

        $this->deleteJson($this->url()."/{$id}")->assertOk();
        $this->assertNull(ExpenseAttachment::find($id));
        Storage::disk(ExpenseAttachment::DISK)->assertMissing($attachment->file_path);
        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_only_photos_and_pdfs_up_to_the_size_limit_are_taken(): void
    {
        $this->postJson($this->url(), [])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($this->url(), ['file' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($this->url(), ['file' => UploadedFile::fake()->create('scan.pdf', ExpenseAttachment::MAX_KB + 1, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(0, ExpenseAttachment::count());
    }

    public function test_an_expense_can_have_at_most_ten_receipts(): void
    {
        for ($i = 0; $i < ExpenseAttachment::MAX_PER_EXPENSE; $i++) {
            $this->postJson($this->url(), ['file' => UploadedFile::fake()->image("r{$i}.png")])->assertCreated();
        }
        $this->postJson($this->url(), ['file' => UploadedFile::fake()->image('one-more.png')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(ExpenseAttachment::MAX_PER_EXPENSE, $this->expense->attachments()->count());
    }

    public function test_viewing_needs_view_expenses_and_changing_needs_edit_expenses(): void
    {
        $id = $this->postJson($this->url(), ['file' => UploadedFile::fake()->image('r.jpg')])->json('data.id');

        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->givePermissionTo(['view expenses']);
        $this->actingAs($viewer);
        $this->getJson($this->url())->assertOk();
        $this->get($this->url()."/{$id}")->assertOk();
        $this->postJson($this->url(), ['file' => UploadedFile::fake()->image('r.jpg')])->assertForbidden();
        $this->deleteJson($this->url()."/{$id}")->assertForbidden();

        $nobody = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($nobody);
        $this->getJson($this->url())->assertForbidden();
        $this->getJson($this->url()."/{$id}")->assertForbidden();
        $this->assertSame(1, ExpenseAttachment::count());
    }

    public function test_a_receipt_is_only_reached_through_its_own_expense_and_business(): void
    {
        $id = $this->postJson($this->url(), ['file' => UploadedFile::fake()->image('r.jpg')])->json('data.id');
        $second = Expense::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->getJson($this->url($second)."/{$id}")->assertNotFound();
        $this->deleteJson($this->url($second)."/{$id}")->assertNotFound();

        auth()->forgetGuards();
        [$other] = $this->createTenantWithSubscription();
        $theirs = Expense::withoutTenantGuard(fn () => Expense::factory()->create(['tenant_id' => $other->id]));
        $this->actingAs($this->user);
        $this->getJson($this->url($theirs))->assertNotFound();
        $this->postJson($this->url($theirs), ['file' => UploadedFile::fake()->image('r.jpg')])->assertNotFound();
        $this->assertSame(1, ExpenseAttachment::count());
    }

    public function test_deleting_the_expense_for_good_removes_its_receipt_files(): void
    {
        $id = $this->postJson($this->url(), ['file' => UploadedFile::fake()->image('r.jpg')])->json('data.id');
        $path = ExpenseAttachment::findOrFail($id)->file_path;

        $this->expense->delete(); // soft: the receipt stays, the expense can come back
        Storage::disk(ExpenseAttachment::DISK)->assertExists($path);

        $this->expense->forceDelete();
        Storage::disk(ExpenseAttachment::DISK)->assertMissing($path);
        $this->assertNull(ExpenseAttachment::find($id));
    }

    public function test_the_same_idempotency_key_with_a_different_file_is_refused(): void
    {
        $file = UploadedFile::fake()->image('a.jpg', 10, 10);
        $this->withHeader('Idempotency-Key', 'receipt-1')->postJson($this->url(), ['file' => $file])->assertCreated();
        $this->withHeader('Idempotency-Key', 'receipt-1')->postJson($this->url(), ['file' => UploadedFile::fake()->image('a.jpg', 20, 20)])
            ->assertStatus(422);
        $this->assertSame(1, ExpenseAttachment::count());
    }
}
