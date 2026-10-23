<?php

namespace Tests\Feature;

use App\Http\Middleware\CanonicalPublicUrl;
use App\Models\Category;
use App\Models\Enquiry;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_care_page_drops_stretched_image_styles(): void
    {
        Page::query()->create([
            'slug' => 'natural-stone-care',
            'title' => 'Natural Stone Care',
            'body' => '<img style="height: 100%!important;" sizes="33vw" alt="Cleaning a stone floor" src="/assets/img/stonecare/stonecare-1.jpg"><p>Marble needs a mild cleaner.</p>',
        ]);

        $this->get('/natural-stone-care')
            ->assertOk()
            ->assertSee('Marble needs a mild cleaner.')
            ->assertDontSee('height: 100%', false)
            ->assertDontSee('33vw', false);
    }

    public function test_catalogue_category_and_product_pages_use_existing_slugs(): void
    {
        $category = Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'description' => 'Indian sandstone for paving and cladding.',
            'position' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Jodhpur Pink Sandstone',
            'slug' => 'sandstone-jodhpur-pink',
            'color' => 'Pink',
            'finishes' => ['Honed'],
            'applications' => ['Paving'],
            'summary' => 'A pink sandstone from Jodhpur.',
            'detail' => 'Used for paving and wall cladding. It is also cut for garden steps. The colour stays even across a large floor.',
            'application' => 'Flooring and paving.',
        ]);
        $product->specs()->create(['label' => 'Origin', 'value' => 'India', 'position' => 0]);
        $product->specs()->create(['label' => 'Minimum Order', 'value' => 'Ask our team for this product.', 'position' => 1]);
        $product->images()->create(['path' => 'assets/img/about.jpg', 'alt' => 'First face', 'position' => 0]);
        $product->images()->create(['path' => 'assets/img/about.jpg', 'alt' => 'Second face', 'position' => 1]);

        $this->get('/products')->assertOk()->assertSee('Find the stone for your project');
        $this->get('/sandstone')->assertOk()->assertSee('Jodhpur Pink Sandstone');
        $this->get('/sandstone-jodhpur-pink')
            ->assertOk()
            ->assertSee('Request a Quote for This Stone')
            ->assertSee('About this stone')
            ->assertSee('Where it is used')
            ->assertSee('One full container is the minimum we accept.')
            ->assertSee('aria-label="Previous photograph"', false)
            ->assertSee('aria-label="Next photograph"', false)
            ->assertSee('class="product-thumbs"', false)
            ->assertDontSee('Relevant Products');
        $this->get('/sandstone-jodhpur-pink.html')->assertRedirect('/sandstone-jodhpur-pink');
        $this->get('/contact?product=sandstone-jodhpur-pink')->assertOk()->assertSee('Quotation: Jodhpur Pink Sandstone');
        $this->get('/aboutus')->assertOk()->assertSee('A Jodhpur stone house for buildings and landscapes.');
    }

    public function test_public_addresses_canonicalize_and_search_text_stays_specific(): void
    {
        $category = Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'description' => 'Indian sandstone for paving, cladding, and steps.',
            'introduction' => 'The Royal Indian Stones company exports a very long sandstone introduction.',
            'position' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Mint Sandstone',
            'slug' => 'sandstone-mint',
            'color' => 'Green',
            'finishes' => ['Natural'],
            'applications' => ['Paving', 'Cladding'],
            'summary' => 'Mint sandstone.',
            'meta_title' => 'Mint Sandstone, Manufacturer, Supplier, Exporter, Wholesaler from India',
            'meta_description' => 'Mint Sandstone',
            'is_published' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Raj Green Sandstone',
            'slug' => 'sandstone-raj-green',
            'color' => 'Green',
            'finishes' => ['Natural'],
            'applications' => ['Paving'],
            'summary' => 'Raj green sandstone.',
            'meta_title' => 'Raj Green Sandstone',
            'meta_description' => 'A green sandstone used for paving and cladding around courtyards.',
            'is_published' => true,
        ]);

        $trailingSlash = app(CanonicalPublicUrl::class)->handle(
            Request::create('http://localhost/sandstone/', 'GET'),
            fn () => response('ok'),
        );
        $this->assertSame(301, $trailingSlash->getStatusCode());
        $this->assertSame(rtrim((string) config('app.url'), '/').'/sandstone', $trailingSlash->headers->get('Location'));
        $this->get('/SANDSTONE')->assertStatus(301)->assertRedirect('/sandstone');
        $this->get('/Sandstone-Mint')->assertStatus(301)->assertRedirect('/sandstone-mint');
        $this->get('/FAQ')->assertStatus(301)->assertRedirect('/faq');
        $this->get('/sandstone')
            ->assertOk()
            ->assertSee('<meta name="description" content="Indian sandstone for paving, cladding, and steps.">', false)
            ->assertSee('The Royal Indian Stones company exports a very long sandstone introduction.');
        $this->get('/sandstone-mint')
            ->assertOk()
            ->assertSee('<title>Mint Sandstone | Royal Indian Stones</title>', false)
            ->assertSee('Mint Sandstone is a sandstone in Green from Royal Indian Stones', false)
            ->assertDontSee('Wholesaler')
            ->assertDontSee('"@type":"Product"', false);
        $this->get('/sandstone-raj-green')
            ->assertOk()
            ->assertSee('<title>Raj Green Sandstone | Royal Indian Stones</title>', false);
        $this->get('/')->assertOk()->assertSee('<title>Indian natural stone | Royal Indian Stones</title>', false);
        $this->get('/contact')->assertOk()->assertSee('Let’s find your stone | Royal Indian Stones', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee('<lastmod>', false);
        $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/admin/dashboard')->assertRedirect('/admin/login')->assertHeader('X-Robots-Tag', 'noindex');

        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertIsString($robots);
        $this->assertStringNotContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Sitemap: https://www.royalindianstones.com/sitemap.xml', $robots);
    }

    public function test_enquiry_is_stored_and_keeps_quote_fields(): void
    {
        $payload = [
            'name' => 'Asha Sharma',
            'email' => 'asha@example.com',
            'subject' => 'Natural stone quotation',
            'message' => 'We need pink sandstone tiles for a courtyard.',
            'product' => 'Jodhpur Pink Sandstone',
            'quantity' => '200 m2',
            'dimensions' => '60 x 60 cm',
            'finish' => 'Honed',
            'destination' => 'United Kingdom',
        ];

        $this->postJson('/contact', $payload)
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'Your enquiry has been received. Thank you for sharing your requirements.',
            ]);

        $this->assertDatabaseHas('enquiries', [
            'email' => 'asha@example.com',
            'product' => 'Jodhpur Pink Sandstone',
            'quantity' => '200 m2',
            'destination' => 'United Kingdom',
        ]);
        $this->assertSame(1, Enquiry::query()->count());
    }

    public function test_enquiry_rejects_a_short_message(): void
    {
        $this->postJson('/contact', [
            'name' => 'Asha Sharma',
            'email' => 'asha@example.com',
            'subject' => 'Natural stone quotation',
            'message' => 'Too short',
        ])->assertStatus(422)->assertJson([
            'ok' => false,
            'message' => 'Please describe your requirements in at least 10 characters.',
        ]);

        $this->assertSame(0, Enquiry::query()->count());
    }

    public function test_the_blog_is_in_the_menu_and_recent_notes_sit_above_the_gallery(): void
    {
        Post::query()->create([
            'slug' => 'sandstone-floors',
            'title' => 'Choosing sandstone for floors',
            'summary' => 'A short note on colour and finish.',
            'body' => '<p>Sandstone for courtyards.</p>',
            'is_published' => true,
            'published_on' => '2024-05-01',
        ]);
        Post::query()->create([
            'slug' => 'hidden-note',
            'title' => 'Unpublished quarry note',
            'body' => '<p>Not ready.</p>',
            'is_published' => false,
        ]);

        $home = $this->get('/');
        $home->assertOk()
            ->assertSee('Choosing sandstone for floors')
            ->assertSee('Read the blog')
            ->assertDontSee('Unpublished quarry note');

        $html = $home->getContent();
        $journal = strpos($html, 'id="journal-title"');
        $projects = strpos($html, 'id="projects-title"');
        $navStart = strpos($html, 'aria-label="Main navigation"');
        $navEnd = strpos($html, '</nav>', $navStart);
        $nav = substr($html, $navStart, $navEnd - $navStart);
        $this->assertNotFalse($journal);
        $this->assertNotFalse($projects);
        $this->assertLessThan($projects, $journal);
        $this->assertSame(1, substr_count($nav, '>Blog</a>'));
        $this->assertLessThan(strpos($nav, '>Resources<'), strpos($nav, '>Blog</a>'));
        $this->assertSame(1, substr_count($nav, url('/stone-articles')));
        $this->assertLessThan(strpos($nav, '>About Us</a>'), strpos($nav, '>Stone Articles</a>'));
        $this->assertGreaterThan(strpos($nav, '>Resources<'), strpos($nav, url('/project-gallery')));

        $this->get('/blog')
            ->assertOk()
            ->assertSee('href="'.url('/blog').'" class="active"', false);

        $this->get('/sandstone-floors')
            ->assertOk()
            ->assertSee('href="'.url('/blog').'" class="active"', false);

        Category::query()->create([
            'name' => 'Stone Articles',
            'slug' => 'stone-articles',
            'position' => 0,
        ]);

        $this->get('/stone-articles')
            ->assertOk()
            ->assertSee('href="'.url('/stone-articles').'" class="active"', false);

        $this->get('/project-gallery')
            ->assertOk()
            ->assertSee('href="'.url('/project-gallery').'" class="active"', false);
    }

    public function test_header_search_opens_the_catalogue(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('aria-controls="header-search-panel"', false)
            ->assertSee('action="'.route('catalogue').'" method="get"', false)
            ->assertSee('name="q"', false);
    }

    public function test_newsletter_subscription_is_stored(): void
    {
        $this->postJson('/newsletter', ['email_address' => 'buyer@example.com'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue(NewsletterSubscriber::query()->where('email', 'buyer@example.com')->exists());
    }
}
