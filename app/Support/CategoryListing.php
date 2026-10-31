<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

final class CategoryListing
{
    /**
     * @param  Collection<int, Product>  $products
     * @return array{
     *     label: string,
     *     options: list<array{key: string, label: string, url: string, active: bool}>,
     *     groups: list<array{key: string, label: string, showHeading: bool, products: Collection<int, Product>}>,
     *     count: int
     * }
     */
    public function present(Category $category, Collection $products, ?string $selected): array
    {
        $definition = $this->definition($category);
        $ordered = $products->sortBy(fn (Product $product): string => mb_strtolower($product->name))->values();

        if ($definition === null) {
            return [
                'label' => '',
                'options' => [],
                'groups' => [[
                    'key' => '',
                    'label' => '',
                    'showHeading' => false,
                    'products' => $ordered,
                ]],
                'count' => $ordered->count(),
            ];
        }

        /** @var array<string, Collection<int, Product>> $buckets */
        $buckets = [];
        foreach ($definition['groups'] as $key => $label) {
            $buckets[$key] = new Collection;
        }
        $unmatched = new Collection;

        foreach ($ordered as $product) {
            $key = $this->keyFor($category, $product);
            if (is_string($key) && array_key_exists($key, $buckets)) {
                $buckets[$key]->push($product);
            } else {
                $unmatched->push($product);
            }
        }

        $active = is_string($selected) && array_key_exists($selected, $definition['groups']) ? $selected : '';
        $groups = [];

        if ($active !== '') {
            $groups[] = [
                'key' => $active,
                'label' => $definition['groups'][$active],
                'showHeading' => false,
                'products' => $buckets[$active],
            ];
        } else {
            foreach ($definition['groups'] as $key => $label) {
                if ($buckets[$key]->isEmpty()) {
                    continue;
                }
                $groups[] = [
                    'key' => $key,
                    'label' => $label,
                    'showHeading' => true,
                    'products' => $buckets[$key],
                ];
            }
            if ($unmatched->isNotEmpty()) {
                $groups[] = [
                    'key' => '',
                    'label' => '',
                    'showHeading' => false,
                    'products' => $unmatched,
                ];
            }
        }

        $labeled = array_values(array_filter($groups, fn (array $group): bool => $group['showHeading']));
        if (count($labeled) < 2) {
            foreach ($groups as $index => $group) {
                $groups[$index]['showHeading'] = false;
            }
        }

        $options = [[
            'key' => '',
            'label' => 'All',
            'url' => route('site.show', $category->slug),
            'active' => $active === '',
        ]];
        foreach ($definition['groups'] as $key => $label) {
            $options[] = [
                'key' => $key,
                'label' => $label,
                'url' => route('site.show', ['slug' => $category->slug, 'group' => $key]),
                'active' => $active === $key,
            ];
        }

        return [
            'label' => $definition['label'],
            'options' => $options,
            'groups' => $groups,
            'count' => array_sum(array_map(fn (array $group): int => $group['products']->count(), $groups)),
        ];
    }

    /**
     * @return array{label: string, groups: array<string, string>}|null
     */
    private function definition(Category $category): ?array
    {
        return match ($category->slug) {
            'granite' => [
                'label' => 'Filter by origin',
                'groups' => [
                    'north-india' => 'North Indian Granites',
                    'south-india' => 'South Indian Granites',
                ],
            ],
            'stone-pebbles' => [
                'label' => 'Filter by finish',
                'groups' => [
                    'natural' => 'Natural River Pebbles',
                    'tumbled' => 'Natural Stone Tumbled Pebbles',
                    'onyx' => 'Natural Onyx Polished Pebbles',
                    'agate' => 'Natural Agate Polished Pebbles',
                ],
            ],
            default => null,
        };
    }

    private function keyFor(Category $category, Product $product): ?string
    {
        return match ($category->slug) {
            'granite' => $this->graniteKey($product),
            'stone-pebbles' => $this->pebbleKey($product),
            default => null,
        };
    }

    private function graniteKey(Product $product): ?string
    {
        $origin = $product->specs->first(
            fn (mixed $spec): bool => is_object($spec) && strcasecmp((string) $spec->label, 'Origin') === 0,
        );
        $source = is_object($origin) && trim((string) $origin->value) !== ''
            ? (string) $origin->value
            : $product->slug;
        $source = mb_strtolower($source);

        if (str_contains($source, 'north')) {
            return 'north-india';
        }

        if (str_contains($source, 'south')) {
            return 'south-india';
        }

        return null;
    }

    private function pebbleKey(Product $product): string
    {
        $name = mb_strtolower($product->name);
        $slug = mb_strtolower($product->slug);

        $polished = str_contains($name, 'polish') || str_contains($slug, 'polish');
        $river = str_contains($slug, 'natural-river');

        if ($polished && ! $river && (str_contains($name, 'onyx') || str_contains($slug, 'onyx'))) {
            return 'onyx';
        }

        if ($polished && ! $river && (str_contains($name, 'agate') || str_contains($slug, 'agate'))) {
            return 'agate';
        }

        if (str_contains($slug, 'tumbled') && ! str_contains($name, 'polish')) {
            return 'tumbled';
        }

        return 'natural';
    }
}
