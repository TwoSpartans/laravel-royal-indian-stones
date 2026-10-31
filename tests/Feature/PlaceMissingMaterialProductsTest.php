<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\PlaceMissingMaterialProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceMissingMaterialProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_basalt_soapstone_and_travertine_categories_list_their_stones(): void
    {
        foreach ([
            ['Basalt', 'stone-basalt'],
            ['Soapstone', 'stone-soap'],
            ['Travertine', 'stone-travertine'],
        ] as [$name, $slug]) {
            Category::query()->create([
                'name' => $name,
                'slug' => $slug,
                'position' => 0,
            ]);
        }

        $placer = app(PlaceMissingMaterialProducts::class);

        $this->assertSame(3, $placer->place());
        $this->assertSame(0, $placer->place());
        $this->assertSame(3, Product::query()->count());

        $this->get('/stone-basalt')
            ->assertOk()
            ->assertSee('Indian Basalt Stone')
            ->assertDontSee('No products in this category yet');

        $this->get('/stone-soap')
            ->assertOk()
            ->assertSee('Natural Indian Soapstone')
            ->assertDontSee('No products in this category yet');

        $this->get('/stone-travertine')
            ->assertOk()
            ->assertSee('Indian Travertine Stone')
            ->assertDontSee('No products in this category yet');
    }
}
