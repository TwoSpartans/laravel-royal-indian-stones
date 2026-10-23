<?php

namespace App\Enums;

enum NetworkResult: string
{
    case ApprovedOffice = 'approved_office';
    case OutsideNetwork = 'outside_network';
    case UnableToVerify = 'unable_to_verify';
    case RemoteAuthorized = 'remote_authorized';

    public function label(): string
    {
        return match ($this) {
            self::ApprovedOffice => 'approved office network',
            self::OutsideNetwork => 'outside approved network',
            self::UnableToVerify => 'unable to verify',
            self::RemoteAuthorized => 'approved remote or field work',
        };
    }
}
