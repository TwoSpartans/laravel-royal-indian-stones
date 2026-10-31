<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Services\BlogStudio\StudioDocumentService;
use App\Services\JournalIndex;
use App\Support\LaterPages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SiteController extends Controller
{
    /**
     * @var list<string>
     */
    private const RESERVED_SLUGS = [
        'natural-stones-of-india',
        'sitemap',
        'project-gallery',
        'faq',
        'blog',
        'aboutus',
    ];

    public function show(string $slug): View|RedirectResponse
    {
        foreach (self::RESERVED_SLUGS as $canonical) {
            if ($slug !== $canonical && strcasecmp($slug, $canonical) === 0) {
                return redirect('/'.$canonical, 301);
            }
        }

        $product = $this->findBySlug(
            Product::query()->published()->with(['category', 'specs', 'images']),
            $slug,
        );
        if ($product instanceof Product) {
            if ($product->slug !== $slug) {
                return redirect('/'.$product->slug, 301);
            }

            $related = Product::query()
                ->published()
                ->with('images')
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->orderBy('name')
                ->limit(12)
                ->get();
            $image = $product->catalogueImage();

            return view('product', [
                'product' => $product,
                'related' => $related,
                'active' => $product->category?->slug === 'stone-articles' ? 'articles' : 'products',
                'productId' => $product->slug,
                'title' => $product->searchTitle(),
                'description' => $product->searchDescription(),
                'shareImage' => $image ? asset($image->path) : null,
                'productSchema' => $this->productSchema($product),
            ]);
        }

        $category = $this->findBySlug(Category::query(), $slug);
        if ($category instanceof Category) {
            if ($category->slug !== $slug) {
                return redirect('/'.$category->slug, 301);
            }

            return view('category', [
                'category' => $category,
                'products' => $category->products()->published()->with('images')->orderBy('name')->get(),
                'active' => $category->slug === 'stone-articles' ? 'articles' : 'products',
                'title' => $category->name.' | Royal Indian Stones',
                'description' => $category->searchDescription(),
                'shareImage' => $category->image ? asset($category->image) : null,
            ]);
        }

        if ($slug === 'natural-stones-of-india') {
            return view('categories', [
                'categories' => Category::query()->orderBy('position')->get(),
                'active' => 'products',
                'title' => 'Natural Stones of India | Royal Indian Stones',
                'description' => 'Browse sandstone, limestone, marble, granite, slate, quartzite and other natural stones from India.',
            ]);
        }

        if ($slug === 'sitemap') {
            $pages = Page::query()->orderBy('title')->get();
            $pageGroups = $this->sitemapPageGroups($pages);
            $featuredPages = $this->featuredSitemapPages();

            return view('sitemap', [
                'featuredPages' => $featuredPages,
                'pageGroups' => $pageGroups,
                'pageCount' => count($featuredPages) + collect($pageGroups)->sum(fn (array $links): int => count($links)),
                'categories' => Category::query()->orderBy('position')->get(),
                'productsByCategory' => Product::query()->published()->orderBy('name')->get()->groupBy('category_id'),
                'posts' => Post::query()->published()->orderByDesc('published_on')->orderBy('title')->get(),
                'active' => '',
                'title' => 'Sitemap | Royal Indian Stones',
                'description' => 'All public pages and stone products on Royal Indian Stones.',
            ]);
        }

        if ($slug === 'project-gallery') {
            return view('gallery', [
                'projects' => Project::query()->orderBy('position')->get(),
                'active' => 'projects',
                'bodyClass' => 'gallery-page',
                'title' => 'Project Gallery | Royal Indian Stones',
                'description' => 'Natural stone projects delivered by Royal Indian Stones for residences, hotels, landscaping, and building work.',
            ]);
        }

        if ($slug === 'faq') {
            return view('faq', [
                'faqs' => Faq::query()->orderBy('position')->get(),
                'active' => 'resources',
                'title' => 'FAQ | Royal Indian Stones',
                'description' => 'Ordering, packing, delivery, and payment questions about natural stone from Royal Indian Stones.',
            ]);
        }

        if ($slug === 'blog') {
            return app(JournalIndex::class)->respond(request());
        }

        if ($slug === 'aboutus') {
            return view('about', [
                'active' => 'about',
                'title' => 'About Us | Royal Indian Stones',
                'description' => 'Royal Indian Stones is part of the Royal Indian Exports Group, a certified manufacturer, exporter, and factory owner of natural stone.',
                'shareImage' => asset('assets/img/about.jpg'),
            ]);
        }

        $page = $this->findBySlug(Page::query(), $slug);
        if ($page instanceof Page) {
            if ($page->slug !== $slug) {
                return redirect('/'.$page->slug, 301);
            }

            return view('content', [
                'page' => $page,
                'active' => $page->nav_group,
                'title' => $this->readablePageTitle($page->title).' | Royal Indian Stones',
                'description' => $page->meta_description ?: $page->title.' from Royal Indian Stones.',
            ]);
        }

        app(StudioDocumentService::class)->publishDue();

        $post = $this->findBySlug(
            Post::query()->published()->with(['postCategory', 'comments' => fn ($query) => $query->where('approved', true)->latest()]),
            $slug,
        );
        if ($post instanceof Post) {
            if ($post->slug !== $slug) {
                return redirect('/'.$post->slug, 301);
            }

            return view('post', [
                'post' => $post,
                'active' => 'blog',
                'title' => $post->title.' | Royal Indian Stones',
                'description' => $post->meta_description ?: $post->summary,
                'shareImage' => $post->image ? asset($post->image) : null,
                'articleSchema' => $this->articleSchema($post),
            ]);
        }

        $placeholder = LaterPages::PAGES[$slug] ?? null;
        abort_if($placeholder === null, 404);

        return view('page', [
            'pageTitle' => $placeholder['title'],
            'active' => $placeholder['active'],
            'title' => $placeholder['title'].' | Royal Indian Stones',
            'description' => $placeholder['title'].' from Royal Indian Stones.',
        ]);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function findBySlug(Builder $query, string $slug): ?Model
    {
        $match = (clone $query)->where('slug', $slug)->first();
        if ($match instanceof Model || $slug === strtolower($slug)) {
            return $match;
        }

        return (clone $query)->whereRaw('lower(slug) = ?', [strtolower($slug)])->first();
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function featuredSitemapPages(): array
    {
        return [
            ['label' => 'Home', 'url' => url('/')],
            ['label' => 'Find your stone', 'url' => url('/products')],
            ['label' => 'E-catalogue', 'url' => route('e-catalogue')],
            ['label' => 'Stone categories', 'url' => url('/natural-stones-of-india')],
            ['label' => 'Request a quote', 'url' => route('contact')],
            ['label' => 'About us', 'url' => url('/aboutus')],
            ['label' => 'Project gallery', 'url' => url('/project-gallery')],
            ['label' => 'FAQ', 'url' => url('/faq')],
            ['label' => 'Blog', 'url' => url('/blog')],
        ];
    }

    /**
     * @param  Collection<int, Page>  $pages
     * @return array<string, list<array{label: string, url: string}>>
     */
    private function sitemapPageGroups(Collection $pages): array
    {
        $remaining = $pages->reject(fn (Page $page): bool => $page->slug === 'aboutus');
        $definitions = [
            'About' => fn (Page $page): bool => $page->nav_group === 'about',
            'Care and quality' => fn (Page $page): bool => in_array($page->slug, ['quality-control', 'natural-stone-care', 'general-instruction'], true),
            'Packaging' => fn (Page $page): bool => str_starts_with($page->slug, 'packaging-type'),
            'Policies' => fn (Page $page): bool => in_array($page->slug, ['privacy-policy', 'disclaimer'], true),
        ];
        $groups = [];
        $claimed = collect();

        foreach ($definitions as $label => $matches) {
            $matched = $remaining
                ->filter($matches)
                ->reject(fn (Page $page): bool => $claimed->contains($page->id))
                ->values();
            if ($matched->isEmpty()) {
                continue;
            }
            $claimed = $claimed->merge($matched->pluck('id'));
            $groups[$label] = $this->sitemapLinks($matched);
        }

        $leftover = $remaining->reject(fn (Page $page): bool => $claimed->contains($page->id))->values();
        if ($leftover->isNotEmpty()) {
            $groups['More pages'] = $this->sitemapLinks($leftover);
        }

        return $groups;
    }

    /**
     * @param  Collection<int, Page>  $pages
     * @return list<array{label: string, url: string}>
     */
    private function sitemapLinks(Collection $pages): array
    {
        return $pages->map(fn (Page $page): array => [
            'label' => $this->readablePageTitle($page->title),
            'url' => url('/'.$page->slug),
        ])->all();
    }

    private function readablePageTitle(string $title): string
    {
        if (mb_strtoupper($title, 'UTF-8') !== $title) {
            return $title;
        }

        $lower = mb_strtolower($title, 'UTF-8');

        return preg_replace_callback(
            '/(?<=^|[\s\-\/])\p{L}/u',
            fn (array $match): string => mb_strtoupper($match[0], 'UTF-8'),
            $lower,
        ) ?? $lower;
    }

    /**
     * @return array<string, mixed>
     */
    private function productSchema(Product $product): array
    {
        $crumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
        ];
        if ($product->category) {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $product->category->name, 'item' => url('/'.$product->category->slug)];
        }
        $crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => $product->name];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $crumbs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function articleSchema(Post $post): array
    {
        $posting = array_filter([
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'datePublished' => $post->published_on?->toDateString(),
            'dateModified' => $post->updated_at?->toDateString(),
            'description' => $post->meta_description ?: $post->summary,
            'image' => $post->image ? asset($post->image) : null,
            'mainEntityOfPage' => url('/'.$post->slug),
            'timeRequired' => 'PT'.$post->readingMinutes().'M',
            'author' => ['@type' => 'Organization', 'name' => 'Royal Indian Stones'],
            'publisher' => ['@type' => 'Organization', 'name' => 'Royal Indian Stones'],
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => url('/blog')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $post->title],
                    ],
                ],
                $posting,
            ],
        ];
    }
}
