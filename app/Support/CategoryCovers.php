<?php

namespace App\Support;

use App\Models\Category;

final class CategoryCovers
{
    /**
     * Photographs large enough for the collection cards. The previous covers were roughly 380 by 125 pixels.
     *
     * @return array<string, string>
     */
    public static function paths(): array
    {
        return [
            'sandstone' => 'assets/img/products/sandstone/agra-red/agra-red-sandstone-natural-mosaic-tiles.jpg',
            'limestone' => 'assets/img/products/limestone/n-green/n-green-limestone-natural-finish-decorative-wall-tiles.jpg',
            'marble' => 'assets/img/products/marble/rainforest-green/rainforest-green-marble-polished-tiles.jpg',
            'granite' => 'assets/img/products/granite/north-indian/river-black/river-black-granite-polished-tiles-rajasthan-india.jpg',
            'slate' => 'assets/img/products/slate/deoli-green/deoli-green-natural-patio-pack-wall-cladding-tiles.jpg',
            'quartzite' => 'assets/img/products/quartzite/star-black/star-black-quartzite-natural-finish-floor-tiles.jpg',
            'stone-pebbles' => 'assets/img/products/pebbles/beige-natural-river/beige-color-natural-river-pebbles-premium-quality-decorative-stones.jpg',
            'stone-cobbles' => 'assets/img/products/cobbles/raj-green-sandstone/raj-blend-green-indian-sandstone-square-cobbles-cubes-setts-close-up-natural-surface-finish-hand-tumbled-stones-landscaping-products.jpg',
            'stone-chips' => 'assets/img/products/chips/green-color-crushed/green-color-natural-crushed-indian-stone-chips.jpg',
            'stone-basalt' => 'assets/img/products/basalt/black-basalt-flamed-look-indian-stones-tiles-slabs-use-for-construction-projects.jpg',
            'stone-soap' => 'assets/img/products/soap/black-look-soap-soft-rock-quarried-stone-brushed-leather-finish-kitchen-countertops-applications.jpg',
            'stone-travertine' => 'assets/img/products/travertine/yellow-travertine-natural-look-indian-stone-supplier-exporter-wholesaler-trader-importer.jpg',
        ];
    }

    public static function apply(): void
    {
        foreach (self::paths() as $slug => $path) {
            if (! is_file(public_path($path))) {
                continue;
            }

            Category::query()->where('slug', $slug)->update(['image' => $path]);
        }
    }
}
