<?php

namespace App\Math;

/**
 * Decimal math with bcmath when available, float fallback for dev containers.
 */
final class Bc
{
    public static function div(string $left, string $right, int $scale = 4): string
    {
        if (function_exists('bcdiv')) {
            return \bcdiv($left, $right, $scale);
        }

        if ((float) $right === 0.0) {
            return '0';
        }

        return number_format((float) $left / (float) $right, $scale, '.', '');
    }

    public static function add(string $left, string $right, int $scale = 2): string
    {
        if (function_exists('bcadd')) {
            return \bcadd($left, $right, $scale);
        }

        return number_format((float) $left + (float) $right, $scale, '.', '');
    }

    public static function sub(string $left, string $right, int $scale = 4): string
    {
        if (function_exists('bcsub')) {
            return \bcsub($left, $right, $scale);
        }

        return number_format((float) $left - (float) $right, $scale, '.', '');
    }

    public static function comp(string $left, string $right, int $scale = 4): int
    {
        if (function_exists('bccomp')) {
            return \bccomp($left, $right, $scale);
        }

        $l = (float) $left;
        $r = (float) $right;

        return $l <=> $r;
    }
}
