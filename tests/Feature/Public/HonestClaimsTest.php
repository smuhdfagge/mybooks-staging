<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Home page plan, session H1: the public pages only say what is true.
 * A paid plan starts with a free trial and no card (owner's decision,
 * November 2026, mybooks.trial_days): the pages say so only while trials
 * are on. There are no confirmed
 * customer numbers or quotes, and MyBooks makes no GDPR, uptime or
 * daily-backup promise, no live status or help videos exist yet, and no
 * reply time is promised (H4). Add
 * a phrase back only once it is true.
 */
class HonestClaimsTest extends TestCase
{
    use RefreshDatabase;

    private const BANNED = [
        'no credit card', 'get started free', 'thousands of',
        'gdpr', 'bank-level', 'daily backups', 'uptime', '10k+', 'active users',
        'trusted by businesses', 'enterprise-grade', 'fraction of the cost',
        'all systems operational', 'video tutorials', 'never share your data', 'onboarding wizard',
        'within 24 hours', 'follow us', 'knowledge base',
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

    public function test_home_page_offers_the_free_trial_while_it_is_on(): void
    {
        config(['mybooks.trial_days' => 10]);

        $this->get(route('home'))->assertOk()
            ->assertSee('Start your free trial')
            ->assertSee('Free for 10 days, no card needed.')
            ->assertSee('Prices in naira. Pay through Paystack.')
            ->assertDontSee('Sirajo Muhammad');
    }

    #[DataProvider('pages')]
    public function test_with_trials_off_no_page_offers_one(string $route): void
    {
        config(['mybooks.trial_days' => 0]);

        $text = strtolower(strip_tags($this->get(route($route))->assertOk()->getContent()));

        $this->assertStringNotContainsString('free trial', $text);
        $this->assertStringNotContainsString('no card needed', $text);
        if ($route === 'home') {
            $this->assertStringContainsString('choose a plan', $text);
        }
    }
}
