<?php

namespace Tests\Feature\Public;

use App\Models\Plan;
use App\Support\PublicSite\LiveFeatures;
use App\Support\PublicSite\PublicPlans;
use Tests\TestCase;

/**
 * Home page plan, session H2: the shared public layout, prices read from the
 * plans table, and the check for which optional features are really live.
 */
class PublicFoundationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function seedPlans(): void
    {
        Plan::factory()->create([
            'name' => 'Starter', 'slug' => 'starter', 'description' => 'For a small business.',
            'monthly_price' => 25000, 'annual_price' => 250000, 'sort_order' => 1,
            'sms_monthly_limit' => 100, 'whatsapp_monthly_limit' => 50,
            'features' => ['Up to 5 users', 'Invoicing & Billing'],
        ]);
        Plan::factory()->create([
            'name' => 'Professional', 'slug' => 'professional', 'description' => 'For a growing business.',
            'monthly_price' => 50000, 'annual_price' => 500000, 'allow_monthly_billing' => false, 'sort_order' => 2,
            'features' => ['Up to 15 users'],
        ]);
        Plan::factory()->inactive()->create(['name' => 'Old plan', 'slug' => 'old', 'sort_order' => 3]);
    }

    public function test_plans_come_from_the_table(): void
    {
        $this->seedPlans();

        $plans = app(PublicPlans::class)->all();

        $this->assertSame(['starter', 'professional'], $plans->pluck('slug')->all());
        $starter = $plans->first();
        $this->assertSame(25000.0, $starter['monthly']);
        $this->assertSame(250000.0, $starter['annual']);
        $this->assertSame(17, $starter['savings_percent']);
        $this->assertStringContainsString('plan=starter', $starter['annual_url']);
        $this->assertStringContainsString('cycle=annual', $starter['annual_url']);

        $pro = $plans->last();
        $this->assertNull($pro['monthly']);
        $this->assertTrue($pro['annual_only']);
        $this->assertNull($pro['monthly_url']);
    }

    public function test_pricing_shows_table_prices_and_follows_changes(): void
    {
        $this->seedPlans();

        $this->blade('<x-public.pricing highlight="professional" highlight-label="Best for a growing business" />')
            ->assertSee('₦250,000')
            ->assertSee('a year (₦20,833 a month)')
            ->assertSee('₦25,000')
            ->assertSee('a year, paid yearly only')
            ->assertSee('Choose Starter')
            ->assertSee('Choose Professional')
            ->assertSee('Best for a growing business')
            ->assertSee('Invoicing &amp; Billing', false)
            ->assertSee('Monthly')
            ->assertSee('Yearly')
            ->assertDontSee('Old plan');

        Plan::where('slug', 'starter')->update(['annual_price' => 240000]);

        $this->blade('<x-public.pricing />')->assertSee('₦240,000')->assertDontSee('₦250,000');
    }

    public function test_message_allowances_show_only_when_messages_are_really_sent(): void
    {
        $this->seedPlans();
        config(['services.termii.api_key' => null, 'services.termii.whatsapp_device_id' => null, 'services.whatsapp_meta.token' => null]);

        $this->blade('<x-public.pricing />')->assertDontSee('SMS')->assertDontSee('WhatsApp');

        config(['mybooks.features.sms_whatsapp' => true, 'mybooks.messaging.sms_driver' => 'auto', 'services.termii.api_key' => 'test-key']);

        $this->blade('<x-public.pricing />')->assertSee('100 SMS a month')->assertDontSee('WhatsApp');
    }

    public function test_pricing_with_no_plans_points_to_contact(): void
    {
        $this->blade('<x-public.pricing />')->assertSee('contact us');
    }

    public function test_live_features_need_the_switch_and_the_keys(): void
    {
        config([
            'mybooks.features.sms_whatsapp' => true, 'mybooks.messaging.sms_driver' => 'auto', 'mybooks.messaging.whatsapp_driver' => 'auto',
            'services.termii.api_key' => null, 'services.termii.whatsapp_device_id' => null,
            'services.whatsapp_meta.token' => null, 'services.whatsapp_meta.phone_number_id' => null,
            'mybooks.features.bank_feeds' => true, 'services.mono.secret_key' => null,
            'mybooks.features.e_invoicing' => false, 'mybooks.einvoicing.base_urls.live' => 'https://nrs.example',
        ]);
        $live = app(LiveFeatures::class);
        $this->assertFalse($live->sms());
        $this->assertFalse($live->whatsapp());
        $this->assertFalse($live->bankFeeds());
        $this->assertFalse($live->eInvoicing());

        config(['services.termii.api_key' => 'k', 'services.whatsapp_meta.token' => 't', 'services.whatsapp_meta.phone_number_id' => 'p',
            'services.mono.secret_key' => 's', 'mybooks.features.e_invoicing' => true]);
        $this->assertTrue($live->sms());
        $this->assertTrue($live->whatsapp());
        $this->assertTrue($live->bankFeeds());
        $this->assertTrue($live->eInvoicing());

        config(['mybooks.features.sms_whatsapp' => false, 'mybooks.features.bank_feeds' => false]);
        $this->assertFalse($live->sms());
        $this->assertFalse($live->whatsapp());
        $this->assertFalse($live->bankFeeds());
    }

    public function test_shared_layout_has_the_top_bar_footer_and_dark_mode(): void
    {
        $view = $this->blade('<x-public.layout title="Pricing"><p>Body text</p></x-public.layout>');

        $view->assertSee('<title>Pricing · MyBooks</title>', false)
            ->assertSee('data-theme-init', false)
            ->assertSee('Skip to the page')
            ->assertSee('Body text')
            ->assertSee('What it does')
            ->assertSee('Nigerian tax')
            ->assertSee('For accountants')
            ->assertSee('Choose a plan')
            ->assertSee('Sign in')
            ->assertSee('Privacy policy')
            ->assertSee('Terms of service')
            // Pricing and the guides are pages of their own now (SEO plan).
            ->assertSee(route('pricing'), false)
            ->assertSee(route('guides.index'), false)
            ->assertDontSee('Get Started');
    }

    public function test_naira_format(): void
    {
        $this->assertSame('₦25,000', PublicPlans::naira(25000));
        $this->assertSame('₦12,500.50', PublicPlans::naira(12500.5));
    }
}
