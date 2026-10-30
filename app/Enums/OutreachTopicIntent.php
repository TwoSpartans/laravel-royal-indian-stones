<?php

namespace App\Enums;

enum OutreachTopicIntent: string
{
    case Buy = 'buy';
    case Specify = 'specify';
    case Learn = 'learn';

    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Buy',
            self::Specify => 'Specify',
            self::Learn => 'Learn',
        };
    }
}
