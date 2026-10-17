<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SitemapXmlController extends Controller
{
    public function __invoke(): Response
    {
        $entries = collect([
            $this->entry(url('/')),
            $this->entry(url('/products')),
            $this->entry(url('/contact')),
            $this->entry(url('/project-gallery')),
            $this->entry(url('/faq')),
            $this->entry(url('/blog')),
            $this->entry(url('/natural-stones-of-india')),
            $this->entry(url('/sitemap')),
        ])
            ->merge($this->modelEntries(Page::query()->orderBy('slug')))
            ->merge($this->modelEntries(Category::query()->orderBy('position')))
            ->merge($this->modelEntries(Product::query()->published()->orderBy('slug')))
            ->merge($this->modelEntries(Post::query()->orderBy('slug')))
            ->unique('loc')
            ->values();

        return response()
            ->view('sitemap-xml', ['entries' => $entries])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * @param  Builder<Model>  $query
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    private function modelEntries(Builder $query): Collection
    {
        return $query->get(['slug', 'updated_at'])->map(fn (Model $model): array => $this->entry(
            url('/'.$model->getAttribute('slug')),
            $model->getAttribute('updated_at'),
        ));
    }

    /**
     * @return array{loc: string, lastmod: string|null}
     */
    private function entry(string $loc, mixed $updatedAt = null): array
    {
        $lastmod = $updatedAt instanceof Carbon ? $updatedAt->toDateString() : null;

        return [
            'loc' => $loc,
            'lastmod' => $lastmod,
        ];
    }
}
