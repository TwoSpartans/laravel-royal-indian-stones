<?php

namespace App\Services\Sitemap;

use App\Enums\SitemapGroup;
use App\Models\Category;
use App\Models\Faq;
use App\Models\HomeImage;
use App\Models\HomeSection;
use App\Models\JournalMedia;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Collects the canonical addresses the public site already serves.
 *
 * Categories are a single list, not a department tree. A category address
 * is /{slug}, the same address the catch-all route renders.
 */
class SitemapCatalog
{
    /** @var array<string, true> */
    private array $seenExclusions = [];

    public function __construct(private ImageAddress $images) {}

    public function collect(SitemapGroup $group): SitemapSnapshot
    {
        $this->seenExclusions = [];
        $ownership = SitemapOwnership::load();

        return match ($group) {
            SitemapGroup::Categories => $this->categories($ownership),
            SitemapGroup::Products => $this->products($ownership),
            SitemapGroup::Pages => $this->pages($ownership),
            SitemapGroup::Journal => $this->journal($ownership),
            SitemapGroup::Images => $this->imagePages($ownership),
        };
    }

    private function categories(SitemapOwnership $ownership): SitemapSnapshot
    {
        $entries = [];
        $exclusions = [];
        Category::query()
            ->select(['id', 'slug', 'updated_at'])
            ->chunkById(200, function ($categories) use ($ownership, &$entries, &$exclusions): void {
                foreach ($categories as $category) {
                    $loc = SitemapUrl::page('/'.$category->slug);
                    if (! $ownership->categoryIsCanonical($category->slug)) {
                        $this->exclude($exclusions, $loc, $ownership->blocker($category->slug));

                        continue;
                    }
                    $this->put($entries, $loc, $category->updated_at);
                }
            });

        return new SitemapSnapshot($this->sorted($entries), $exclusions);
    }

    private function products(SitemapOwnership $ownership): SitemapSnapshot
    {
        $entries = [];
        $exclusions = [];
        Product::query()
            ->published()
            ->select(['id', 'slug', 'updated_at'])
            ->chunkById(200, function ($products) use ($ownership, &$entries, &$exclusions): void {
                foreach ($products as $product) {
                    $loc = SitemapUrl::page('/'.$product->slug);
                    if (! $ownership->productIsCanonical($product->slug)) {
                        $this->exclude($exclusions, $loc, $ownership->blocker($product->slug));

                        continue;
                    }
                    $this->put($entries, $loc, $product->updated_at);
                }
            });

        return new SitemapSnapshot($this->sorted($entries), $exclusions);
    }

    private function pages(SitemapOwnership $ownership): SitemapSnapshot
    {
        [$entries, $exclusions] = $this->fixedPages($ownership);
        Page::query()
            ->select(['id', 'slug', 'updated_at', 'is_indexable'])
            ->chunkById(200, function ($pages) use ($ownership, &$entries, &$exclusions): void {
                foreach ($pages as $page) {
                    $loc = SitemapUrl::page('/'.$page->slug);
                    if (! $page->is_indexable) {
                        $reason = $ownership->pageIsCanonical($page->slug)
                            ? 'This page is marked noindex.'
                            : $ownership->blocker($page->slug);
                        $this->exclude($exclusions, $loc, $reason);

                        continue;
                    }
                    if (! $ownership->pageIsCanonical($page->slug)) {
                        $this->exclude($exclusions, $loc, $ownership->blocker($page->slug));

                        continue;
                    }
                    $this->put($entries, $loc, $page->updated_at);
                }
            });

        return new SitemapSnapshot($this->sorted($entries), $exclusions);
    }

    private function journal(SitemapOwnership $ownership): SitemapSnapshot
    {
        $entries = [];
        $exclusions = [];
        Post::query()
            ->published()
            ->select(['id', 'slug', 'updated_at'])
            ->chunkById(200, function ($posts) use ($ownership, &$entries, &$exclusions): void {
                foreach ($posts as $post) {
                    $loc = SitemapUrl::page('/'.$post->slug);
                    if (! $ownership->postIsCanonical($post->slug)) {
                        $this->exclude($exclusions, $loc, $ownership->blocker($post->slug));

                        continue;
                    }
                    $this->put($entries, $loc, $post->updated_at);
                }
            });

        return new SitemapSnapshot($this->sorted($entries), $exclusions);
    }

    private function imagePages(SitemapOwnership $ownership): SitemapSnapshot
    {
        /** @var array<string, array{lastmod: ?CarbonInterface, images: array<string, true>}> $pages */
        $pages = [];
        $exclusions = [];
        $limit = max(1, (int) config('sitemap.max_images_per_url'));

        $this->productImages($ownership, $pages, $exclusions);
        $this->categoryImages($ownership, $pages, $exclusions);
        $this->cmsImages($ownership, $pages, $exclusions);
        $this->postImages($ownership, $pages, $exclusions);
        $this->homeImages($pages, $exclusions);
        $this->projectImages($ownership, $pages, $exclusions);

        $entries = [];
        foreach ($pages as $loc => $page) {
            $images = array_keys($page['images']);
            if (count($images) > $limit) {
                foreach (array_slice($images, $limit) as $dropped) {
                    $this->exclude($exclusions, $dropped, 'This page already lists the maximum of '.$limit.' images.');
                }
                $images = array_slice($images, 0, $limit);
            }
            sort($images);
            if ($images === []) {
                continue;
            }
            $entries[$loc] = new SitemapEntry($loc, $page['lastmod'], $images);
        }

        return new SitemapSnapshot($this->sorted($entries), $exclusions);
    }

    /**
     * @return array{0: array<string, SitemapEntry>, 1: list<array{loc: string, reason: string}>}
     */
    private function fixedPages(SitemapOwnership $ownership): array
    {
        $products = $this->stamp(Product::query()->published()->max('updated_at'));
        $categories = $this->stamp(Category::query()->max('updated_at'));
        $posts = $this->stamp(Post::query()->published()->max('updated_at'));
        $pages = $this->stamp(Page::query()->max('updated_at'));
        $home = $this->newer(
            $this->stamp(HomeSection::query()->max('updated_at')),
            $this->stamp(HomeImage::query()->max('updated_at')),
        );
        $catalogue = $this->newer($products, $categories);
        $content = $this->newer($this->newer($catalogue, $pages), $posts);

        $entries = [];
        $exclusions = [];
        $routed = [
            '/' => $home,
            '/products' => $products,
            '/e-catalogue' => $catalogue,
            '/contact' => null,
        ];
        foreach ($routed as $path => $lastmod) {
            $this->put($entries, SitemapUrl::page($path), $lastmod);
        }

        $resolved = [
            '/natural-stones-of-india' => $categories,
            '/aboutus' => null,
            '/project-gallery' => $this->stamp(Project::query()->max('updated_at')),
            '/faq' => $this->stamp(Faq::query()->max('updated_at')),
            '/blog' => $posts,
            '/sitemap' => $content,
        ];
        foreach (config('sitemap.resolved_pages', []) as $path => $label) {
            unset($label);
            $path = (string) $path;
            $slug = trim($path, '/');
            $loc = SitemapUrl::page($path);
            if (! $ownership->resolvedIsCanonical($slug)) {
                $this->exclude($exclusions, $loc, $ownership->blocker($slug));

                continue;
            }
            $this->put($entries, $loc, $resolved[$path] ?? null);
        }

        return [$entries, $exclusions];
    }

    /**
     * @param  array<string, array{lastmod: ?CarbonInterface, images: array<string, true>}>  $pages
     * @param  list<array{loc: string, reason: string}>  $exclusions
     */
    private function productImages(SitemapOwnership $ownership, array &$pages, array &$exclusions): void
    {
        Product::query()
            ->published()
            ->select(['id', 'slug', 'updated_at'])
            ->with(['images' => fn ($query) => $query->orderBy('position')->orderBy('id')])
            ->chunkById(100, function ($products) use ($ownership, &$pages, &$exclusions): void {
                foreach ($products as $product) {
                    if (! $ownership->productIsCanonical($product->slug)) {
                        continue;
                    }
                    $loc = SitemapUrl::page('/'.$product->slug);
                    foreach ($product->images as $image) {
                        $this->addImage($pages, $exclusions, $loc, $product->updated_at, $image->updated_at, (string) $image->path, $image->width, $image->height);
                    }
                }
            });
    }

    /**
     * @param  array<string, array{lastmod: ?CarbonInterface, images: array<string, true>}>  $pages
     * @param  list<array{loc: string, reason: string}>  $exclusions
     */
    private function categoryImages(SitemapOwnership $ownership, array &$pages, array &$exclusions): void
    {
        $landings = [SitemapUrl::page('/e-catalogue')];
        if ($ownership->resolvedIsCanonical('natural-stones-of-india')) {
            $landings[] = SitemapUrl::page('/natural-stones-of-india');
        }

        Category::query()
            ->select(['id', 'slug', 'image', 'updated_at'])
            ->whereNotNull('image')
            ->chunkById(200, function ($categories) use ($ownership, $landings, &$pages, &$exclusions): void {
                foreach ($categories as $category) {
                    if (! is_string($category->image) || trim($category->image) === '') {
                        continue;
                    }
                    $targets = $landings;
                    if ($ownership->categoryIsCanonical($category->slug)) {
                        $targets[] = SitemapUrl::page('/'.$category->slug);
                    }
                    foreach ($targets as $loc) {
                        $this->addImage($pages, $exclusions, $loc, $category->updated_at, null, $category->image, null, null);
                    }
                }
            });
    }

    /**
     * @param  array<string, array{lastmod: ?CarbonInterface, images: array<string, true>}>  $pages
     * @param  list<array{loc: string, reason: string}>  $exclusions
     */
    private function cmsImages(SitemapOwnership $ownership, array &$pages, array &$exclusions): void
    {
        Page::query()
            ->where('is_indexable', true)
            ->select(['id', 'slug', 'body', 'updated_at'])
            ->chunkById(40, function ($records) use ($ownership, &$pages, &$exclusions): void {
                foreach ($records as $page) {
                    if (! $ownership->pageIsCanonical($page->slug)) {
                        continue;
                    }
                    $loc = SitemapUrl::page('/'.$page->slug);
                    foreach ($this->htmlPaths((string) $page->body) as $path) {
                        $this->addImage($pages, $exclusions, $loc, $page->updated_at, null, $path, null, null);
                    }
                }
            });
    }

    /**
     * @param  array<string, array{lastmod: ?CarbonInterface, images: array<string, true>}>  $pages
     * @param  list<array{loc: string, reason: string}>  $exclusions
     */
    private function postImages(SitemapOwnership $ownership, array &$pages, array &$exclusions): void
    {
        Post::query()
            ->published()
            ->select(['id', 'slug', 'image', 'body', 'body_document', 'updated_at'])
            ->chunkById(40, function ($posts) use ($ownership, &$pages, &$exclusions): void {
                $mediaIds = [];
                $documents = [];
                foreach ($posts as $post) {
                    if (! $ownership->postIsCanonical($post->slug) || ! is_array($post->body_document)) {
                        continue;
                    }
                    $ids = [];
                    $this->collectMediaIds($post->body_document, $ids);
                    $documents[$post->id] = $ids;
                    array_push($mediaIds, ...$ids);
                }

                $media = $mediaIds === []
                    ? collect()
                    : JournalMedia::query()->whereIn('id', array_unique($mediaIds))->get()->keyBy('id');

                foreach ($posts as $post) {
                    if (! $ownership->postIsCanonical($post->slug)) {
                        continue;
                    }
                    $loc = SitemapUrl::page('/'.$post->slug);
                    if (is_string($post->image) && trim($post->image) !== '') {
                        $this->addImage($pages, $exclusions, $loc, $post->updated_at, null, $post->image, null, null);
                    }
                    foreach ($this->htmlPaths((string) $post->body) as $path) {
                        $this->addImage($pages, $exclusions, $loc, $post->updated_at, null, $path, null, null);
                    }
                    foreach ($documents[$post->id] ?? [] as $id) {
                        $item = $media->get($id);
                        if ($item instanceof JournalMedia && is_string($item->path)) {
                            $this->addImage($pages, $exclusions, $loc, $post->updated_at, $item->updated_at, $item->path, $item->width, $item->height);
                        }
                    }
                }
            });
    }

    /**
     * @param  array<string, array{lastmod: ?CarbonInterface, images: array<string, true>}>  $pages
     * @param  list<array{loc: string, reason: string}>  $exclusions
     */
    private function homeImages(array &$pages, array &$exclusions): void
    {
        $loc = SitemapUrl::page('/');
        HomeImage::query()
            ->whereNotNull('path')
            ->select(['id', 'path', 'updated_at'])
            ->chunkById(100, function ($images) use ($loc, &$pages, &$exclusions): void {
                foreach ($images as $image) {
                    if (! is_string($image->path) || trim($image->path) === '') {
                        continue;
                    }
                    $this->addImage($pages, $exclusions, $loc, null, $image->updated_at, $image->path, null, null);
                }
            });
    }

    /**
     * @param  array<string, array{lastmod: ?CarbonInterface, images: array<string, true>}>  $pages
     * @param  list<array{loc: string, reason: string}>  $exclusions
     */
    private function projectImages(SitemapOwnership $ownership, array &$pages, array &$exclusions): void
    {
        if (! $ownership->resolvedIsCanonical('project-gallery')) {
            return;
        }

        $loc = SitemapUrl::page('/project-gallery');
        Project::query()
            ->select(['id', 'image', 'lightbox', 'width', 'height', 'updated_at'])
            ->chunkById(100, function ($projects) use ($loc, &$pages, &$exclusions): void {
                foreach ($projects as $project) {
                    $this->addImage($pages, $exclusions, $loc, $project->updated_at, null, (string) $project->image, $project->width, $project->height);
                    if (is_string($project->lightbox) && trim($project->lightbox) !== '') {
                        $this->addImage($pages, $exclusions, $loc, $project->updated_at, null, $project->lightbox, null, null);
                    }
                }
            });
    }

    /**
     * @param  array<string, array{lastmod: ?CarbonInterface, images: array<string, true>}>  $pages
     * @param  list<array{loc: string, reason: string}>  $exclusions
     */
    private function addImage(
        array &$pages,
        array &$exclusions,
        string $loc,
        ?CarbonInterface $pageLastmod,
        ?CarbonInterface $imageLastmod,
        string $path,
        ?int $width,
        ?int $height,
    ): void {
        $decision = $this->images->decide($path, $width, $height);
        if ($decision['reason'] !== null) {
            $this->exclude($exclusions, $decision['url'] ?? $path, $decision['reason']);

            return;
        }
        if ($decision['url'] === null) {
            return;
        }

        if (! isset($pages[$loc])) {
            $pages[$loc] = ['lastmod' => null, 'images' => []];
        }
        $pages[$loc]['images'][$decision['url']] = true;
        $lastmod = $this->newer($pageLastmod, $imageLastmod);
        if ($lastmod !== null && ($pages[$loc]['lastmod'] === null || $lastmod->greaterThan($pages[$loc]['lastmod']))) {
            $pages[$loc]['lastmod'] = $lastmod;
        }
    }

    /**
     * @return list<string>
     */
    private function htmlPaths(string $html): array
    {
        if ($html === '' || ! str_contains(strtolower($html), '<img')) {
            return [];
        }

        preg_match_all('/\b(?:src|data-src)\s*=\s*(["\'])([^"\']+)\1/i', $html, $matches);

        return $matches[2] ?? [];
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<int>  $ids
     */
    private function collectMediaIds(array $node, array &$ids): void
    {
        if (isset($node['mediaId']) && is_numeric($node['mediaId'])) {
            $ids[] = (int) $node['mediaId'];
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collectMediaIds($child, $ids);
            }
        }
    }

    /**
     * @param  array<string, SitemapEntry>  $entries
     */
    private function put(array &$entries, string $loc, mixed $lastmod): void
    {
        if (isset($entries[$loc])) {
            return;
        }

        $entries[$loc] = new SitemapEntry($loc, $lastmod instanceof CarbonInterface ? $lastmod : $this->stamp($lastmod));
    }

    /**
     * @param  list<array{loc: string, reason: string}>  $exclusions
     */
    private function exclude(array &$exclusions, string $loc, string $reason): void
    {
        $key = $loc.'|'.$reason;
        if (isset($this->seenExclusions[$key]) || count($exclusions) >= 200) {
            return;
        }

        $this->seenExclusions[$key] = true;
        $exclusions[] = ['loc' => $loc, 'reason' => $reason];
    }

    /**
     * @param  array<string, SitemapEntry>  $entries
     * @return list<SitemapEntry>
     */
    private function sorted(array $entries): array
    {
        ksort($entries);

        return array_values($entries);
    }

    private function stamp(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value, 'UTC')->timezone((string) config('app.timezone'));
    }

    private function newer(?CarbonInterface $left, ?CarbonInterface $right): ?CarbonInterface
    {
        if ($left === null) {
            return $right;
        }
        if ($right === null) {
            return $left;
        }

        return $left->greaterThan($right) ? $left : $right;
    }
}
