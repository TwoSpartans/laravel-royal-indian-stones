<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MaterialCategoryProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_basalt_soapstone_and_travertine_pages_become_products_in_their_categories(): void
    {
        $source = storage_path('framework/testing/catalogue-source');
        File::deleteDirectory($source);
        File::ensureDirectoryExists($source);
        File::put($source.'/index.html', '<html><body></body></html>');
        File::put($source.'/sandstone.html', '<html><head><meta name="description" content="Sandstone collection."></head><body><h1>Sandstone</h1></body></html>');
        File::put($source.'/stone-basalt.html', $this->materialPage('Indian Basalt Stone', 'Dark tiles and slabs.'));
        File::put($source.'/stone-soap.html', $this->materialPage('Natural Indian Soapstone', 'Countertops and carving.'));
        File::put($source.'/stone-travertine.html', $this->materialPage('Indian Travertine Stone', 'Floors and wall cladding.'));

        app(CatalogueImporter::class)->import($source);

        $basalt = Category::query()->where('slug', 'stone-basalt')->first();
        $this->assertNotNull($basalt);
        $this->assertEqualsCanonicalizing(
            ['indian-basalt-stone', 'natural-indian-soapstone', 'indian-travertine-stone'],
            Product::query()->pluck('slug')->all(),
        );

        $product = Product::query()->where('slug', 'indian-basalt-stone')->first();
        $this->assertNotNull($product);
        $this->assertSame($basalt->id, $product->category_id);
        $this->assertSame('Indian Basalt Stone', $product->name);
        $this->assertSame(
            'One full container is the minimum we accept.',
            $product->specs()->where('label', 'Minimum Order')->value('value'),
        );
        $this->assertNull(Product::query()->where('slug', 'stone-basalt')->first());
        $this->assertNull(Product::query()->where('slug', 'sandstone')->first());

        $this->get('/stone-basalt')
            ->assertOk()
            ->assertSee('Indian Basalt Stone')
            ->assertDontSee('No products in this category yet');

        $this->get('/indian-basalt-stone')
            ->assertOk()
            ->assertSee('Indian Basalt Stone')
            ->assertSee('Dark tiles and slabs.');
    }

    private function materialPage(string $name, string $detail): string
    {
        return <<<HTML
        <html><head><meta name="description" content="{$name}"></head><body>
        <div class="product-info"><h1>{$name}</h1>
        <table><tr><th>Color</th><td>Black</td></tr><tr><th>Finish</th><td>Honed</td></tr></table>
        </div>
        <div class="product-description"><p>{$detail}</p></div>
        </body></html>
        HTML;
    }
}
