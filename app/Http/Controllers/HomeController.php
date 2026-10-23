<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $order = [
            'sandstone-raj-green',
            'granite-south-indian-absolute-black',
            'marble-rainforest-green',
        ];

        $featured = Product::query()
            ->with(['category', 'images'])
            ->where('is_published', true)
            ->whereIn('slug', $order)
            ->get()
            ->sortBy(fn (Product $product) => array_search($product->slug, $order, true))
            ->values();

        return view('home', [
            'categories' => Category::query()->orderBy('position')->get(),
            'featured' => $featured,
            'posts' => Post::query()->published()->with('postCategory')->orderByDesc('published_on')->orderBy('title')->limit(3)->get(),
            'active' => 'home',
            'title' => 'Indian natural stone | Royal Indian Stones',
            'description' => 'Discover sandstone, granite, marble and more for architecture, interiors and outdoor projects.',
            'shareImage' => asset('assets/img/slide/slide-1.jpg'),
        ]);
    }
}
