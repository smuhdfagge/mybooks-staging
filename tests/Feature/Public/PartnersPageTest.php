<?php

namespace Tests\Feature\Public;

use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * The accountant partner programme page (Nigeria marketing plan, channel 1):
 * findable on its own, linked from every public page, sending accountants to
 * the contact form with the right subject, and promising only what MyBooks
 * does.
 */
class PartnersPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['mybooks.search_indexing' => true]);
    }

    public function test_the_partner_page_is_published_and_findable(): void
    {
        $html = $this->get(route('partners'))->assertOk()
            ->assertSee('MyBooks for accountants')
            ->assertSee('Become a partner')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'A page has exactly one h1.');
        $this->assertStringContainsString('<link rel="canonical" href="'.route('partners').'">', $html);
        $this->assertStringContainsString('<loc>'.route('partners').'</loc>', $this->get(route('sitemap'))->getContent());

        // Linked from the top bar and the footer of every public page.
        $about = $this->get(route('about'))->assertOk()->getContent();
        $footer = substr($about, (int) strrpos($about, '<footer'));
        $this->assertStringContainsString('href="'.route('partners').'"', $footer, 'The footer links to the partner page.');
        $this->assertStringContainsString('href="'.route('partners').'"', substr($about, 0, (int) strpos($about, '<main')), 'The top bar links to the partner page.');
    }

    public function test_it_sends_accountants_to_the_contact_form_as_a_partnership(): void
    {
        $this->get(route('partners'))->assertOk()
            ->assertSee(route('contact', ['subject' => 'partnership']).'#contact-form', false);

        $this->get(route('contact', ['subject' => 'partnership']))->assertOk()
            ->assertSee('<option value="partnership" selected', false);
    }

    public function test_it_promises_only_what_mybooks_does(): void
    {
        // "Your own login with the Accountant role ... post your adjustments".
        $this->seed(DatabaseSeeder::class);
        $accountant = Role::whereNull('tenant_id')->where('name', 'accountant')->firstOrFail();
        // "The ledgers, journals and financial reports" ... "post your adjustments".
        foreach (['view chart-of-accounts', 'view journals', 'post journals', 'view reports'] as $permission) {
            $this->assertTrue($accountant->hasPermissionTo($permission), $permission);
        }
        // Payroll is not the Accountant role's: the page says a client gives it
        // through a role of their own, and MyBooks runs the imports.
        $this->assertFalse($accountant->hasPermissionTo('view payroll'));
        $this->get(route('partners'))->assertOk()
            ->assertSee('a role they set up to include payroll too')
            ->assertSee('import their records for them');

        // It points at the 2026 guides, which exist.
        $this->get(route('partners'))->assertOk()
            ->assertSee(route('guides.show', 'nigeria-tax-act-small-business'), false)
            ->assertSee(route('guides.show', 'paye-nigeria'), false)
            ->assertSee(route('guides.show', 'nigeria-tax-calendar'), false);
        foreach (['nigeria-tax-act-small-business', 'paye-nigeria', 'nigeria-tax-calendar'] as $slug) {
            $this->get(route('guides.show', $slug))->assertOk();
        }
    }
}
