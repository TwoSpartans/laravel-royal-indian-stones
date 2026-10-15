<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function __invoke(): View
    {
        return view('catalogue', [
            'products' => Product::query()->published()->with('category')->orderBy('name')->get(),
            'active' => 'products',
            'title' => 'Find Natural Stone Products | Royal Indian Stones',
            'description' => 'Search Indian natural stones by material, colour, finish and application. Browse specifications and request a product-specific quotation.',
        ]);
    }
}
