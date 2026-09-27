<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Prevent actual emails and clear rate limiters between tests
        Mail::fake();
        RateLimiter::clear('contact_form');
    }

    public function test_contact_form_is_rate_limited(): void
    {
        $data = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'subject' => 'Test Subject',
            'message' => 'Test message content',
        ];

        // Contact form has throttle:5,1
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $data);
        }

        // The 6th request should be rate limited
        $response = $this->post('/contact', $data);
        $response->assertStatus(429);
    }

    public function test_register_is_rate_limited(): void
    {
        // Register has throttle:5,1
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'name' => 'Test User',
                'email' => "ratelimit{$i}@example.com",
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'company_name' => 'Test Co',
            ]);
        }

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'ratelimit99@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'Test Co',
        ]);

        $response->assertStatus(429);
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        // forgot-password has throttle:5,1
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', [
                'email' => 'test@example.com',
            ]);
        }

        $response = $this->post('/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(429);
    }
}
