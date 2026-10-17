<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Home page plan, session H1: the public pages only say what is true.
 * Sign-up asks for payment first (no trial), there are no confirmed
 * customer numbers or quotes, and MyBooks makes no GDPR, uptime or
 * daily-backup promise, and no live status or help videos exist yet. Add
 * a phrase back only once it is true.
 */
class HonestClaimsTest extends TestCase
{
    use RefreshDatabase;

    private const BANNED = [
        'free trial', 'no credit card', 'get started free', 'thousands of',
        'gdpr', 'bank-level', 'daily backups', 'uptime', '10k+', 'active users',
        'trusted by businesses', 'enterprise-grade', 'fraction of the cost',
        'all systems operational', 'video tutorials', 'never share your data', 'onboarding wizard',
    ];

    public static function pages(): array
    {
        return [['home'], ['about'], ['contact'], ['support'], ['privacy-policy'], ['terms-of-service']];
    }

    #[DataProvider('pages')]
    public function test_public_page_makes_no_unproven_claims(string $route): void
    {
        $text = strtolower(strip_tags($this->get(route($route))->assertOk()->getContent()));

        foreach (self::BANNED as $phrase) {
            $this->assertStringNotContainsString($phrase, $text, "The {$route} page says \"{$phrase}\".");
        }
    }

    public function test_home_page_offers_a_plan_not_a_trial(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('Choose a plan')
            ->assertSee('Prices in naira. Pay through Paystack.')
            ->assertDontSee('Sirajo Muhammad');
    }
}
