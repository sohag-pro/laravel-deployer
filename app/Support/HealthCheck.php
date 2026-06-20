<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Probes a URL after a release goes live so a broken deploy can be rolled
 * back automatically. A 2xx response counts as healthy; the probe is retried
 * to allow the application a moment to warm up.
 */
class HealthCheck
{
    public static function passes(string $url, int $retries = 5, int $delaySeconds = 3): bool
    {
        $retries = max(1, $retries);

        for ($attempt = 0; $attempt < $retries; $attempt++) {
            try {
                if (Http::timeout(10)->get($url)->successful()) {
                    return true;
                }
            } catch (Throwable) {
                // Connection refused / DNS / timeout — treat as not yet healthy.
            }

            if ($attempt < $retries - 1 && $delaySeconds > 0) {
                sleep($delaySeconds);
            }
        }

        return false;
    }
}
