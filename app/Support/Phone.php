<?php

namespace App\Support;

class Phone
{
    /** Normalises Nigerian numbers to +234XXXXXXXXXX; leaves other international numbers as +digits. */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '234')) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+234'.substr($digits, 1);
        }
        if (strlen($digits) === 10 && ! str_starts_with(trim($phone), '+')) {
            return '+234'.$digits;
        }

        return '+'.$digits;
    }

    public static function looksLikePhone(string $value): bool
    {
        return (bool) preg_match('/^\+?[\d\s\-()]{7,}$/', trim($value));
    }

    public static function display(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        if (str_starts_with($phone, '+234') && strlen($phone) === 14) {
            return '0'.substr($phone, 4, 3).' '.substr($phone, 7, 3).' '.substr($phone, 10);
        }

        return $phone;
    }
}
