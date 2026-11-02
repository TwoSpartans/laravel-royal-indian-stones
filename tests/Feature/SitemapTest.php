<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SitemapRun;
use App\Models\User;
use App\Services\Sitemap\SitemapCatalog;
use App\Services\Sitemap\SitemapPublisher;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory(storage_path('app/public/products/sitemap-test'));
        parent::tearDown();
    }

    public function test_published_product_is_added_and_an_edit_updates_only_its_lastmod(): void
    {
        Carbon::setTestNow('2026-04-01 10:00:00');
        $product = $this->product('mint-stone', true);
        $first = $this->get('/sitemap-products.xml');
        $first->assertOk();
        $first->assertSee('/mint-stone<', false);
        $first->assertSee($product->fresh()->updated_at->toAtomString(), false);
        $firstBody = $first->getContent();

        $this->artisan('sitemap:reconcile')->assertSuccessful();
        $this->assertSame($firstBody, $this->get('/sitemap-products.xml')->getContent());

        Carbon::setTestNow('2026-04-02 10:00:00');
        $product->update(['detail' => 'A fuller description of the mint stone.']);
        $third = $this->get('/sitemap-products.xml');
        $third->assertSee($product->fresh()->updated_at->toAtomString(), false);
        $this->assertNotSame($firstBody, $third->getContent());
    }

    public function test_unpublishing_or_deleting_a_product_removes_it(): void
    {
        $product = $this->product('hidden-mint', true);
        $this->get('/sitemap-products.xml')->assertOk()->assertSee('/hidden-mint<', false);

        $product->update(['is_published' => false]);
        $this->get('/sitemap-products.xml')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('hidden-mint', false);

        $product->update(['is_published' => true]);
        $this->get('/sitemap-products.xml')->assertOk()->assertSee('/hidden-mint<', false);
        $product->delete();
        $this->get('/sitemap-products.xml')->assertNotFound();
    }

    public function test_renamed_category_replaces_its_sitemap_address(): void
    {
        $category = Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'kota-sandstone',
            'position' => 1,
        ]);

        $this->get('/sitemap-categories.xml')->assertOk()->assertSee('/kota-sandstone<', false);

        $category->update(['slug' => 'bundi-sandstone', 'description' => 'A new public description.']);

        $this->get('/sitemap-categories.xml')
            ->assertOk()
            ->assertSee('/bundi-sandstone<', false)
            ->assertDontSee('/kota-sandstone<', false);
    }

    public function test_product_images_are_added_and_removed_with_the_parent_page(): void
    {
        config(['sitemap.max_images_per_url' => 2]);
        $product = $this->product('gallery-stone', true);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'https://cdn.example.com/photos/a&b.jpg',
            'alt' => 'Remote face',
            'position' => 0,
        ]);
        $this->image($product, 'stone.png', 800, 600);
        $this->image($product, 'side.png', 800, 600);
        $this->image($product, 'missing.png', 800, 600);
        $this->image($product, 'pixel.png', 1, 1);

        $xml = $this->get('/sitemap-images.xml');
        $xml->assertOk();
        $body = $xml->getContent();
        $this->assertStringContainsString('/gallery-stone<', $body);
        $this->assertStringContainsString('stone.png', $body);
        $this->assertSame(1, substr_count($body, 'stone.png'));
        $this->assertStringNotContainsString('side.png', $body);
        $this->assertStringNotContainsString('missing.png', $body);
        $this->assertStringNotContainsString('pixel.png', $body);
        $this->assertStringContainsString('https://cdn.example.com/photos/a&amp;b.jpg', $body);
        $this->assertStringNotContainsString('a&b.jpg', $body);
        $this->assertStringNotContainsString('image:caption', $body);
        $this->assertStringNotContainsString('image:title', $body);
        $this->get('/sitemap-products.xml')->assertOk()->assertDontSee('stone.png', false);
        $this->actingAs(User::factory()->create())
            ->get('/admin/sitemap/exclusions')
            ->assertOk()
            ->assertSee('The image file is missing.')
            ->assertSee('maximum of 2 images');

        $product->images()->where('path', 'like', '%stone.png')->get()->each->delete();
        $this->get('/sitemap-images.xml')->assertDontSee('stone.png', false);
    }

    public function test_journal_articles_follow_the_publishing_rules(): void
    {
        Post::query()->create([
            'slug' => 'courtyard-note',
            'title' => 'Courtyard note',
            'summary' => 'A published note.',
            'body' => '<p>Stone in a courtyard.</p>',
            'is_published' => true,
            'status' => 'published',
            'published_on' => '2026-03-01',
        ]);
        Post::query()->create([
            'slug' => 'future-note',
            'title' => 'Future note',
            'summary' => 'Not yet.',
            'body' => '<p>Later.</p>',
            'is_published' => false,
            'status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
        ]);

        $this->get('/sitemap-journal.xml')->assertOk()->assertSee('/courtyard-note<', false)->assertDontSee('future-note', false);

        Post::query()->where('slug', 'courtyard-note')->firstOrFail()->update([
            'is_published' => false,
            'status' => 'draft',
        ]);

        $this->get('/sitemap-journal.xml')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('courtyard-note', false)->assertDontSee('future-note', false);
    }

    public function test_noindex_page_is_removed_from_the_sitemap_and_marked_in_html(): void
    {
        Page::query()->create([
            'slug' => 'private-note',
            'title' => 'Private note',
            'body' => '<p>Hidden from search.</p>',
            'is_indexable' => false,
        ]);

        $this->get('/private-note')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
        $this->get('/sitemap-pages.xml')->assertOk()->assertDontSee('/private-note<', false);
    }

    public function test_redirects_duplicates_and_private_routes_stay_out(): void
    {
        $category = Category::query()->create([
            'name' => 'Limestone',
            'slug' => 'limestone',
            'position' => 1,
        ]);
        $this->product('shared-stone', true, $category);
        Page::query()->create([
            'slug' => 'shared-stone',
            'title' => 'Shared stone',
            'body' => '<p>Not the canonical page.</p>',
        ]);
        $this->product('admin', true, $category);

        $products = $this->get('/sitemap-products.xml')->assertOk()->getContent();
        $pages = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
        $index = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertSame(1, substr_count($products, '/shared-stone<'));
        $this->assertStringNotContainsString('/shared-stone<', $pages);
        $this->assertStringNotContainsString('/admin<', $products);
        $this->assertStringNotContainsString('/admin<', $pages);
        $this->assertStringNotContainsString('/portal', $index);
        $this->assertStringContainsString('/aboutus<', $pages);
        $this->assertStringNotContainsString('/about<', $pages);
        $this->assertStringNotContainsString('/blogs<', $pages);
        $this->assertStringNotContainsString('<priority>', $products);
        $this->assertStringNotContainsString('<changefreq>', $products);
    }

    public function test_sitemap_index_splits_when_a_file_reaches_the_url_limit(): void
    {
        config(['sitemap.max_urls' => 2]);
        foreach (['kota-limestone', 'tandur-limestone', 'jaisalmer-limestone'] as $position => $slug) {
            Category::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'position' => $position,
            ]);
        }

        $index = $this->get('/sitemap.xml');
        $index->assertOk();
        $index->assertSee('<sitemapindex', false);
        $index->assertSee('sitemap-categories.xml', false);
        $index->assertSee('sitemap-categories-2.xml', false);
        $index->assertDontSee('<urlset', false);
        $this->assertStringContainsString('application/xml', (string) $index->headers->get('Content-Type'));
        $this->assertTrue($this->schema($index->getContent()));
        $this->assertTrue($this->schema($this->get('/sitemap-categories.xml')->getContent()));
        $this->get('/sitemap-Categories.xml')->assertRedirect('/sitemap-categories.xml');
    }

    public function test_failed_rebuild_keeps_the_last_published_file(): void
    {
        $this->product('kept-stone', true);
        $original = $this->get('/sitemap-products.xml')->assertOk()->getContent();

        $this->mock(SitemapCatalog::class, function ($mock): void {
            $mock->shouldReceive('collect')->andThrow(new RuntimeException('disk full'));
        });

        try {
            app(SitemapPublisher::class)->rebuild('products', 'manual');
            $this->fail('The rebuild should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('disk full', $exception->getMessage());
        }

        $this->assertSame($original, $this->get('/sitemap-products.xml')->getContent());
        $this->assertNotNull(SitemapRun::query()->where('status', 'failed')->first());
    }

    public function test_admin_sitemap_tools_are_private_and_regenerate_on_request(): void
    {
        $this->get('/admin/sitemap')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_site_admin' => false]))
            ->get('/admin/sitemap')
            ->assertForbidden();

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->get('/admin/sitemap')
            ->assertOk()
            ->assertSee('Healthy')
            ->assertSee('Regenerate all')
            ->assertSee('Eligible URLs');

        $this->actingAs($admin)
            ->post(route('admin.sitemap.regenerate'))
            ->assertRedirect(route('admin.sitemap.index'));
        $this->actingAs($admin)
            ->post(route('admin.sitemap.validate'))
            ->assertRedirect(route('admin.sitemap.index'))
            ->assertSessionHas('status');
        $this->actingAs($admin)
            ->get('/admin/sitemap/history')
            ->assertOk()
            ->assertSee('Success');

        $this->artisan('schedule:list')
            ->expectsOutputToContain('sitemap:reconcile')
            ->assertSuccessful();
    }

    public function test_search_console_submission_runs_only_when_the_index_changes(): void
    {
        config([
            'sitemap.search_console.enabled' => true,
            'sitemap.search_console.site_url' => 'https://www.royalindianstones.com/',
            'sitemap.search_console.access_token' => 'token-value',
        ]);
        Http::fake([
            'www.googleapis.com/*' => Http::response('', 204),
        ]);

        $this->artisan('sitemap:reconcile')->assertSuccessful();
        $this->artisan('sitemap:reconcile')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'webmasters/v3/sites/')
                && str_contains($request->url(), 'sitemap.xml')
                && $request->hasHeader('Authorization', 'Bearer token-value');
        });
    }

    private function product(string $slug, bool $published, ?Category $category = null): Product
    {
        $category ??= Category::query()->first() ?? Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'position' => 0,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $slug,
            'slug' => $slug,
            'finishes' => [],
            'applications' => [],
            'is_published' => $published,
        ]);
    }

    private function image(Product $product, string $name, int $width, int $height): void
    {
        $relative = 'storage/products/sitemap-test/'.$name;
        if ($name !== 'missing.png') {
            $absolute = storage_path('app/public/products/sitemap-test/'.$name);
            File::ensureDirectoryExists(dirname($absolute));
            File::put($absolute, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        }

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => $relative,
            'alt' => $name,
            'width' => $width,
            'height' => $height,
            'position' => $product->images()->count(),
        ]);
    }

    private function schema(string $xml): bool
    {
        $dom = new DOMDocument;
        $dom->loadXML($xml);

        return $dom->schemaValidate(resource_path('schemas/sitemap.xsd'));
    }
}
