<?php

namespace Tests\Feature;

use App\Support\HealthCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_it_passes_on_a_2xx_response(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $this->assertTrue(HealthCheck::passes('https://app.test/up', retries: 1, delaySeconds: 0));
    }

    public function test_it_fails_after_retries_on_error_responses(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->assertFalse(HealthCheck::passes('https://app.test/up', retries: 3, delaySeconds: 0));
        Http::assertSentCount(3);
    }

    public function test_it_fails_when_the_host_is_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('refused');
        });

        $this->assertFalse(HealthCheck::passes('https://app.test/up', retries: 2, delaySeconds: 0));
    }
}
