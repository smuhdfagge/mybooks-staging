<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

/**
 * The support address on the public pages.
 *
 * It shows on four of them, including the privacy policy and the terms,
 * which are the pages a customer reaches for when something has gone
 * wrong. They all read config('mybooks.support_email') now, so there is
 * one place to change it and no page can be left behind.
 */
class SupportEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_pages_show_the_configured_address(): void
    {
        config(['mybooks.support_email' => 'support@mybooks.cloud']);

        foreach (['contact', 'support', 'privacy-policy', 'terms-of-service'] as $page) {
            $this->get(route($page))->assertOk()
                ->assertSee('mailto:support@mybooks.cloud', false)
                ->assertSee('support@mybooks.cloud');
        }
    }

    public function test_changing_the_setting_changes_every_page(): void
    {
        // None of them hold their own copy of the address.
        config(['mybooks.support_email' => 'help@example.test']);

        foreach (['contact', 'support', 'privacy-policy', 'terms-of-service'] as $page) {
            $this->get(route($page))->assertOk()
                ->assertSee('help@example.test')
                ->assertDontSee('support@mybooks.cloud');
        }
    }

    public function test_the_old_address_is_gone(): void
    {
        config(['mybooks.support_email' => 'support@mybooks.cloud']);

        // my-books.cloud, with the hyphen, is not a domain MyBooks answers
        // mail on, so a page still showing it sends customers nowhere.
        foreach (['home', 'about', 'contact', 'support', 'privacy-policy', 'terms-of-service'] as $page) {
            $this->get(route($page))->assertOk()
                ->assertDontSee('support@my-books.cloud');
        }
    }
}
