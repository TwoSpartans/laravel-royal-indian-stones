<?php

namespace App\Services;

use App\Models\Product;

class CatalogueIndex
{
    public static function write(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $products = Product::query()->with(['category', 'images'])->published()->orderBy('name')->get();
        $directory = public_path('assets/data');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents(
            $directory.DIRECTORY_SEPARATOR.'products.json',
            $products->map(fn (Product $product) => $product->toCatalogueArray())->values()->toJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );
    }
}
