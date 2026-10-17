<?php

namespace Tests\Feature\Public;

use App\Models\Plan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Home page plan, session H3: the new home page. Prices and limits follow
 * the plans table, optional features appear only while really live, and
 * "Book a demo" lands on the contact form with the subject picked.
 */
class HomePageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Nothing optional is live unless a test switches it on.
        config([
            'services.termii.api_key' => null, 'services.termii.whatsapp_device_id' => null,
            'services.whatsapp_meta.token' => null, 'services.whatsapp_meta.phone_number_id' => null,
            'services.mono.secret_key' => null,
            'mybooks.features.e_invoicing' => false,
        ]);
    }

    private function seedPlans(): void
    {
        Plan::factory()->create(['name' => 'Starter', 'slug' => 'starter', 'monthly_price' => 25000, 'annual_price' => 250000, 'max_users' => 5, 'sort_order' => 1, 'features' => ['Up to 5 users']]);
        Plan::factory()->create(['name' => 'Professional', 'slug' => 'professional', 'monthly_price' => 50000, 'annual_price' => 500000, 'allow_monthly_billing' => false, 'max_users' => 15, 'sort_order' => 2, 'features' => ['Up to 15 users']]);
        Plan::factory()->create(['name' => 'Enterprise', 'slug' => 'enterprise', 'monthly_price' => 150000, 'annual_price' => 1500000, 'max_users' => 50, 'sort_order' => 3, 'features' => ['Up to 50 users']]);
    }

    public function test_home_page_has_the_ten_sections(): void
    {
        $this->seedPlans();

        $this->get(route('home'))->assertOk()
            ->assertSee('Invoices out, money in, tax worked out.')
            ->assertSee('Built for Nigeria')
            ->assertSee('id="tour"', false)
            ->assertSee('Nigerian tax, already set up')
            ->assertSee('One set of books for you and your accountant')
            ->assertSee('Moving from Excel or another system')
            ->assertSee('Your books stay safe and private')
            ->assertSee('Plans in naira')
            ->assertSee('Questions people ask')
            ->assertSee('Ready to put your books in order?')
            ->assertSee('Example figures for a sample business.');
    }

    public function test_prices_and_limits_follow_the_plans_table(): void
    {
        $this->seedPlans();

        $this->get(route('home'))
            ->assertSee('₦250,000')
            ->assertSee('₦500,000')
            ->assertSee('₦1,500,000')
            ->assertSee('Up to 50 people on one account.')
            ->assertSee('Yearly costs the same as ten months.')
            ->assertSee('register?plan=starter&amp;cycle=annual', false);

        Plan::where('slug', 'starter')->update(['annual_price' => 240000]);
        Plan::where('slug', 'enterprise')->update(['max_users' => 40]);

        $this->get(route('home'))
            ->assertSee('₦240,000')
            ->assertDontSee('₦250,000')
            ->assertSee('Up to 40 people on one account.')
            // Starter is no longer exactly ten months, so the line goes.
            ->assertDontSee('ten months');
    }

    public function test_optional_features_stay_off_the_page_until_live(): void
    {
        $this->seedPlans();

        $response = $this->get(route('home'))->assertOk()
            ->assertSee('remind customers by email')
            ->assertSee('Being prepared');
        $text = strip_tags($response->getContent());
        $this->assertStringNotContainsString('WhatsApp', $text);
        $this->assertStringNotContainsString('SMS', $text);
        $this->assertStringNotContainsString('bank feeds', $text);
    }

    public function test_optional_features_show_once_switched_on_with_keys(): void
    {
        $this->seedPlans();
        config([
            'mybooks.features.sms_whatsapp' => true, 'mybooks.messaging.sms_driver' => 'auto', 'mybooks.messaging.whatsapp_driver' => 'auto',
            'services.termii.api_key' => 'k', 'services.termii.whatsapp_device_id' => 'd',
            'mybooks.features.bank_feeds' => true, 'services.mono.secret_key' => 's',
            'mybooks.features.e_invoicing' => true, 'mybooks.einvoicing.base_urls.live' => 'https://nrs.example',
        ]);

        $this->get(route('home'))
            ->assertSee('remind customers on WhatsApp or by SMS')
            ->assertSee('WhatsApp reminder, sent by MyBooks')
            ->assertSee('Payment reminders by email, SMS and WhatsApp')
            ->assertSee('Bank reconciliation with bank feeds')
            ->assertDontSee('Being prepared');
    }

    public function test_book_a_demo_opens_the_contact_form_with_the_subject_picked(): void
    {
        $this->get(route('home'))->assertSee(route('contact', ['subject' => 'demo']).'#contact-form', false);

        $this->get(route('contact', ['subject' => 'demo']))->assertOk()
            ->assertSee('<option value="demo" selected', false);

        Mail::fake();
        $this->post(route('contact.submit'), [
            'first_name' => 'Amina', 'last_name' => 'Bello', 'email' => 'amina@example.com',
            'subject' => 'demo', 'message' => 'Please show us MyBooks.',
        ])->assertSessionHasNoErrors();
    }
}
