<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\CategoryCovers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCoversTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_index_keeps_the_sharp_category_photograph(): void
    {
        Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'image' => 'assets/img/optimized/bced8b2cfeced7e0-380.webp',
            'position' => 0,
        ]);

        CategoryCovers::apply();

        $this->assertDatabaseHas('categories', [
            'slug' => 'sandstone',
            'image' => 'assets/img/products/sandstone/agra-red/agra-red-sandstone-natural-mosaic-tiles.jpg',
        ]);

        $this->get('/natural-stones-of-india')
            ->assertOk()
            ->assertSee('assets/img/products/sandstone/agra-red/agra-red-sandstone-natural-mosaic-tiles.jpg', false);
    }

    public function test_home_collection_uses_a_sharp_photograph(): void
    {
        Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'image' => 'assets/img/optimized/bced8b2cfeced7e0-380.webp',
            'position' => 0,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Explore the collection', false)
            ->assertDontSee('A material for every project', false)
            ->assertDontSee('Stone for every project', false)
            ->assertSee('agra-red-sandstone-natural-mosaic-tiles.jpg', false)
            ->assertDontSee('bced8b2cfeced7e0-380.webp', false);
    }
}
