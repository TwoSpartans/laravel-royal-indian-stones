<?php

namespace App\Services\Sitemap;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides which record owns a public slug.
 *
 * The catch-all page resolves a product, then a category, then a fixed
 * page, then a CMS page, then a journal article. Real routes such as
 * /products win before that catch-all.
 */
final class SitemapOwnership
{
    /**
     * @param  array<string, true>  $products
     * @param  array<string, true>  $categories
     * @param  array<string, true>  $pages
     * @param  array<string, true>  $posts
     * @param  array<string, true>  $blocked
     * @param  array<string, true>  $resolved
     */
    private function __construct(
        private array $products,
        private array $categories,
        private array $pages,
        private array $posts,
        private array $blocked,
        private array $resolved,
    ) {}

    public static function load(): self
    {
        $blocked = [];
        foreach (config('sitemap.blocked_slugs', []) as $slug) {
            $blocked[strtolower((string) $slug)] = true;
        }
        foreach (config('sitemap.routed_pages', []) as $path => $label) {
            unset($label);
            $slug = trim((string) $path, '/');
            if ($slug !== '') {
                $blocked[$slug] = true;
            }
        }

        $resolved = [];
        foreach (config('sitemap.resolved_pages', []) as $path => $label) {
            unset($label);
            $resolved[trim((string) $path, '/')] = true;
        }

        return new self(
            self::slugs(Product::query()->published()),
            self::slugs(Category::query()),
            self::slugs(Page::query()->where('is_indexable', true)),
            self::slugs(Post::query()->published()),
            $blocked,
            $resolved,
        );
    }

    /**
     * @param  Builder<Product>|Builder<Category>|Builder<Page>|Builder<Post>  $query
     * @return array<string, true>
     */
    private static function slugs(Builder $query): array
    {
        $slugs = [];
        foreach ($query->pluck('slug') as $slug) {
            $slugs[(string) $slug] = true;
        }

        return $slugs;
    }

    public function productIsCanonical(string $slug): bool
    {
        return ! $this->caseRedirects($slug) && ! $this->routeOwns($slug);
    }

    public function categoryIsCanonical(string $slug): bool
    {
        return ! $this->caseRedirects($slug)
            && ! $this->routeOwns($slug)
            && ! isset($this->products[$slug]);
    }

    public function resolvedIsCanonical(string $slug): bool
    {
        return ! $this->caseRedirects($slug)
            && ! $this->routeOwns($slug)
            && ! isset($this->products[$slug])
            && ! isset($this->categories[$slug]);
    }

    public function pageIsCanonical(string $slug): bool
    {
        return $this->resolvedIsCanonical($slug) && ! isset($this->resolved[$slug]);
    }

    public function postIsCanonical(string $slug): bool
    {
        return $this->pageIsCanonical($slug) && ! isset($this->pages[$slug]);
    }

    public function blocker(string $slug): string
    {
        if ($this->caseRedirects($slug)) {
            return 'This spelling redirects to the public address.';
        }
        if ($this->routeOwns($slug)) {
            return 'A public route already uses this address.';
        }
        if (isset($this->products[$slug])) {
            return 'A published product uses this address.';
        }
        if (isset($this->categories[$slug])) {
            return 'A category uses this address.';
        }
        if (isset($this->resolved[$slug])) {
            return 'A public page already uses this address.';
        }
        if (isset($this->pages[$slug])) {
            return 'A published page uses this address.';
        }

        return 'This address is not the canonical page.';
    }

    private function routeOwns(string $slug): bool
    {
        return isset($this->blocked[strtolower($slug)]);
    }

    private function caseRedirects(string $slug): bool
    {
        $lower = strtolower($slug);
        if ($lower === $slug) {
            return false;
        }

        return isset($this->blocked[$lower]) || isset($this->resolved[$lower]);
    }
}
