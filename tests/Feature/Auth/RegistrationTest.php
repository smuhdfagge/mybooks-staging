<?php

namespace Tests\Feature\Auth;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $plan = Plan::factory()->create([
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
        ]);

        $response = $this->post('/register', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'company_name' => 'Test Company',
            'company_email' => 'company@example.com',
            'currency' => 'USD',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Str0ng-Pass!',
            'password_confirmation' => 'Str0ng-Pass!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
    }
}
