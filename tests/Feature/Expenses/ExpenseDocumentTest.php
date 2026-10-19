<?php

namespace Tests\Feature\Expenses;

use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Vendor;
use App\Support\AmountInWords;
use App\Support\ExpenseDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Expenses print like invoices: the expense page shows the payment voucher
 * itself and prints it in place, the list and the PDF use the same
 * document, and nothing about an expense opens in a new tab.
 */
class ExpenseDocumentTest extends TestCase
{
    private int $no = 400;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-19 10:00:00'));
        $this->createAuthenticatedUser(['view expenses', 'create expenses', 'edit expenses', 'view settings']);
        $this->tenant->update(['name' => 'Kano Traders Ltd', 'currency' => 'NGN', 'tax_number' => '12345678-0001']);
    }

    private function expense(array $attributes = []): Expense
    {
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dantata Supplies']);
        $account = ChartOfAccount::query()->where('tenant_id', $this->tenant->id)->where('type', 'expense')->first();

        $expense = Expense::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'expense_account_id' => $account?->id,
            'expense_number' => 'EXP-000'.(++$this->no),
            'name' => 'Diesel for the generator',
            'expense_date' => '2026-10-01',
            'amount' => 100000,
            'tax_amount' => 7500,
            'total' => 107500,
            'status' => Expense::STATUS_DRAFT,
            'payment_method' => 'bank_transfer',
            'reference' => 'TRF-8841',
            'created_by' => $this->user->id,
        ], $attributes));

        return $expense->fresh();
    }

    public function test_expense_page_shows_the_voucher_and_prints_in_place(): void
    {
        $expense = $this->expense();

        $html = $this->get(route('expenses.show', $expense))->assertOk()
            ->assertSee('data-expense-document', false)
            ->assertSee('Payment Voucher')
            ->assertSee('Kano Traders Ltd')
            ->assertSee('TIN: 12345678-0001')
            ->assertSee('Dantata Supplies')
            ->assertSee('Diesel for the generator')
            ->assertSee('data-testid="print-expense"', false)
            ->assertSee('Amount in words')
            ->assertSee('Prepared by')
            ->assertSee('Approved by')
            ->assertSee('Received by')
            ->getContent();

        $this->assertStringNotContainsString('target="_blank"', $html, 'Nothing on the expense page opens a new tab.');
        // The figure and the words agree, so the amount cannot be altered later.
        $this->assertStringContainsString('One Hundred and Seven Thousand, Five Hundred Naira Only', $html);
    }

    public function test_expense_list_print_opens_the_expense_and_prints_in_the_same_tab(): void
    {
        $expense = $this->expense();

        $html = $this->get(route('expenses.index'))->assertOk()->getContent();

        $this->assertStringContainsString(e(route('expenses.show', ['expense' => $expense, 'print' => 1])), $html);
        $this->assertStringNotContainsString('target="_blank"', $html);
        $this->get(route('expenses.show', ['expense' => $expense, 'print' => 1]))->assertOk()
            ->assertSee("searchParams.get('print') !== '1'", false);
    }

    public function test_print_page_is_the_same_document_with_a_way_back(): void
    {
        $expense = $this->expense();

        $this->get(route('expenses.print', $expense))->assertOk()
            ->assertSee('data-expense-document', false)
            ->assertSee('Back to expense')
            ->assertSee(route('expenses.show', $expense), false)
            ->assertSee('data-print', false);
    }

    public function test_download_pdf_is_a_real_pdf_named_for_the_voucher(): void
    {
        $expense = $this->expense();

        $response = $this->get(route('expenses.pdf', $expense))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            'Payment voucher '.$expense->expense_number.' - Dantata Supplies.pdf',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_printing_keeps_its_own_margins_and_a_paid_voucher_says_so(): void
    {
        $expense = $this->expense(['status' => Expense::STATUS_PAID]);

        $html = $this->get(route('expenses.show', $expense))->assertOk()
            ->assertSee('Amount paid')
            ->assertSee('Paid on 1 Oct 2026')
            ->getContent();

        // The sheet carries the margins (repeated on every page), so the print
        // dialog's margin setting cannot push the voucher off the paper.
        $this->assertStringContainsString('@page { size: A4; margin: 0; }', $html);
        $this->assertStringContainsString('box-decoration-break: clone', $html);
    }

    public function test_status_in_words(): void
    {
        $this->assertSame('draft', ExpenseDocument::state($this->expense())['key']);
        $this->assertSame('Paid', ExpenseDocument::state($this->expense(['status' => Expense::STATUS_PAID]))['label']);
        $this->assertSame('Awaiting approval', ExpenseDocument::state($this->expense(['status' => Expense::STATUS_PENDING_APPROVAL]))['label']);
        $this->assertSame('Rejected', ExpenseDocument::state($this->expense(['status' => Expense::STATUS_REJECTED]))['label']);
        $this->assertSame('Approved, awaiting payment', ExpenseDocument::state($this->expense(['status' => Expense::STATUS_APPROVED]))['label']);
    }

    public function test_the_voucher_carries_the_business_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/kano.webp', $this->picture());
        $this->tenant->update(['logo' => 'logos/kano.webp']);
        $expense = $this->expense();

        $this->get(route('expenses.show', $expense))->assertOk()
            ->assertSee('data-testid="expense-logo"', false)
            ->assertDontSee('data-testid="add-logo-hint"', false)
            ->assertSee('src="data:image/png;base64,', false);
    }

    public function test_without_a_vendor_the_voucher_still_prints(): void
    {
        $expense = $this->expense(['vendor_id' => null]);

        $this->get(route('expenses.show', $expense))->assertOk()->assertSee('Not recorded');
        $this->assertStringStartsWith('%PDF', $this->get(route('expenses.pdf', $expense))->assertOk()->getContent());
        $this->assertSame('Payment voucher '.$expense->expense_number.'.pdf', ExpenseDocument::fileName($expense));
    }

    /**
     * The amount a voucher is signed against, written out. The cases that
     * trip naive conversions: the "and" before a remainder under a hundred,
     * the kobo, a round amount, and the hyphen in the twenties upward.
     */
    #[DataProvider('amounts')]
    public function test_amount_in_words(float $amount, string $expected): void
    {
        $this->assertSame($expected, AmountInWords::format($amount, 'NGN'));
    }

    public static function amounts(): array
    {
        return [
            'round thousands' => [50000, 'Fifty Thousand Naira Only'],
            'with kobo' => [1500.75, 'One Thousand, Five Hundred Naira, Seventy-Five Kobo Only'],
            'and under a hundred' => [1050, 'One Thousand and Fifty Naira Only'],
            'a larger remainder takes a comma, not "and"' => [1200, 'One Thousand, Two Hundred Naira Only'],
            'teens' => [15, 'Fifteen Naira Only'],
            'hundred and unit' => [101, 'One Hundred and One Naira Only'],
            'millions' => [2500000, 'Two Million, Five Hundred Thousand Naira Only'],
            'zero' => [0, 'Zero Naira Only'],
            'one kobo' => [0.01, 'Zero Naira, One Kobo Only'],
            'negative' => [-250, 'Minus Two Hundred and Fifty Naira Only'],
        ];
    }

    /** A small WebP picture, made with GD. */
    private function picture(): string
    {
        $img = imagecreatetruecolor(300, 100);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 31, 78, 121));
        ob_start();
        imagewebp($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
