<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpec;

final class PlaceMissingMaterialProducts
{
    public function place(): int
    {
        $placed = 0;

        foreach ($this->definitions() as $definition) {
            $category = Category::query()->where('slug', $definition['category'])->first();
            if (! $category instanceof Category) {
                continue;
            }

            $product = Product::query()->where('slug', $definition['slug'])->first();
            if ($product instanceof Product) {
                if ($product->category_id === $category->id && $product->is_published) {
                    continue;
                }

                $product->category_id = $category->id;
                $product->is_published = true;
                $product->save();
                $placed++;

                continue;
            }

            $product = Product::query()->create([
                'category_id' => $category->id,
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'color' => $definition['color'],
                'finishes' => $definition['finishes'],
                'applications' => $definition['applications'],
                'summary' => $definition['summary'],
                'detail' => $definition['detail'],
                'application' => $definition['application'],
                'meta_title' => $definition['meta_title'],
                'meta_description' => $definition['meta_description'],
                'is_published' => true,
            ]);

            foreach ($definition['specs'] as $spec) {
                ProductSpec::query()->create([
                    'product_id' => $product->id,
                    'label' => $spec['label'],
                    'value' => $spec['value'],
                    'position' => $spec['position'],
                ]);
            }

            foreach ($definition['images'] as $image) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'path' => $image['path'],
                    'alt' => $image['alt'],
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'srcset' => $image['srcset'],
                    'position' => $image['position'],
                ]);
            }

            $placed++;
        }

        return $placed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(): array
    {
        $decoded = json_decode((string) file_get_contents(database_path('data/basalt-soapstone-travertine.json')), true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
