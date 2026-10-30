<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Tests\TestCase;

/**
 * "Add line" on the journal pages did nothing: the script appends rows to
 * #entriesBody, and moving the lines into <x-table> dropped that id from the
 * <tbody> (TypeError: Cannot read properties of null (reading 'appendChild')).
 * Adding and removing lines and the totals were checked in a browser; this
 * holds the markup the script needs in place.
 */
class JournalLineEditorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createAuthenticatedUser(['view journals', 'create journals', 'edit journals']);
    }

    public function test_every_element_the_line_script_looks_up_is_on_the_create_and_edit_pages(): void
    {
        $journal = Journal::withoutEvents(function () {
            $journal = Journal::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'draft', 'is_posted' => false]);
            $account = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
            JournalEntry::factory()->for($journal)->debit(100)->create(['account_id' => $account->id]);
            JournalEntry::factory()->for($journal)->credit(100)->create(['account_id' => $account->id]);

            return $journal;
        });

        foreach ([route('journals.create'), route('journals.edit', $journal)] as $url) {
            $xpath = $this->xpath($this->get($url)->assertOk()->getContent());

            // The script sits in a nonced block (the CSP has no unsafe-inline).
            $scripts = $xpath->query('//script[@nonce and contains(., "function addRow")]');
            $this->assertSame(1, $scripts->length, $url);

            preg_match_all("/getElementById\\('([^']+)'\\)/", $scripts->item(0)->textContent, $m);
            $this->assertContains('entriesBody', $m[1], $url);
            foreach (array_unique($m[1]) as $id) {
                $this->assertSame(1, $xpath->query("//*[@id='{$id}']")->length, "#{$id} missing on {$url}");
            }

            // New rows go in the same <tbody> as the rendered ones, inside #entriesTable.
            $body = $xpath->query("//*[@id='entriesTable']//table/tbody[@id='entriesBody']")->item(0);
            $this->assertInstanceOf(DOMElement::class, $body, $url);
            $this->assertGreaterThanOrEqual(2, $xpath->query('tr[contains(@class, "entry-row")]', $body)->length, $url);
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }
}
