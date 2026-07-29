<?php

namespace App\Math;

/**
 * Decimal math. Requires ext-bcmath — floating-point fallback is intentionally absent
 * so the same inputs produce the same decisions in every environment.
 */
final class Bc
{
    public static function div(string $left, string $right, int $scale = 4): string
    {
        self::assertBcmath();

        return \bcdiv($left, $right, $scale);
    }

    public static function add(string $left, string $right, int $scale = 2): string
    {
        self::assertBcmath();

        return \bcadd($left, $right, $scale);
    }

    public static function sub(string $left, string $right, int $scale = 4): string
    {
        self::assertBcmath();

        return \bcsub($left, $right, $scale);
    }

    public static function comp(string $left, string $right, int $scale = 4): int
    {
        self::assertBcmath();

        return \bccomp($left, $right, $scale);
    }

    private static function assertBcmath(): void
    {
        if (!\extension_loaded('bcmath')) {
            throw new \RuntimeException(
                'ext-bcmath is required for monetary arithmetic. Install the extension; silent float fallback is not allowed.',
            );
        }
    }
}
