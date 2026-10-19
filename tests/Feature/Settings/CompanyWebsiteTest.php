<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;

/**
 * Settings > Company: the website is optional, "www.example.com" is enough,
 * and typing "/" in a box no longer jumps to the search bar.
 */
class CompanyWebsiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createAuthenticatedUser(['view settings', 'edit settings']);
    }

    private function save(?string $website): void
    {
        $this->from(route('settings.company'))->post(route('settings.company.update'), [
            'name' => 'Our Family Farm Ltd', 'email' => 'farm@example.com', 'website' => $website,
        ])->assertSessionHasNoErrors()->assertRedirect(route('settings.company'));
    }

    public function test_website_is_optional_and_https_is_added_when_left_out(): void
    {
        $this->save('www.ourfamilyfarm.ng');
        $this->assertSame('https://www.ourfamilyfarm.ng', $this->tenant->fresh()->website);

        $this->save('http://ourfamilyfarm.ng');
        $this->assertSame('http://ourfamilyfarm.ng', $this->tenant->fresh()->website);

        $this->save('');
        $this->assertNull($this->tenant->fresh()->website);
    }

    public function test_the_page_says_the_website_is_optional_and_lets_any_text_be_typed(): void
    {
        $html = $this->get(route('settings.company'))->assertOk()
            ->assertSee('Website (optional)')
            ->getContent();

        $this->assertMatchesRegularExpression('/<input type="text" name="website"/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="website"[^>]*required/', $html);
    }

    public function test_slash_only_jumps_to_search_when_not_typing(): void
    {
        $html = $this->get(route('settings.company'))->assertOk()->getContent();

        $this->assertStringContainsString("closest('input, textarea, select", $html);
        $this->assertStringNotContainsString('@keydown.slash.window.prevent', $html);
    }
}
