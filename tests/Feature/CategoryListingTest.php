<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_granite_page_lists_north_india_before_south_india(): void
    {
        $granite = $this->category('Granite', 'granite');
        $south = $this->stone($granite, 'Absolute Black Granite', 'granite-absolute-black');
        $south->specs()->create(['label' => 'Origin', 'value' => 'South India', 'position' => 0]);
        $north = $this->stone($granite, 'River White Granite', 'granite-river-white');
        $north->specs()->create(['label' => 'Origin', 'value' => 'North India', 'position' => 0]);
        $hidden = $this->stone($granite, 'Hidden South Granite', 'granite-hidden-south');
        $hidden->forceFill(['is_published' => false])->save();
        $hidden->specs()->create(['label' => 'Origin', 'value' => 'South India', 'position' => 0]);

        $this->get('/granite')
            ->assertOk()
            ->assertSee('Filter by origin')
            ->assertSee('North Indian Granites')
            ->assertSee('?group=north-india', false)
            ->assertSee('?group=south-india', false)
            ->assertSee('class="category-group-rule"', false)
            ->assertSeeInOrder(['River White Granite', 'South Indian Granites', 'Absolute Black Granite'])
            ->assertDontSee('Hidden South Granite');
    }

    public function test_granite_origin_filter_hides_the_other_region(): void
    {
        $granite = $this->category('Granite', 'granite');
        $south = $this->stone($granite, 'Absolute Black Granite', 'granite-absolute-black');
        $south->specs()->create(['label' => 'Origin', 'value' => 'South India', 'position' => 0]);
        $north = $this->stone($granite, 'River White Granite', 'granite-river-white');
        $north->specs()->create(['label' => 'Origin', 'value' => 'North India', 'position' => 0]);

        $this->get('/granite?group=south-india')
            ->assertOk()
            ->assertSee('Absolute Black Granite')
            ->assertDontSee('River White Granite')
            ->assertDontSee('category-group-rule', false);
    }

    public function test_an_unknown_granite_filter_keeps_both_regions(): void
    {
        $granite = $this->category('Granite', 'granite');
        $south = $this->stone($granite, 'Absolute Black Granite', 'granite-absolute-black');
        $south->specs()->create(['label' => 'Origin', 'value' => 'South India', 'position' => 0]);
        $north = $this->stone($granite, 'River White Granite', 'granite-river-white');
        $north->specs()->create(['label' => 'Origin', 'value' => 'North India', 'position' => 0]);

        $this->get('/granite?group=east-india')
            ->assertOk()
            ->assertSeeInOrder(['River White Granite', 'Absolute Black Granite']);
    }

    public function test_an_empty_granite_region_says_none_are_listed(): void
    {
        $granite = $this->category('Granite', 'granite');
        $north = $this->stone($granite, 'River White Granite', 'granite-river-white');
        $north->specs()->create(['label' => 'Origin', 'value' => 'North India', 'position' => 0]);

        $this->get('/granite?group=south-india')
            ->assertOk()
            ->assertSee('No products in this category yet')
            ->assertDontSee('River White Granite');
    }

    public function test_pebble_page_lists_natural_then_tumbled_then_polished(): void
    {
        $pebbles = $this->category('Pebbles', 'stone-pebbles');
        $this->stone($pebbles, 'Aqua Onyx Polished Pebbles', 'stone-pebbles-aqua-color-onyx-polished');
        $this->stone($pebbles, 'Beige Sandstone Pebbles', 'stone-pebbles-beige-sandstone-tumbled');
        $this->stone($pebbles, 'Yellow River Pebbles', 'stone-pebbles-yellow-natural-river');
        $this->stone($pebbles, 'Red Agate River Pebbles', 'stone-pebbles-red-agate-natural-river');
        $this->stone($pebbles, 'Black Agate Polished Pebbles', 'stone-pebbles-black-agate-polished-tumbled');

        $this->get('/stone-pebbles')
            ->assertOk()
            ->assertSee('Filter by finish')
            ->assertSee('Natural River Pebbles')
            ->assertSee('Natural Stone Tumbled Pebbles')
            ->assertSee('Natural Onyx Polished Pebbles')
            ->assertSee('Natural Agate Polished Pebbles')
            ->assertDontSee('?group=polished', false)
            ->assertSeeInOrder([
                'Red Agate River Pebbles',
                'Yellow River Pebbles',
                'Beige Sandstone Pebbles',
                'Aqua Onyx Polished Pebbles',
                'Black Agate Polished Pebbles',
            ]);
    }

    public function test_pebble_finish_filter_hides_the_other_finishes(): void
    {
        $pebbles = $this->category('Pebbles', 'stone-pebbles');
        $this->stone($pebbles, 'Aqua Onyx Polished Pebbles', 'stone-pebbles-aqua-color-onyx-polished');
        $this->stone($pebbles, 'Beige Sandstone Pebbles', 'stone-pebbles-beige-sandstone-tumbled');
        $this->stone($pebbles, 'Yellow River Pebbles', 'stone-pebbles-yellow-natural-river');
        $this->stone($pebbles, 'Black Agate Polished Pebbles', 'stone-pebbles-black-agate-polished-tumbled');

        $this->get('/stone-pebbles?group=tumbled')
            ->assertOk()
            ->assertSee('Beige Sandstone Pebbles')
            ->assertDontSee('Yellow River Pebbles')
            ->assertDontSee('Aqua Onyx Polished Pebbles')
            ->assertDontSee('Black Agate Polished Pebbles');
    }

    public function test_a_sandstone_page_does_not_offer_the_granite_or_pebble_filters(): void
    {
        $sandstone = $this->category('Sandstone', 'sandstone');
        $this->stone($sandstone, 'Jodhpur Pink Sandstone', 'sandstone-jodhpur-pink');

        $this->get('/sandstone')
            ->assertOk()
            ->assertSee('Jodhpur Pink Sandstone')
            ->assertDontSee('category-filter', false)
            ->assertDontSee('Filter by origin')
            ->assertDontSee('Filter by finish');
    }

    private function category(string $name, string $slug): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'position' => 0,
        ]);
    }

    private function stone(Category $category, string $name, string $slug): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'color' => 'Grey',
            'finishes' => [],
            'applications' => [],
        ]);
    }
}
