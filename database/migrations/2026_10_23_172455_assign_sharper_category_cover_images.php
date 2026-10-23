<?php

use App\Models\Category;
use App\Support\CategoryCovers;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CategoryCovers::apply();
    }

    public function down(): void
    {
        foreach ($this->previousPaths() as $slug => $path) {
            Category::query()->where('slug', $slug)->update(['image' => $path]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function previousPaths(): array
    {
        return [
            'sandstone' => 'assets/img/optimized/bced8b2cfeced7e0-380.webp',
            'limestone' => 'assets/img/optimized/9b5332d61fed9334-380.webp',
            'marble' => 'assets/img/optimized/0f407efdb599cbcc-380.webp',
            'granite' => 'assets/img/products/index/granite/alaska-gold-north-indian-granite-polished-finish-tiles.jpg',
            'slate' => 'assets/img/optimized/343189dc0215c682-380.webp',
            'quartzite' => 'assets/img/optimized/5eb043ecc89e543b-380.webp',
            'stone-pebbles' => 'assets/img/optimized/510ead2d41ac9093-380.webp',
            'stone-cobbles' => 'assets/img/optimized/3676f1034c39e607-380.webp',
            'stone-chips' => 'assets/img/products/index/chips/yellow-color-natural-stone-crushed-tumbled-dry-chips.jpg',
            'stone-basalt' => 'assets/img/optimized/d63b2c681b1d4e12-380.webp',
            'stone-soap' => 'assets/img/optimized/e69488e9736d6be6-380.webp',
            'stone-travertine' => 'assets/img/optimized/1a37306f0b3f3902-380.webp',
        ];
    }
};
