<?php

namespace App\Support;

final class VndAmountInput
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if (preg_match('/^\d+$/D', $value)) {
            return $value;
        }

        if (preg_match('/^\d{1,3}([., \x{00A0}])\d{3}(?:\1\d{3})*$/uD', $value)) {
            return preg_replace('/[., \x{00A0}]/u', '', $value);
        }

        return $value;
    }
}
