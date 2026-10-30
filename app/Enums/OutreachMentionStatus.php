<?php

namespace App\Enums;

enum OutreachMentionStatus: string
{
    case ToContact = 'to_contact';
    case Sent = 'sent';
    case Published = 'published';
    case Passed = 'passed';

    public function label(): string
    {
        return match ($this) {
            self::ToContact => 'To contact',
            self::Sent => 'Sent',
            self::Published => 'Published',
            self::Passed => 'Passed',
        };
    }
}
