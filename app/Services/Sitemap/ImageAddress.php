<?php

namespace App\Services\Sitemap;

use App\Support\ServedUpload;

final class ImageAddress
{
    public function __construct(private ServedUpload $uploads) {}

    /**
     * @return array{url: ?string, reason: ?string}
     */
    public function decide(string $path, ?int $width = null, ?int $height = null): array
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = preg_replace('/#.*$/', '', $path) ?? $path;
        if ($path === '' || preg_match('/^(?:data|javascript|mailto):/i', $path) === 1) {
            return $this->reject(null, 'The image address is empty or not a file.');
        }

        if ($width !== null && $height !== null && ($width <= 1 || $height <= 1)) {
            return $this->reject($this->displayUrl($path), 'The image is a tracking pixel or placeholder.');
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $this->remote($path);
        }

        if (str_starts_with($path, '//')) {
            $scheme = str_starts_with(strtolower((string) config('app.url')), 'https://') ? 'https:' : 'http:';

            return $this->remote($scheme.$path);
        }

        $relative = ltrim($path, '/');
        $display = $this->localDisplay($relative);
        if ($this->decorative($relative) || ! $this->extensionAllowed($relative)) {
            return $this->reject($display, 'The image is an icon, placeholder, or unsupported file.');
        }

        $absolute = $this->uploads->absolute($relative);
        if ($absolute === null && is_file(public_path($relative))) {
            $absolute = public_path($relative);
        }
        if ($absolute === null || ! is_file($absolute)) {
            return $this->reject($display, 'The image file is missing.');
        }

        if ($width === null || $height === null) {
            $size = @getimagesize($absolute);
            if (! is_array($size) || $size[0] <= 1 || $size[1] <= 1) {
                return $this->reject($display, 'The image file could not be read or is a placeholder.');
            }
        }

        return ['url' => $display, 'reason' => null];
    }

    /**
     * @return array{url: ?string, reason: ?string}
     */
    private function remote(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $this->reject(null, 'The image address is not a public URL.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $siteIsHttps = str_starts_with(strtolower((string) config('app.url')), 'https://');
        if ($scheme !== 'https' && ! ($scheme === 'http' && ! $siteIsHttps)) {
            return $this->reject($url, 'The image address is not an HTTPS URL.');
        }

        $path = (string) ($parts['path'] ?? '');
        if ($this->decorative($path) || ! $this->extensionAllowed($path)) {
            return $this->reject($url, 'The image is an icon, placeholder, or unsupported file.');
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            foreach (array_keys($query) as $key) {
                $name = strtolower((string) $key);
                if (str_starts_with($name, 'utm_') || in_array($name, ['gclid', 'fbclid', 'mc_eid', 'yclid'], true)) {
                    unset($query[$key]);
                }
            }
        }

        $rebuilt = $scheme.'://'.$parts['host'];
        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }
        $rebuilt .= $path;
        if ($query !== []) {
            $rebuilt .= '?'.http_build_query($query);
        }

        return ['url' => $rebuilt, 'reason' => null];
    }

    private function localDisplay(string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return SitemapUrl::file($relative);
    }

    private function displayUrl(string $path): ?string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return $this->localDisplay(ltrim($path, '/'));
    }

    private function extensionAllowed(string $path): bool
    {
        $bare = strtolower((string) (parse_url($path, PHP_URL_PATH) ?: $path));

        return preg_match('/\.(?:jpe?g|png|gif|webp|avif)$/', $bare) === 1;
    }

    private function decorative(string $path): bool
    {
        $lower = strtolower($path);
        if (preg_match('/\.(?:svg|ico|bmp|cur)(?:$|\?)/', $lower) === 1) {
            return true;
        }

        foreach (['favicon', 'apple-touch-icon', 'sprite', 'placeholder', 'spacer', '/pixel', 'tracking', '1x1', '/icons/', '/icon/', '/vendor/'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{url: ?string, reason: string}
     */
    private function reject(?string $url, string $reason): array
    {
        return ['url' => $url, 'reason' => $reason];
    }
}
