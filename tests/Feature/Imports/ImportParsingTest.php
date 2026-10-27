<?php

namespace Tests\Feature\Imports;

use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Reading dates and amounts out of a spreadsheet.
 *
 * Both were wrong, and both were wrong silently: the import reported
 * success and wrote the wrong figures. In an accounting system that is
 * worse than refusing the file, because nobody goes looking.
 */
class ImportParsingTest extends TestCase
{
    use RefreshDatabase;

    private function read(string $method, mixed $value): mixed
    {
        $m = new ReflectionMethod(ImportService::class, $method);
        $m->setAccessible(true);

        return $m->invoke(app(ImportService::class), $value);
    }

    public static function dates(): array
    {
        return [
            // Day first, the way dates are written in Nigeria.
            'the fifth of November' => ['05/11/2026', '2026-11-05'],
            'a day past the twelfth' => ['25/11/2026', '2026-11-25'],
            'the last day of the year' => ['31/12/2026', '2026-12-31'],
            'dashes' => ['05-11-2026', '2026-11-05'],
            'ISO is left alone' => ['2026-11-05', '2026-11-05'],
            'written out' => ['5 November 2026', '2026-11-05'],
        ];
    }

    #[DataProvider('dates')]
    public function test_a_date_is_read_day_first(string $given, string $expected): void
    {
        $this->assertSame($expected, $this->read('parseDate', $given));
    }

    public function test_an_impossible_date_is_refused_rather_than_rolled_over(): void
    {
        // This is what went wrong: createFromFormat took a month of 25 and
        // moved it into the next year instead of failing, so 31/12/2026
        // was stored as 2028-07-12 — the wrong year, with no error.
        $this->assertNull($this->read('parseDate', '45/45/2026'));
        $this->assertNull($this->read('parseDate', 'not a date'));
        $this->assertNull($this->read('parseDate', ''));
    }

    public static function amounts(): array
    {
        return [
            'plain' => ['1500.00', 1500.0],
            'thousands separator' => ['1,500.00', 1500.0],
            'millions' => ['1,531,875.00', 1531875.0],
            'with the naira sign' => ['₦1,500.00', 1500.0],
            'with the currency code' => ['NGN 1,500.00', 1500.0],
            'brackets mean negative' => ['(250.00)', -250.0],
            'a bare integer' => ['42', 42.0],
            'already a number' => [1500.5, 1500.5],
            'a negative sign' => ['-250.00', -250.0],
        ];
    }

    #[DataProvider('amounts')]
    public function test_an_amount_survives_the_way_a_spreadsheet_writes_it(mixed $given, float $expected): void
    {
        $this->assertSame($expected, $this->read('parseAmount', $given));
    }

    public function test_something_that_is_not_an_amount_is_refused(): void
    {
        // Rather than becoming 0.0 and dropping out of the totals.
        $this->assertNull($this->read('parseAmount', 'see attached'));
        $this->assertNull($this->read('parseAmount', ''));
        $this->assertNull($this->read('parseAmount', null));
    }

    public function test_a_european_number_is_refused_rather_than_guessed_at(): void
    {
        // 1.250,00 means one thousand two hundred and fifty somewhere, and
        // one and a quarter here. Guessing either way would be a silent
        // error of the kind this whole change is about.
        $this->assertNull($this->read('parseAmount', '1.250,00'));
        // A comma used as the decimal point, same reasoning.
        $this->assertNull($this->read('parseAmount', '1250,50'));
        // Commas that group nothing sensible.
        $this->assertNull($this->read('parseAmount', '1,50,000'));
    }
}
