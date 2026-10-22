<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

/**
 * How the public pages name MyBooks: the support address, and the domain
 * the privacy policy and the terms say the service runs on.
 *
 * Both were written with a hyphen, my-books.cloud, which is not a domain
 * MyBooks has. The address bounced, and the two documents that say what
 * MyBooks promises its customers named a website it does not own.
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

    public function test_no_page_names_a_domain_mybooks_does_not_own(): void
    {
        // Set here rather than left to .env, because the home page's
        // structured data publishes the support address and would
        // otherwise fail this on whatever a given machine happens to have
        // configured. That is the views being tested, not the server.
        config(['mybooks.support_email' => 'support@mybooks.cloud']);

        // The privacy policy and the terms each name the website four
        // times, in the parts that say who the document binds and where
        // changes to it will appear. Naming the wrong one there is worse
        // than a broken link.
        foreach (['home', 'about', 'contact', 'support', 'privacy-policy', 'terms-of-service'] as $page) {
            $this->get(route($page))->assertOk()
                ->assertDontSee('my-books.cloud');
        }
    }

    public function test_the_legal_pages_say_where_the_service_runs(): void
    {
        $this->get(route('privacy-policy'))->assertOk()
            ->assertSee('provided through the website mybooks.cloud')
            ->assertSee('https://mybooks.cloud');

        $this->get(route('terms-of-service'))->assertOk()
            ->assertSee('accessible at mybooks.cloud')
            ->assertSee('https://mybooks.cloud');
    }
}
