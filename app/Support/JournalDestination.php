<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;

final class JournalDestination
{
    /**
     * @var list<string>
     */
    private const KNOWN = [
        '/',
        '/products',
        '/contact',
        '/aboutus',
        '/project-gallery',
        '/faq',
        '/blog',
        '/natural-stones-of-india',
        '/natural-stone-care',
        '/quality-control',
        '/general-instruction',
        '/packaging-type',
        '/sitemap',
    ];

    public static function normalize(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        if ($path === '' || str_contains($path, '..') || str_contains($path, '//')) {
            return null;
        }
        if ($path !== '/' && preg_match('#^/[A-Za-z0-9_-]+$#', $path) !== 1) {
            return null;
        }

        return $path;
    }

    public static function exists(string $path): bool
    {
        $path = self::normalize($path);
        if ($path === null) {
            return false;
        }
        if (in_array($path, self::KNOWN, true)) {
            return true;
        }

        $slug = ltrim($path, '/');

        return Page::query()->where('slug', $slug)->exists()
            || Category::query()->where('slug', $slug)->exists()
            || Product::query()->published()->where('slug', $slug)->exists()
            || Post::query()->published()->where('slug', $slug)->exists();
    }
}
