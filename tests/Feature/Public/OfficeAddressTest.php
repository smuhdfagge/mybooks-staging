<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

/**
 * The office address on the public pages.
 *
 * It shows in three places — the contact page, the about page's opening
 * line and the home page's structured data — and all three read the same
 * config entry. The test that matters is the last one here: that no page
 * still carries the old Kano address, which is how a move usually goes
 * wrong.
 */
class OfficeAddressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_contact_page_shows_the_address(): void
    {
        $this->get(route('contact'))->assertOk()
            ->assertSee('Block B House B2, Sani Zangon Daura Estate')
            ->assertSee('Kado, Abuja')
            ->assertSee('Our office in Abuja.');
    }

    public function test_the_about_page_names_the_city(): void
    {
        $this->get(route('about'))->assertOk()
            ->assertSee('run from our office in Abuja.');
    }

    public function test_the_home_page_structured_data_carries_the_address(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
        $graph = collect(json_decode($m[1], true, flags: JSON_THROW_ON_ERROR)['@graph'])->keyBy('@type');

        $this->assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => 'Block B House B2, Sani Zangon Daura Estate',
            'addressLocality' => 'Kado',
            'addressRegion' => 'Abuja',
            'addressCountry' => 'NG',
        ], $graph['Organization']['address']);
    }

    public function test_no_public_page_still_shows_the_old_address(): void
    {
        // A move that leaves one page behind is worse than not moving: it
        // is the page nobody thought to check that customers find.
        foreach (['home', 'about', 'contact', 'support', 'privacy-policy', 'terms-of-service'] as $page) {
            $html = $this->get(route($page))->assertOk()->getContent();

            $this->assertStringNotContainsString('Wapa', $html, "The old address is still on the {$page} page.");
            $this->assertStringNotContainsString('ando House', $html, "The old address is still on the {$page} page.");
            $this->assertStringNotContainsString('700213', $html, "The old postcode is still on the {$page} page.");
            $this->assertStringNotContainsString('office in Kano', $html, "The {$page} page still says the office is in Kano.");
        }
    }
}
