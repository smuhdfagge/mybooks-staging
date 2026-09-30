<?php

namespace Tests\Feature\Regression;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use Tests\TestCase;

/**
 * Round 3, Phase D: security fixes S2 to S8.
 */
class PhaseDSecurityTest extends TestCase
{
    // ── S3: journal account options ─────────────────────────────

    public function test_s3_journal_pages_add_account_options_as_text_not_html(): void
    {
        $this->createAuthenticatedUser(['create journals', 'edit journals']);
        ChartOfAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '<img src=x onerror=alert(1)>',
            'is_active' => true,
        ]);
        $journal = Journal::withoutEvents(fn () => Journal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
            'is_posted' => false,
        ]));

        foreach ([route('journals.create'), route('journals.edit', $journal)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // The "add row" script used to paste account names into an HTML
            // string, so a name with markup ran as code.
            $this->assertStringNotContainsString('${a.name}</option>', $html);
            $this->assertStringNotContainsString('${accountOptions}', $html);
            $this->assertStringContainsString('select.add(new Option(', $html);
        }
    }
}
