<?php

namespace App\Services\BlogStudio;

class ArticleExcerpt
{
    public const MIN = 160;

    public const MAX = 200;

    public const MESSAGE_EMPTY = 'Add an excerpt of 160–200 characters.';

    public const MESSAGE_SHORT = 'The excerpt needs at least 160 characters (currently :count).';

    public const MESSAGE_LONG = 'The excerpt must be 200 characters or fewer (currently :count).';

    public static function publishProblem(?string $excerpt): ?string
    {
        $length = mb_strlen(trim(strip_tags((string) $excerpt)));

        return match (true) {
            $length === 0 => self::MESSAGE_EMPTY,
            $length < self::MIN => str_replace(':count', (string) $length, self::MESSAGE_SHORT),
            $length > self::MAX => str_replace(':count', (string) $length, self::MESSAGE_LONG),
            default => null,
        };
    }

    /**
     * @return array{min: int, max: int, messages: array{empty: string, short: string, long: string}}
     */
    public static function clientRules(): array
    {
        return [
            'min' => self::MIN,
            'max' => self::MAX,
            'messages' => [
                'empty' => self::MESSAGE_EMPTY,
                'short' => self::MESSAGE_SHORT,
                'long' => self::MESSAGE_LONG,
            ],
        ];
    }
}
