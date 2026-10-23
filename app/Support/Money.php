<?php

namespace App\Support;

class Money
{
    public static function format(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $paise = abs($paise);

        return $sign.'₹'.number_format(intdiv($paise, 100)).'.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function rupeesToPaise(string $rupees): ?int
    {
        if (preg_match('/^\d{1,8}(\.\d{1,2})?$/', $rupees) !== 1) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $rupees, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }
}
