<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_a_freshly_generated_code_verifies(): void
    {
        $secret = Totp::generateSecret();

        $this->assertTrue(Totp::verify($secret, Totp::now($secret)));
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $secret = Totp::generateSecret();

        $this->assertFalse(Totp::verify($secret, '000000'));
        $this->assertFalse(Totp::verify($secret, 'not-a-code'));
    }

    public function test_secret_is_base32_and_uri_is_well_formed(): void
    {
        $secret = Totp::generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertStringContainsString('otpauth://totp/', Totp::uri($secret, 'admin@example.com', 'Deployer'));
    }
}
