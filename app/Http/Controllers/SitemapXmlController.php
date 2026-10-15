<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapXmlController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([url('/')])
            ->merge(Page::query()->orderBy('slug')->pluck('slug')->map(fn ($slug) => url('/'.$slug)))
            ->merge([
                url('/products'),
                url('/contact'),
                url('/project-gallery'),
                url('/faq'),
                url('/blog'),
                url('/natural-stones-of-india'),
                url('/sitemap'),
            ])
            ->merge(Category::query()->orderBy('position')->pluck('slug')->map(fn ($slug) => url('/'.$slug)))
            ->merge(Product::query()->published()->orderBy('slug')->pluck('slug')->map(fn ($slug) => url('/'.$slug)))
            ->merge(Post::query()->orderBy('slug')->pluck('slug')->map(fn ($slug) => url('/'.$slug)))
            ->unique()
            ->values();

        return response()
            ->view('sitemap-xml', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
