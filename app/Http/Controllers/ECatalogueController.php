<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\HomeContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ECatalogueController extends Controller
{
    public function __invoke(HomeContent $content): View
    {
        $categories = Category::query()
            ->withCount(['products as published_products_count' => fn (Builder $query): Builder => $query->published()])
            ->orderBy('position')
            ->get()
            ->keyBy('slug');

        $collections = [];
        foreach ($content->explore($categories->values()) as $card) {
            $category = $categories->get($card['slot']);
            if (! $category instanceof Category) {
                continue;
            }

            $collections[] = [
                'name' => $card['name'],
                'url' => $card['url'],
                'image' => $card['image'],
                'alt' => $card['alt'],
                'summary' => $category->searchDescription(),
                'products' => (int) $category->getAttribute('published_products_count'),
            ];
        }

        return view('e-catalogue', [
            'collections' => $collections,
            'active' => '',
            'title' => 'E-catalogue | Royal Indian Stones',
            'description' => 'Browse the Royal Indian Stones e-catalogue: sandstone, limestone, marble, granite, slate and the other stones we export from India.',
        ]);
    }
}
