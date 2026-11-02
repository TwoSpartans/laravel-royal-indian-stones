<?php

namespace App\Services\Sitemap;

final class SitemapUrl
{
    public static function page(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if ($path === '' || $path === '/') {
            return $base;
        }

        return $base.'/'.ltrim($path, '/');
    }

    public static function file(string $relative): string
    {
        $assetUrl = config('sitemap.asset_url');
        $base = is_string($assetUrl) && trim($assetUrl) !== ''
            ? rtrim($assetUrl, '/')
            : rtrim((string) config('app.url'), '/');
        $segments = array_map(
            rawurlencode(...),
            explode('/', ltrim(str_replace('\\', '/', $relative), '/')),
        );

        return $base.'/'.implode('/', $segments);
    }
}
