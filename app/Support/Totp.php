<?php

namespace App\Support;

/**
 * Minimal RFC 6238 TOTP implementation (SHA-1, 6 digits, 30s step) — enough
 * to enroll and verify an authenticator app without pulling in a dependency.
 */
class Totp
{
    protected const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    protected const DIGITS = 6;

    protected const PERIOD = 30;

    /**
     * Generate a random Base32 secret (default 160 bits / 32 chars).
     */
    public static function generateSecret(int $length = 32): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * Build the otpauth:// provisioning URI for QR codes / manual entry.
     */
    public static function uri(string $secret, string $label, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer.':'.$label),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    /**
     * Verify a code against the secret, allowing +/- $window time steps for
     * clock drift.
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen((string) $code) !== self::DIGITS) {
            return false;
        }

        $counter = (int) floor(time() / self::PERIOD);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Current TOTP code for a secret (useful for tooling and tests).
     */
    public static function now(string $secret): string
    {
        return self::code($secret, (int) floor(time() / self::PERIOD));
    }

    /**
     * Compute the HOTP value for a given counter.
     */
    protected static function code(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $binCounter = pack('N*', 0).pack('N*', $counter);

        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    protected static function base32Decode(string $secret): string
    {
        $secret = rtrim(strtoupper($secret), '=');
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $index;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
