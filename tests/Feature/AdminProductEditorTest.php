<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminProductEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $product = $this->stone();

        $this->get('/admin/products/create')->assertRedirect('/admin/login');
        $this->get('/admin/products/'.$product->id.'/edit')->assertRedirect('/admin/login');
        $this->post('/admin/products/'.$product->id.'/duplicate')->assertRedirect('/admin/login');
    }

    public function test_admin_can_add_a_stone_with_a_spec_and_a_photo(): void
    {
        $admin = User::factory()->create();
        $category = $this->sandstone();
        $directory = public_path('assets/img/products/sandstone/editor-pink');

        try {
            $this->actingAs($admin)->post('/admin/products', [
                'name' => 'Editor Pink Sandstone',
                'slug' => 'editor-pink',
                'category_id' => $category->id,
                'color' => 'Pink',
                'finishes_text' => 'Honed, Polished',
                'applications_text' => 'Paving',
                'summary' => 'A pink sandstone for courtyards.',
                'is_published' => '1',
                'specs' => [
                    ['label' => 'Origin', 'value' => 'Rajasthan'],
                ],
                'photos' => [
                    UploadedFile::fake()->image('pink-sandstone.jpg', 640, 480),
                ],
                'photo_alts' => ['Pink sandstone sample'],
            ])->assertRedirect();

            $product = Product::query()->where('slug', 'editor-pink')->first();
            $this->assertNotNull($product);
            $this->assertSame(['Honed', 'Polished'], $product->finishes);
            $image = $product->images()->first();
            $this->assertNotNull($image);
            $this->assertSame(640, $image->width);
            $this->assertSame('Pink sandstone sample', $image->alt);
            $this->assertFileExists($this->storedUpload($image->path));

            $this->get('/editor-pink')
                ->assertOk()
                ->assertSee('Editor Pink Sandstone')
                ->assertSee('Origin')
                ->assertSee('Rajasthan');
        } finally {
            File::deleteDirectory($directory);
            File::deleteDirectory(storage_path('app/public/products/sandstone/editor-pink'));
        }
    }

    public function test_admin_can_rename_a_stone_and_replace_its_spec(): void
    {
        $admin = User::factory()->create();
        $product = $this->stone();

        $this->actingAs($admin)->put('/admin/products/'.$product->id, [
            'name' => 'Jodhpur Gold',
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'is_published' => '1',
            'specs' => [
                ['label' => 'Finish', 'value' => 'Honed'],
            ],
        ])->assertRedirect('/admin/products/'.$product->id.'/edit');

        $this->assertSame('Jodhpur Gold', $product->fresh()->name);
        $this->assertDatabaseMissing('product_specs', ['product_id' => $product->id, 'label' => 'Origin']);
        $this->assertDatabaseHas('product_specs', ['product_id' => $product->id, 'label' => 'Finish', 'value' => 'Honed']);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        $admin = User::factory()->create();
        $product = $this->stone();

        $this->actingAs($admin)
            ->from('/admin/products/create')
            ->followingRedirects()
            ->post('/admin/products', [
                'name' => 'Another pink stone',
                'slug' => $product->slug,
                'category_id' => $product->category_id,
                'is_published' => '1',
            ])
            ->assertOk()
            ->assertSee('Check the form')
            ->assertSee('aria-invalid="true"', false);

        $this->assertSame(1, Product::query()->count());
    }

    public function test_a_new_stone_is_not_marked_saved(): void
    {
        $admin = User::factory()->create();
        $this->sandstone();

        $this->actingAs($admin)
            ->get('/admin/products/create')
            ->assertOk()
            ->assertSee('Not saved', false);
    }

    public function test_editor_shows_the_studio_controls(): void
    {
        $admin = User::factory()->create();
        $product = $this->stone();

        $this->actingAs($admin)
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('Preview')
            ->assertSee('Readiness')
            ->assertSee('Make cover');
    }

    public function test_reordering_photos_makes_the_first_image_the_cover(): void
    {
        $admin = User::factory()->create();
        $product = $this->stone();
        $first = $product->images()->create([
            'path' => 'assets/img/products/sandstone/first.jpg',
            'alt' => 'First sample',
            'position' => 0,
            'width' => 100,
            'height' => 80,
        ]);
        $second = $product->images()->create([
            'path' => 'assets/img/products/sandstone/second.jpg',
            'alt' => 'Second sample',
            'position' => 1,
            'width' => 100,
            'height' => 80,
        ]);

        $this->actingAs($admin)->put('/admin/products/'.$product->id, [
            'name' => $product->name,
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'is_published' => '1',
            'existing_images' => [
                $first->id => ['alt' => 'Updated first', 'position' => 1],
                $second->id => ['alt' => 'Second sample', 'position' => 0],
            ],
        ])->assertRedirect('/admin/products/'.$product->id.'/edit');

        $cover = $product->fresh()->load('images')->catalogueImage();
        $this->assertSame('Second sample', $cover?->alt);
        $this->assertSame('Updated first', $first->fresh()->alt);
        $this->assertSame(2, $product->images()->count());
    }

    public function test_an_unsupported_upload_is_rejected_and_existing_photos_stay(): void
    {
        $admin = User::factory()->create();
        $product = $this->stone();
        $image = $product->images()->create([
            'path' => 'assets/img/products/sandstone/kept.jpg',
            'alt' => 'Kept sample',
            'position' => 0,
        ]);

        $this->actingAs($admin)->put('/admin/products/'.$product->id, [
            'name' => 'Renamed stone',
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'is_published' => '1',
            'existing_images' => [
                $image->id => ['alt' => 'Kept sample', 'position' => 0],
            ],
            'photos' => [
                UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
            ],
        ])->assertSessionHasErrors('photos.0');

        $this->assertSame('Jodhpur Pink', $product->fresh()->name);
        $this->assertSame('assets/img/products/sandstone/kept.jpg', $image->fresh()->path);
        $this->assertSame(1, $product->images()->count());
    }

    public function test_a_rejected_upload_leaves_the_existing_photo_file_in_place(): void
    {
        $admin = User::factory()->create();
        $product = $this->stone();
        $path = 'assets/img/products/sandstone/kept-file.jpg';
        File::ensureDirectoryExists(public_path('assets/img/products/sandstone'));
        File::put(public_path($path), 'kept-photo');

        try {
            $image = $product->images()->create([
                'path' => $path,
                'alt' => 'Kept file',
                'position' => 0,
            ]);

            $this->actingAs($admin)->put('/admin/products/'.$product->id, [
                'name' => $product->name,
                'slug' => $product->slug,
                'category_id' => $product->category_id,
                'is_published' => '1',
                'photos' => [
                    UploadedFile::fake()->create('payload.php', 20, 'application/x-php'),
                ],
            ])->assertSessionHasErrors('photos.0');

            $this->assertFileExists(public_path($path));
            $this->assertNotNull($image->fresh());
            $this->assertSame(1, $product->images()->count());
        } finally {
            File::delete(public_path($path));
        }
    }

    public function test_the_product_list_can_be_filtered_by_name(): void
    {
        $admin = User::factory()->create();
        $this->stone();
        Product::query()->create([
            'category_id' => Category::query()->where('slug', 'sandstone')->first()->id,
            'name' => 'Other Gold Granite',
            'slug' => 'other-gold',
            'color' => 'Gold',
            'finishes' => [],
            'applications' => [],
            'summary' => 'Another stone.',
        ]);

        $this->actingAs($admin)
            ->get('/admin/products?q=Jodhpur')
            ->assertOk()
            ->assertSee('Jodhpur Pink')
            ->assertDontSee('Other Gold Granite');

        $this->actingAs($admin)
            ->get('/admin/products?q=zzzz-no-stone')
            ->assertOk()
            ->assertSee('No stone matched.');
    }

    public function test_a_shared_photo_file_is_kept_when_one_stone_drops_it(): void
    {
        $admin = User::factory()->create();
        $product = $this->stone();
        $path = 'assets/img/products/sandstone/shared-stone.jpg';
        File::ensureDirectoryExists(public_path('assets/img/products/sandstone'));
        File::put(public_path($path), 'shared-photo');

        try {
            $image = $product->images()->create([
                'path' => $path,
                'alt' => 'Shared sample',
                'position' => 0,
            ]);
            $other = Product::query()->create([
                'category_id' => $product->category_id,
                'name' => 'Other stone',
                'slug' => 'other-stone',
                'color' => 'Gold',
                'finishes' => [],
                'applications' => [],
                'summary' => 'Another stone.',
            ]);
            $other->images()->create([
                'path' => $path,
                'alt' => 'Shared sample',
                'position' => 0,
            ]);

            $this->actingAs($admin)->put('/admin/products/'.$product->id, [
                'name' => $product->name,
                'slug' => $product->slug,
                'category_id' => $product->category_id,
                'is_published' => '1',
                'remove_images' => [$image->id],
            ])->assertRedirect();

            $this->assertFileExists(public_path($path));
            $this->assertModelMissing($image);
            $this->assertSame(1, $other->images()->count());
        } finally {
            File::delete(public_path($path));
        }
    }

    public function test_specification_order_is_saved_and_empty_rows_are_ignored(): void
    {
        $admin = User::factory()->create();
        $product = $this->stone();

        $this->actingAs($admin)->put('/admin/products/'.$product->id, [
            'name' => $product->name,
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'is_published' => '1',
            'specs' => [
                ['label' => 'Finish', 'value' => 'Honed'],
                ['label' => '', 'value' => ''],
                ['label' => 'Origin', 'value' => 'India'],
                ['label' => 'Origin', 'value' => 'Rajasthan'],
            ],
        ])->assertRedirect();

        $labels = $product->specs()->orderBy('position')->pluck('label')->all();
        $this->assertSame(['Finish', 'Origin', 'Origin'], $labels);
    }

    public function test_duplicating_a_stone_creates_a_hidden_copy(): void
    {
        $admin = User::factory()->create();
        $product = $this->stone();
        $product->images()->create([
            'path' => 'assets/img/products/sandstone/copy-source.jpg',
            'alt' => 'Source photo',
            'position' => 0,
        ]);

        $this->actingAs($admin)
            ->post('/admin/products/'.$product->id.'/duplicate')
            ->assertRedirect();

        $copy = Product::query()->where('slug', 'jodhpur-pink-copy')->first();
        $this->assertNotNull($copy);
        $this->assertFalse($copy->is_published);
        $this->assertTrue($product->fresh()->is_published);
        $this->assertSame('Source photo', $copy->images()->first()?->alt);
        $this->assertSame('India', $copy->specs()->first()?->value);
    }

    private function sandstone(): Category
    {
        return Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
        ]);
    }

    private function storedUpload(string $path): string
    {
        if (str_starts_with($path, 'storage/')) {
            return storage_path('app/public/'.substr($path, strlen('storage/')));
        }

        return public_path($path);
    }

    private function stone(): Product
    {
        $product = Product::query()->create([
            'category_id' => $this->sandstone()->id,
            'name' => 'Jodhpur Pink',
            'slug' => 'jodhpur-pink',
            'color' => 'Pink',
            'finishes' => ['Honed'],
            'applications' => ['Paving'],
            'summary' => 'A pink sandstone.',
        ]);
        $product->specs()->create([
            'label' => 'Origin',
            'value' => 'India',
            'position' => 0,
        ]);

        return $product;
    }
}
