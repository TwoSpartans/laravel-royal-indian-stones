<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin/categories')->assertRedirect('/admin/login');
        $this->post('/admin/categories')->assertRedirect('/admin/login');
    }

    public function test_a_signed_in_visitor_cannot_open_categories(): void
    {
        $visitor = User::factory()->create();
        $visitor->forceFill(['is_site_admin' => false])->save();

        $this->actingAs($visitor)->get('/admin/categories')->assertForbidden();
    }

    public function test_admin_can_add_a_category_with_a_cover_and_an_automatic_slug(): void
    {
        $admin = User::factory()->create();
        $directory = storage_path('app/public/products/categories/kota-limestone');

        try {
            $this->actingAs($admin)->post('/admin/categories', [
                'name' => 'Kota Limestone',
                'description' => 'A limestone from Kota.',
                'introduction' => 'Cut in Rajasthan for floors and walls.',
                'cover' => UploadedFile::fake()->image('kota-cover.jpg', 800, 600),
            ])->assertRedirect();

            $category = Category::query()->where('slug', 'kota-limestone')->first();
            $this->assertNotNull($category);
            $this->assertSame('Cut in Rajasthan for floors and walls.', $category->introduction);
            $this->assertNotNull($category->image);
            $this->assertStringStartsWith('storage/products/categories/kota-limestone/', $category->image);
            $this->assertFileExists(storage_path('app/public/'.substr((string) $category->image, strlen('storage/'))));

            $this->get('/kota-limestone')
                ->assertOk()
                ->assertSee('Kota Limestone')
                ->assertSee('Cut in Rajasthan for floors and walls.');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_a_duplicate_category_slug_is_rejected(): void
    {
        $admin = User::factory()->create();
        Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'position' => 0,
        ]);

        $this->actingAs($admin)
            ->from('/admin/categories/create')
            ->post('/admin/categories', [
                'name' => 'Another sandstone',
                'slug' => 'sandstone',
            ])
            ->assertRedirect('/admin/categories/create')
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Category::query()->count());
    }

    public function test_admin_can_update_a_category_with_patch(): void
    {
        $admin = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Marble',
            'slug' => 'marble',
            'position' => 0,
        ]);

        $this->actingAs($admin)->patch('/admin/categories/'.$category->id, [
            'name' => 'Indian Marble',
            'slug' => 'marble',
            'introduction' => 'Blocks from the Indian quarries.',
        ])->assertRedirect('/admin/categories/'.$category->id.'/edit');

        $category->refresh();
        $this->assertSame('Indian Marble', $category->name);
        $this->assertSame('Blocks from the Indian quarries.', $category->introduction);
    }

    public function test_moving_a_category_rewrites_the_public_order(): void
    {
        $admin = User::factory()->create();
        $sandstone = $this->material('Sandstone', 'sandstone', 0);
        $marble = $this->material('Marble', 'marble', 1);
        $granite = $this->material('Granite', 'granite', 2);

        $this->actingAs($admin)->post('/admin/categories/reorder', [
            'id' => $granite->id,
            'direction' => 'up',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(
            [$sandstone->id, $granite->id, $marble->id],
            Category::query()->orderBy('position')->pluck('id')->all(),
        );

        $this->actingAs($admin)->post('/admin/categories/reorder', [
            'id' => $sandstone->id,
            'direction' => 'up',
        ])->assertRedirect()->assertSessionMissing('status');

        $this->assertSame(0, $sandstone->fresh()->position);
    }

    public function test_reorder_requires_every_category_once(): void
    {
        $admin = User::factory()->create();
        $sandstone = $this->material('Sandstone', 'sandstone', 0);
        $marble = $this->material('Marble', 'marble', 1);

        $this->actingAs($admin)
            ->from('/admin/categories')
            ->post('/admin/categories/reorder', [
                'ids' => [$marble->id],
            ])
            ->assertRedirect('/admin/categories')
            ->assertSessionHasErrors('ids');

        $this->assertSame(0, $sandstone->fresh()->position);
        $this->assertSame(1, $marble->fresh()->position);
    }

    public function test_a_category_with_stones_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $category = $this->material('Sandstone', 'sandstone', 0);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Jodhpur Pink',
            'slug' => 'jodhpur-pink',
            'color' => '',
            'finishes' => [],
            'applications' => [],
            'summary' => '',
        ]);

        $this->actingAs($admin)
            ->delete('/admin/categories/'.$category->id)
            ->assertRedirect('/admin/categories')
            ->assertSessionHas('error');

        $this->assertModelExists($category);
        $this->assertSame(1, Product::query()->count());
    }

    public function test_an_empty_category_can_be_deleted_with_its_cover(): void
    {
        $admin = User::factory()->create();
        $directory = storage_path('app/public/products/categories/slate');

        try {
            $this->actingAs($admin)->post('/admin/categories', [
                'name' => 'Slate',
                'slug' => 'slate',
                'cover' => UploadedFile::fake()->image('slate-cover.jpg', 640, 480),
            ])->assertRedirect();

            $category = Category::query()->where('slug', 'slate')->firstOrFail();
            $absolute = storage_path('app/public/'.substr((string) $category->image, strlen('storage/')));
            $this->assertFileExists($absolute);

            $this->actingAs($admin)
                ->delete('/admin/categories/'.$category->id)
                ->assertRedirect('/admin/categories')
                ->assertSessionHas('status');

            $this->assertDatabaseMissing('categories', ['id' => $category->id]);
            $this->assertFileDoesNotExist($absolute);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function material(string $name, string $slug, int $position): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'position' => $position,
        ]);
    }
}
