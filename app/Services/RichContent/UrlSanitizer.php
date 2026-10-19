<?php

namespace App\Services\RichContent;

use Illuminate\Support\Str;

class UrlSanitizer
{
    public function sanitize(?string $url): ?string
    {
        $url = Str::of((string) $url)->trim()->toString();

        if ($url === '' || Str::startsWith($url, '//')) {
            return null;
        }

        if (Str::startsWith($url, '/')) {
            return $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
}
