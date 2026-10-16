<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Support\LaterPages;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function show(string $slug): View
    {
        $product = Product::query()->published()->with(['category', 'specs', 'images'])->where('slug', $slug)->first();
        if ($product) {
            $related = Product::query()
                ->published()
                ->with('images')
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->orderBy('name')
                ->limit(3)
                ->get();

            return view('product', [
                'product' => $product,
                'related' => $related,
                'active' => 'products',
                'productId' => $product->slug,
                'title' => $product->meta_title ?: $product->name.' | Royal Indian Stones',
                'description' => $product->meta_description ?: $product->summary,
                'productSchema' => $this->productSchema($product),
            ]);
        }

        $category = Category::query()->where('slug', $slug)->first();
        if ($category) {
            return view('category', [
                'category' => $category,
                'products' => $category->products()->published()->with('images')->orderBy('name')->get(),
                'active' => 'products',
                'title' => $category->name.' | Royal Indian Stones',
                'description' => $category->introduction ?: ($category->description ?: 'Browse the '.$category->name.' collection from Royal Indian Stones.'),
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
            return view('sitemap', [
                'categories' => Category::query()->orderBy('position')->get(),
                'products' => Product::query()->published()->with('category')->orderBy('name')->get(),
                'pages' => Page::query()->orderBy('title')->get(),
                'posts' => Post::query()->orderByDesc('published_on')->orderBy('title')->get(),
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
            return view('journal', [
                'posts' => Post::query()->with('postCategory')->orderByDesc('published_on')->orderBy('title')->get(),
                'active' => 'resources',
                'title' => 'Journal | Royal Indian Stones',
                'description' => 'Notes on Indian sandstone, granite, marble, slate, and other natural stones.',
            ]);
        }

        if ($slug === 'aboutus') {
            return view('about', [
                'active' => 'about',
                'title' => 'About Us | Royal Indian Stones',
                'description' => 'Royal Indian Stones supplies Indian sandstone, limestone, marble, and granite from Jodhpur for buildings and landscapes.',
            ]);
        }

        $page = Page::query()->where('slug', $slug)->first();
        if ($page) {
            return view('content', [
                'page' => $page,
                'active' => $page->nav_group,
                'title' => $page->title.' | Royal Indian Stones',
                'description' => $page->meta_description ?: $page->title.' from Royal Indian Stones.',
            ]);
        }

        $post = Post::query()->with(['postCategory', 'comments' => fn ($query) => $query->where('approved', true)->latest()])->where('slug', $slug)->first();
        if ($post) {
            return view('post', [
                'post' => $post,
                'active' => 'resources',
                'title' => $post->title.' | Royal Indian Stones',
                'description' => $post->meta_description ?: $post->summary,
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
     * @return array<string, mixed>
     */
    private function productSchema(Product $product): array
    {
        $image = $product->catalogueImage();
        $crumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
        ];
        if ($product->category) {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $product->category->name, 'item' => url('/'.$product->category->slug)];
        }
        $crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => $product->name];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs],
                [
                    '@type' => 'Product',
                    'name' => $product->name,
                    'description' => $product->summary,
                    'image' => $image ? asset($image->path) : null,
                    'brand' => ['@type' => 'Brand', 'name' => 'Royal Indian Stones'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function articleSchema(Post $post): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Journal', 'item' => url('/blog')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $post->title],
                    ],
                ],
                [
                    '@type' => 'Article',
                    'headline' => $post->title,
                    'datePublished' => $post->published_on?->toDateString(),
                    'description' => $post->summary,
                    'image' => $post->image ? asset($post->image) : null,
                    'author' => ['@type' => 'Organization', 'name' => 'Royal Indian Stones'],
                ],
            ],
        ];
    }
}
