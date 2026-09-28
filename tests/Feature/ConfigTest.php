<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfigTest extends TestCase
{
    // ── mybooks.support_email ───────────────────────────────────

    public function test_support_email_has_default(): void
    {
        $this->assertNotEmpty(config('mybooks.support_email'));
    }

    // ── mybooks.import_max_file_size ────────────────────────────

    public function test_import_max_file_size_has_default(): void
    {
        $this->assertEquals(10240, config('mybooks.import_max_file_size'));
    }

    // ── mybooks.currencies ──────────────────────────────────────

    public function test_currencies_config_returns_array(): void
    {
        $currencies = config('mybooks.currencies');

        $this->assertIsArray($currencies);
        $this->assertNotEmpty($currencies);
    }

    public function test_currencies_have_required_keys(): void
    {
        $currencies = config('mybooks.currencies');

        foreach ($currencies as $currency) {
            $this->assertArrayHasKey('code', $currency);
            $this->assertArrayHasKey('name', $currency);
            $this->assertArrayHasKey('symbol', $currency);
        }
    }

    public function test_currency_symbol_resolved_from_config(): void
    {
        $this->createAuthenticatedUser();

        $this->tenant->update(['currency' => 'USD']);
        $this->assertEquals('$', $this->tenant->fresh()->currency_symbol);

        $this->tenant->update(['currency' => 'EUR']);
        $this->assertEquals('€', $this->tenant->fresh()->currency_symbol);

        $this->tenant->update(['currency' => 'GBP']);
        $this->assertEquals('£', $this->tenant->fresh()->currency_symbol);
    }

    public function test_unknown_currency_falls_back_to_code(): void
    {
        $this->createAuthenticatedUser();

        $this->tenant->update(['currency' => 'XYZ']);
        $this->assertEquals('XYZ', $this->tenant->fresh()->currency_symbol);
    }
}
