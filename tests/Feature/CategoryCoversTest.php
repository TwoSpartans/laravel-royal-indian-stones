<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\CategoryCovers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCoversTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_collection_uses_a_sharp_category_photograph(): void
    {
        Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'image' => 'assets/img/optimized/bced8b2cfeced7e0-380.webp',
            'position' => 0,
        ]);

        CategoryCovers::apply();

        $this->get('/')
            ->assertOk()
            ->assertSee('A material for every project', false)
            ->assertSee('assets/img/products/sandstone/agra-red/agra-red-sandstone-natural-mosaic-tiles.jpg', false)
            ->assertDontSee('bced8b2cfeced7e0-380.webp', false);
    }
}
