<?php

namespace App\Enums;

enum PortalRole: string
{
    case Employer = 'employer';
    case AdminHr = 'admin_hr';
    case Manager = 'manager';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Employer => 'Employer',
            self::AdminHr => 'Admin / HR',
            self::Manager => 'Manager',
            self::Employee => 'Employee',
        };
    }

    public function requiresMfa(): bool
    {
        return $this === self::Employer || $this === self::AdminHr;
    }

    public function canAdminister(): bool
    {
        return $this === self::Employer || $this === self::AdminHr || $this === self::Manager;
    }
}
