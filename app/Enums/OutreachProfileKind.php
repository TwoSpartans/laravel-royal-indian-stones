<?php

namespace App\Enums;

enum OutreachProfileKind: string
{
    case GoogleBusiness = 'google_business';
    case CompanyProfile = 'company_profile';
    case TradeDirectory = 'trade_directory';

    public function label(): string
    {
        return match ($this) {
            self::GoogleBusiness => 'Google Business Profile',
            self::CompanyProfile => 'Company profile',
            self::TradeDirectory => 'Trade directory',
        };
    }
}
