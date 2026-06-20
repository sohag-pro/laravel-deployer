<?php

namespace Tests\Unit;

use App\Console\Commands\Deploy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeployRefTest extends TestCase
{
    #[DataProvider('validRefs')]
    public function test_accepts_valid_refs(string $ref): void
    {
        $this->assertTrue(Deploy::isValidRef($ref));
    }

    public static function validRefs(): array
    {
        return [
            ['main'],
            ['release/v1.2.3'],
            ['v1.0.0'],
            ['feature/new-ui'],
            ['9f2a1c4'],
        ];
    }

    #[DataProvider('invalidRefs')]
    public function test_rejects_unsafe_refs(string $ref): void
    {
        $this->assertFalse(Deploy::isValidRef($ref));
    }

    public static function invalidRefs(): array
    {
        return [
            'empty' => [''],
            'semicolon' => ['foo;rm'],
            'space' => ['foo bar'],
            'backtick' => ['foo`id`'],
            'dotdot' => ['foo..bar'],
            'leading dash' => ['--upload-pack=x'],
            'dollar' => ['foo$(id)'],
        ];
    }
}
