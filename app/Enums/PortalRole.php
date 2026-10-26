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
            self::AdminHr, self::Manager => 'Manager / HR',
            self::Employee => 'Employee',
        };
    }

    /**
     * The employer is the company admin. This is the only role that can revise or delete an agreed task.
     */
    public function isCompanyAdmin(): bool
    {
        return $this === self::Employer;
    }

    /**
     * Manager and Admin / HR are the same management level.
     */
    public function isManagerLevel(): bool
    {
        return $this === self::Manager || $this === self::AdminHr;
    }

    public function requiresMfa(): bool
    {
        return $this === self::Employer || $this === self::AdminHr;
    }

    public function canAdminister(): bool
    {
        return $this->isCompanyAdmin() || $this->isManagerLevel();
    }
}
