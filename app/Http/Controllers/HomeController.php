<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
            'active' => 'home',
            'title' => 'Royal Indian Stones | Indian Natural Stone Manufacturer & Exporter',
            'description' => 'Discover sandstone, granite, marble and more for architecture, interiors and outdoor projects.',
        ]);
    }
}
