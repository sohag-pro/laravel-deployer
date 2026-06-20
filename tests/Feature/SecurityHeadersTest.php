<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present_on_responses(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_insecure_requests_are_redirected_when_force_https_is_on(): void
    {
        config(['deployer.force_https' => true]);

        $response = $this->get('http://localhost/login');

        $response->assertStatus(302);
        $this->assertStringStartsWith('https://', $response->headers->get('Location'));
    }

    public function test_https_is_not_forced_by_default(): void
    {
        $this->get('http://localhost/login')->assertOk();
    }
}
