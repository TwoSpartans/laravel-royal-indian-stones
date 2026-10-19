<?php

namespace App\Services\BlogStudio;

class ArticleExcerpt
{
    public const MIN = 40;

    public const MAX = 200;

    public static function publishProblem(?string $excerpt): ?string
    {
        $length = mb_strlen(trim((string) $excerpt));

        if ($length < self::MIN) {
            return 'The excerpt must be at least '.self::MIN.' characters before the article can be published.';
        }

        if ($length > self::MAX) {
            return 'The excerpt must be '.self::MAX.' characters or fewer.';
        }

        return null;
    }
}
