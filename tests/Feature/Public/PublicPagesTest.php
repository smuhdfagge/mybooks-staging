<?php

namespace Tests\Feature\Public;

use App\Models\Plan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Home page plan, session H4: every public page sits on the shared layout,
 * has no dead links, and offers the demo booking.
 */
class PublicPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function pages(): array
    {
        return [['home'], ['about'], ['contact'], ['support'], ['privacy-policy'], ['terms-of-service']];
    }

    #[DataProvider('pages')]
    public function test_page_uses_the_shared_layout_and_has_no_dead_links(string $route): void
    {
        $html = $this->get(route($route))->assertOk()
            ->assertSee('Skip to the page')
            ->assertSee('data-theme-init', false)
            ->assertSee('Nigerian tax')
            ->assertSee('Terms of service')
            ->assertDontSee('Get Started')
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/href="#"/', $html, "The {$route} page has a link that goes nowhere.");
        $this->assertSame(1, substr_count($html, '<h1'), "The {$route} page should have exactly one main heading.");
    }

    public function test_about_page_takes_the_user_limit_from_the_plans_table(): void
    {
        Plan::factory()->create(['slug' => 'small', 'max_users' => 7]);
        Plan::factory()->create(['slug' => 'big', 'max_users' => 30]);

        $this->get(route('about'))->assertSee('Up to 30')->assertDontSee('Up to 50');
    }

    public function test_contact_page_offers_the_demo(): void
    {
        $this->get(route('contact'))->assertOk()
            ->assertSee('Book a demo')
            ->assertSee(route('contact', ['subject' => 'demo']).'#contact-form', false)
            ->assertSee('<option value="demo"', false);
    }

    public function test_accessibility_fixes_from_the_go_live_check_stay_in(): void
    {
        // The opening scene holds a button, so it must not be one picture (role="img").
        $this->get(route('home'))->assertOk()
            ->assertDontSee('role="img" aria-label="Example: an invoice', false)
            ->assertSee('<p class="sr-only">Example: an invoice is sent', false);

        // The privacy policy's wide table can be scrolled from the keyboard.
        $this->get(route('privacy-policy'))->assertOk()
            ->assertSee('tabindex="0" role="region" aria-label="Sub-processors"', false);
    }
}
