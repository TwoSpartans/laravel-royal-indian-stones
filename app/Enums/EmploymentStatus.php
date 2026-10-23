<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Probation = 'probation';
    case Inactive = 'inactive';
    case Offboarded = 'offboarded';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Probation => 'Probation',
            self::Inactive => 'Inactive',
            self::Offboarded => 'Offboarded',
        };
    }

    public function onRoster(): bool
    {
        return $this === self::Active || $this === self::Probation;
    }
}
