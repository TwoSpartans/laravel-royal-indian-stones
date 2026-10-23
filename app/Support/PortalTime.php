<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

class PortalTime
{
    public static function timezone(?User $user): string
    {
        $timezone = $user?->profile?->location?->timezone ?? $user?->timezone ?? 'Asia/Kolkata';

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Asia/Kolkata';
    }

    public static function format(mixed $moment, ?User $user = null, ?string $timezone = null): string
    {
        if ($moment === null || $moment === '') {
            return '';
        }

        return Carbon::parse($moment)
            ->timezone($timezone ?: self::timezone($user))
            ->format('d M Y, H:i');
    }
}
