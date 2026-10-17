<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enquiry;
use App\Models\Faq;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentAndAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_pages_gallery_faq_and_journal_use_existing_slugs(): void
    {
        Page::query()->create([
            'slug' => 'aboutus',
            'title' => 'About us',
            'body' => '<p>Royal Indian Exports Group</p>',
            'nav_group' => 'about',
        ]);
        Page::query()->create([
            'slug' => 'packaging-type-tiles',
            'title' => 'Tile packaging',
            'body' => '<p>Tiles are packed in wooden crates.</p>',
            'nav_group' => 'resources',
        ]);
        Project::query()->create([
            'title' => 'Wall Decoration Project',
            'image' => 'assets/img/projects/example.jpg',
            'lightbox' => 'assets/img/projects/example.jpg',
            'alt' => 'Granite wall',
            'caption' => 'Decorative granite wall cladding.',
            'position' => 0,
        ]);
        Faq::query()->create([
            'question' => 'How to Order ?',
            'answer' => '<p>Email the team with your requirements.</p>',
            'position' => 0,
        ]);
        Post::query()->create([
            'slug' => 'blog-indian-black-basalt-240122',
            'title' => 'Indian Black Basalt',
            'published_on' => '2024-01-22',
            'summary' => 'A note on black basalt.',
            'body' => '<p>Basalt from the Deccan Plateau.</p>',
        ]);

        $this->get('/aboutus')->assertOk()->assertSee('A Jodhpur stone house for buildings and landscapes.')->assertSee('Quality checks')->assertSee('How stone is packed');
        $this->get('/aboutus.html')->assertRedirect('/aboutus');
        $this->get('/packaging-type-tiles')->assertOk()->assertSee('wooden crates');
        $this->get('/project-gallery')->assertOk()->assertSee('Discuss a similar project');
        $this->get('/contact?project=Wall%20Decoration%20Project')->assertOk()->assertSee('Project enquiry: Wall Decoration Project');
        $this->get('/faq')->assertOk()->assertSee('How to Order ?');
        $this->get('/blog')->assertOk()->assertSee('Indian Black Basalt');
        $this->get('/blog-indian-black-basalt-240122')->assertOk()->assertSee('Deccan Plateau');
    }

    public function test_legacy_addresses_redirect_to_the_current_pages(): void
    {
        Post::query()->create([
            'slug' => 'blog-the-tranquil-beauty-and-creative-applications-of-natural-pebbles-240204',
            'title' => 'Natural pebbles',
            'summary' => 'A note on pebbles.',
            'body' => '<p>Pebbles for paths.</p>',
            'published_on' => '2024-02-04',
        ]);

        $this->get('/index')->assertStatus(301)->assertRedirect('/');
        $this->get('/about')->assertStatus(301)->assertRedirect('/aboutus');
        $this->get('/blog-the-tranquil-beauty-and-creative-applications of-natural-pebbles-240204')
            ->assertStatus(301)
            ->assertRedirect('/blog-the-tranquil-beauty-and-creative-applications-of-natural-pebbles-240204');
        $this->get('/blog-the-tranquil-beauty-and-creative-applications-of-natural-pebbles-240204')
            ->assertOk()
            ->assertSee('Pebbles for paths.');
    }

    public function test_sitemap_groups_pages_posts_and_published_products(): void
    {
        Page::query()->create([
            'slug' => 'directors-message',
            'title' => 'DIRECTOR’S MESSAGE',
            'body' => '<p>A note from the director.</p>',
            'nav_group' => 'about',
        ]);
        Page::query()->create([
            'slug' => 'packaging-type-tiles',
            'title' => 'Tile packaging',
            'body' => '<p>Tiles are packed in wooden crates.</p>',
            'nav_group' => 'resources',
        ]);
        Post::query()->create([
            'slug' => 'blog-indian-black-basalt-240122',
            'title' => 'Indian Black Basalt',
            'published_on' => '2024-01-22',
            'summary' => 'A note on black basalt.',
            'body' => '<p>Basalt from the Deccan Plateau.</p>',
        ]);
        $category = Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'position' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Agra Red',
            'slug' => 'sandstone-agra-red',
            'finishes' => [],
            'applications' => [],
            'is_published' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Hidden stone',
            'slug' => 'hidden-stone',
            'finishes' => [],
            'applications' => [],
            'is_published' => false,
        ]);

        $this->get('/sitemap')
            ->assertOk()
            ->assertSee('Start here')
            ->assertSee('About')
            ->assertSee('Director’s Message')
            ->assertSee('Packaging')
            ->assertSee('Tile packaging')
            ->assertSee('22 Jan 2024')
            ->assertSee('Agra Red')
            ->assertDontSee('Hidden stone');
    }

    public function test_admin_lists_and_deletes_an_enquiry(): void
    {
        $admin = User::factory()->create();
        $enquiry = Enquiry::query()->create([
            'name' => 'Asha Sharma',
            'email' => 'asha@example.com',
            'subject' => 'Natural stone quotation',
            'message' => 'Courtyard paving in pink sandstone.',
            'product' => 'Jodhpur Pink Sandstone',
        ]);

        $this->get('/admin/enquiries')->assertRedirect('/admin/login');
        $this->actingAs($admin)->get('/admin/enquiries')->assertOk()->assertSee('asha@example.com');
        $this->actingAs($admin)->delete('/admin/enquiries/'.$enquiry->id)->assertRedirect('/admin/enquiries');
        $this->assertModelMissing($enquiry);
    }

    public function test_admin_lists_newsletter_subscribers(): void
    {
        $admin = User::factory()->create();
        NewsletterSubscriber::query()->create(['email' => 'buyer@example.com']);

        $this->actingAs($admin)->get('/admin/newsletter')->assertOk()->assertSee('buyer@example.com');
    }

    public function test_comment_stays_hidden_until_approved_and_sitemap_lists_products(): void
    {
        $post = Post::query()->create([
            'slug' => 'blog-note',
            'title' => 'A journal note',
            'summary' => 'Short note',
            'body' => '<p>Article body</p>',
            'published_on' => '2024-02-01',
        ]);

        $this->post('/blog-note/comments', [
            'name' => 'Ravi',
            'email' => 'ravi@example.com',
            'body' => 'This stone would suit a courtyard.',
        ])->assertRedirect();

        $this->get('/blog-note')->assertOk()->assertDontSee('This stone would suit a courtyard.');

        $comment = $post->comments()->first();
        $this->actingAs(User::factory()->create())->post('/admin/comments/'.$comment->id.'/approve');
        $this->get('/blog-note')->assertSee('This stone would suit a courtyard.');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/blog-note', false);
    }
}
