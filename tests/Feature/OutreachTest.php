<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OutreachMention;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login_and_cannot_save_a_mention(): void
    {
        $this->get(route('admin.outreach.index'))->assertRedirect('/admin/login');

        $this->post(route('admin.outreach.mentions.store'), [
            'publication' => 'Stone World',
            'status' => 'sent',
        ])->assertRedirect('/admin/login');

        $this->assertDatabaseCount('outreach_mentions', 0);
    }

    public function test_visitor_cannot_open_outreach(): void
    {
        $visitor = User::factory()->create();
        $visitor->forceFill(['is_site_admin' => false])->save();

        $this->actingAs($visitor)->get(route('admin.outreach.index'))->assertForbidden();
    }

    public function test_pages_tab_lists_a_stone_whose_title_repeats_trade_words(): void
    {
        $category = $this->sandstone();
        $stone = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Trade Sandstone',
            'slug' => 'trade-sandstone',
            'color' => 'Buff',
            'finishes' => ['Natural'],
            'applications' => ['Paving'],
            'summary' => 'A buff sandstone.',
            'meta_title' => 'Trade Sandstone, Manufacturer, Supplier, Exporter from India',
            'meta_description' => 'Trade Sandstone',
            'is_published' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.outreach.index'))
            ->assertOk()
            ->assertSee('Trade Sandstone')
            ->assertSee('Title uses supplier words')
            ->assertSee('manufacturer, supplier, exporter')
            ->assertSee('Trade Sandstone, Manufacturer, Supplier, Exporter from India')
            ->assertSee('Description under 50 characters')
            ->assertSee('15 characters')
            ->assertSee('is a sandstone in Buff')
            ->assertSee('No photograph')
            ->assertSee(route('admin.products.edit', $stone), false);
    }

    public function test_pages_tab_omits_a_stone_with_a_clear_title_description_and_photograph(): void
    {
        $category = $this->sandstone();
        $stone = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Courtyard Mint',
            'slug' => 'courtyard-mint',
            'color' => 'Green',
            'finishes' => ['Natural'],
            'applications' => ['Paving'],
            'summary' => 'Mint sandstone for courtyards.',
            'meta_title' => 'Mint sandstone for courtyard paving',
            'meta_description' => 'Mint sandstone from Jodhpur, supplied for courtyard paving and garden paths.',
            'is_published' => true,
        ]);
        ProductImage::query()->create([
            'product_id' => $stone->id,
            'path' => 'assets/img/stones/mint.jpg',
            'alt' => 'Mint sandstone paving',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.outreach.index'))
            ->assertOk()
            ->assertDontSee('Courtyard Mint');
    }

    public function test_pages_tab_can_focus_on_titles_that_are_too_long(): void
    {
        $category = $this->sandstone();
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Trade Sandstone',
            'slug' => 'trade-sandstone',
            'color' => 'Buff',
            'finishes' => ['Natural'],
            'applications' => ['Paving'],
            'summary' => 'A buff sandstone.',
            'meta_title' => 'Trade Sandstone, Manufacturer and Supplier',
            'meta_description' => 'Buff sandstone from Jodhpur, supplied for courtyard paving and garden paths.',
            'is_published' => true,
        ]);
        ProductImage::query()->create([
            'product_id' => Product::query()->where('slug', 'trade-sandstone')->value('id'),
            'path' => 'assets/img/stones/trade.jpg',
            'alt' => 'Buff sandstone',
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Long Title Stone',
            'slug' => 'long-title-stone',
            'color' => 'Grey',
            'finishes' => ['Honed'],
            'applications' => ['Cladding'],
            'summary' => 'A grey stone.',
            'meta_title' => 'Long Title Stone, Manufacturer, Supplier, Exporter, Wholesaler of Natural Stone from India',
            'meta_description' => 'Grey stone from Jodhpur, cut for exterior cladding and boundary walls.',
            'is_published' => true,
        ]);
        ProductImage::query()->create([
            'product_id' => Product::query()->where('slug', 'long-title-stone')->value('id'),
            'path' => 'assets/img/stones/long.jpg',
            'alt' => 'Grey stone cladding',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.outreach.index', ['gap' => 'title-length']))
            ->assertOk()
            ->assertSee('Long Title Stone')
            ->assertSee('Title over 70 characters')
            ->assertSee('90 characters')
            ->assertDontSee('Trade Sandstone');
    }

    public function test_pages_tab_says_when_a_gap_filter_matches_nothing(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.outreach.index', ['gap' => 'hidden']))
            ->assertOk()
            ->assertSee('No warnings in this problem.')
            ->assertDontSee('Every public page has a title, description, and photograph.');
    }

    public function test_pages_tab_lists_a_hidden_stone(): void
    {
        $category = $this->sandstone();
        $stone = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Hidden Limestone',
            'slug' => 'hidden-limestone',
            'color' => 'Grey',
            'finishes' => ['Honed'],
            'applications' => ['Cladding'],
            'summary' => 'Grey limestone for cladding.',
            'meta_title' => 'Grey limestone for exterior cladding',
            'meta_description' => 'Grey limestone from Jodhpur, cut for exterior cladding and boundary walls.',
            'is_published' => false,
        ]);
        ProductImage::query()->create([
            'product_id' => $stone->id,
            'path' => 'assets/img/stones/limestone.jpg',
            'alt' => 'Grey limestone cladding',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.outreach.index'))
            ->assertOk()
            ->assertSee('Hidden Limestone')
            ->assertSee('Hidden from the catalogue')
            ->assertSee('Left off the public catalogue and the sitemap.');
    }

    public function test_pages_tab_lists_a_journal_note_without_a_search_description(): void
    {
        $post = Post::query()->create([
            'slug' => 'basalt-courtyard-note',
            'title' => 'Basalt courtyard note',
            'summary' => 'A note on basalt from the Jodhpur yard, written for architects specifying paving.',
            'body' => '<p>Basalt from the yard.</p>',
            'is_published' => true,
            'status' => 'published',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.outreach.index'))
            ->assertOk()
            ->assertSee('Basalt courtyard note')
            ->assertSee('Journal has no search description')
            ->assertSee('No search description')
            ->assertSee('A note on basalt from the Jodhpur yard, written for architects specifying paving.')
            ->assertSee(route('admin.posts.edit', $post), false);
    }

    public function test_pages_tab_asks_for_alt_text_when_a_featured_photograph_has_none(): void
    {
        Post::query()->create([
            'slug' => 'yard-photograph-note',
            'title' => 'Yard photograph note',
            'summary' => 'A photograph of the yard where mint sandstone is cut and stacked for export.',
            'meta_description' => 'A photograph of mint sandstone stacked in the Jodhpur yard before export.',
            'body' => '<p>The yard photograph.</p>',
            'image' => 'assets/img/journal/yard.jpg',
            'featured_image_alt' => '',
            'is_published' => true,
            'status' => 'published',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.outreach.index'))
            ->assertOk()
            ->assertSee('Yard photograph note')
            ->assertSee('Journal photo has no description')
            ->assertSee('No description on the photograph')
            ->assertDontSee('No search description');
    }

    public function test_ignoring_a_warning_hides_it_until_it_is_restored(): void
    {
        $category = $this->sandstone();
        $stone = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Kept Sandstone',
            'slug' => 'kept-sandstone',
            'color' => 'Buff',
            'finishes' => ['Natural'],
            'applications' => ['Paving'],
            'summary' => 'A buff sandstone.',
            'meta_title' => 'Kept Sandstone, Manufacturer and Supplier',
            'meta_description' => 'Buff sandstone from Jodhpur, supplied for courtyard paving and garden paths.',
            'is_published' => true,
        ]);
        ProductImage::query()->create([
            'product_id' => $stone->id,
            'path' => 'assets/img/stones/kept.jpg',
            'alt' => 'Buff sandstone',
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.outreach.warnings.ignore'), [
                'subject_type' => 'product',
                'subject_id' => $stone->id,
                'gap' => 'title-trade',
                'return_gap' => 'title-trade',
            ])
            ->assertRedirect(route('admin.outreach.index', ['gap' => 'title-trade']))
            ->assertSessionHas('status', 'Warning ignored.');

        $this->assertDatabaseHas('outreach_ignored_warnings', [
            'subject_type' => 'product',
            'subject_id' => $stone->id,
            'gap' => 'title-trade',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.outreach.index', ['gap' => 'title-trade']))
            ->assertOk()
            ->assertDontSee('Kept Sandstone')
            ->assertSee('No warnings in this problem.');

        $this->actingAs($admin)
            ->get(route('admin.outreach.index', ['show' => 'ignored', 'gap' => 'title-trade']))
            ->assertOk()
            ->assertSee('Kept Sandstone')
            ->assertSee('manufacturer, supplier')
            ->assertSee('Restore');

        $this->actingAs($admin)
            ->delete(route('admin.outreach.warnings.restore'), [
                'subject_type' => 'product',
                'subject_id' => $stone->id,
                'gap' => 'title-trade',
                'show' => 'ignored',
                'return_gap' => 'title-trade',
            ])
            ->assertRedirect(route('admin.outreach.index', ['show' => 'ignored', 'gap' => 'title-trade']))
            ->assertSessionHas('status', 'Warning restored.');

        $this->assertDatabaseCount('outreach_ignored_warnings', 0);

        $this->actingAs($admin)
            ->get(route('admin.outreach.index', ['gap' => 'title-trade']))
            ->assertOk()
            ->assertSee('Kept Sandstone');
    }

    public function test_guest_cannot_ignore_a_warning_and_a_missing_problem_is_rejected(): void
    {
        $category = $this->sandstone();
        $stone = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Courtyard Mint',
            'slug' => 'courtyard-mint',
            'color' => 'Green',
            'finishes' => ['Natural'],
            'applications' => ['Paving'],
            'summary' => 'Mint sandstone for courtyards.',
            'meta_title' => 'Mint sandstone for courtyard paving',
            'meta_description' => 'Mint sandstone from Jodhpur, supplied for courtyard paving and garden paths.',
            'is_published' => true,
        ]);
        ProductImage::query()->create([
            'product_id' => $stone->id,
            'path' => 'assets/img/stones/mint.jpg',
            'alt' => 'Mint sandstone paving',
        ]);

        $this->post(route('admin.outreach.warnings.ignore'), [
            'subject_type' => 'product',
            'subject_id' => $stone->id,
            'gap' => 'title-trade',
        ])->assertRedirect('/admin/login');

        $visitor = User::factory()->create();
        $visitor->forceFill(['is_site_admin' => false])->save();
        $this->actingAs($visitor)
            ->post(route('admin.outreach.warnings.ignore'), [
                'subject_type' => 'product',
                'subject_id' => $stone->id,
                'gap' => 'title-trade',
            ])
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->from(route('admin.outreach.index'))
            ->post(route('admin.outreach.warnings.ignore'), [
                'subject_type' => 'product',
                'subject_id' => $stone->id,
                'gap' => 'not-a-problem',
            ])
            ->assertRedirect(route('admin.outreach.index'))
            ->assertSessionHasErrors(['gap' => 'Choose a warning from the list.']);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.outreach.warnings.ignore'), [
                'subject_type' => 'product',
                'subject_id' => $stone->id,
                'gap' => 'title-length',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('outreach_ignored_warnings', 0);
    }

    public function test_valid_mention_is_saved_and_a_published_mention_needs_an_address(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.outreach.mentions.store'), [
                'publication' => 'Stone World',
                'contact_name' => 'Mina Rao',
                'offered_url' => '/sandstone-mint',
                'status' => 'sent',
                'note' => 'Offered the mint sandstone page.',
            ])
            ->assertRedirect(route('admin.outreach.mentions.index'))
            ->assertSessionHas('status', 'Mention saved.');

        $this->assertDatabaseHas('outreach_mentions', [
            'publication' => 'Stone World',
            'contact_name' => 'Mina Rao',
            'offered_url' => '/sandstone-mint',
            'status' => 'sent',
            'note' => 'Offered the mint sandstone page.',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.outreach.mentions.index'))
            ->post(route('admin.outreach.mentions.store'), [
                'publication' => 'Architecture Today',
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.outreach.mentions.index'))
            ->assertSessionHasErrors([
                'published_url' => 'Add the address where the piece was published.',
            ]);

        $this->assertDatabaseMissing('outreach_mentions', [
            'publication' => 'Architecture Today',
        ]);
    }

    public function test_mention_rejects_a_script_address_and_escapes_the_publication_name(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.outreach.mentions.index'))
            ->post(route('admin.outreach.mentions.store'), [
                'publication' => 'Stone World',
                'offered_url' => 'javascript:alert(1)',
                'status' => 'to_contact',
            ])
            ->assertRedirect(route('admin.outreach.mentions.index'))
            ->assertSessionHasErrors([
                'offered_url' => 'Enter a page on this site, starting with /, or a full http address.',
            ]);

        $this->assertDatabaseCount('outreach_mentions', 0);

        $this->actingAs($admin)
            ->post(route('admin.outreach.mentions.store'), [
                'publication' => '<script>alert(1)</script>',
                'status' => 'to_contact',
            ])
            ->assertRedirect(route('admin.outreach.mentions.index'));

        $this->actingAs($admin)
            ->get(route('admin.outreach.mentions.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_mention_can_be_marked_published_and_deleted(): void
    {
        $admin = User::factory()->create();
        $mention = OutreachMention::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.outreach.mentions.update', $mention), [
                'publication' => 'Stone World',
                'offered_url' => '/sandstone-mint',
                'status' => 'published',
                'published_url' => 'https://example.com/mint-sandstone',
            ])
            ->assertRedirect(route('admin.outreach.mentions.index'))
            ->assertSessionHas('status', 'Mention updated.');

        $this->assertDatabaseHas('outreach_mentions', [
            'id' => $mention->id,
            'status' => 'published',
            'published_url' => 'https://example.com/mint-sandstone',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.outreach.mentions.destroy', $mention))
            ->assertRedirect(route('admin.outreach.mentions.index'));

        $this->assertModelMissing($mention);
    }

    public function test_profile_rejects_a_bare_word_and_saves_a_full_address(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.outreach.profiles.index'))
            ->post(route('admin.outreach.profiles.store'), [
                'name' => 'Google Business Profile',
                'url' => 'not a url',
                'kind' => 'google_business',
            ])
            ->assertRedirect(route('admin.outreach.profiles.index'))
            ->assertSessionHasErrors([
                'url' => 'Enter a full http address for the profile.',
            ]);

        $this->assertDatabaseCount('outreach_profiles', 0);

        $this->actingAs($admin)
            ->post(route('admin.outreach.profiles.store'), [
                'name' => 'Google Business Profile',
                'url' => 'https://maps.google.com/?cid=1',
                'kind' => 'google_business',
                'last_checked_on' => '2026-10-30',
                'gap' => 'Add the Jodhpur yard photographs.',
            ])
            ->assertRedirect(route('admin.outreach.profiles.index'))
            ->assertSessionHas('status', 'Profile saved.');

        $this->assertDatabaseHas('outreach_profiles', [
            'name' => 'Google Business Profile',
            'kind' => 'google_business',
            'last_checked_on' => '2026-10-30 00:00:00',
            'gap' => 'Add the Jodhpur yard photographs.',
        ]);
    }

    public function test_topic_is_saved_against_one_page(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.outreach.topics.index'))
            ->post(route('admin.outreach.topics.store'), [
                'phrase' => '',
                'intent' => 'buy',
            ])
            ->assertRedirect(route('admin.outreach.topics.index'))
            ->assertSessionHasErrors([
                'phrase' => 'Write the phrase a buyer types.',
            ]);

        $this->actingAs($admin)
            ->post(route('admin.outreach.topics.store'), [
                'phrase' => 'mint sandstone tiles',
                'intent' => 'specify',
                'target_url' => '/sandstone-mint',
                'country' => 'United Kingdom',
                'note' => 'Architects specifying courtyard paving.',
            ])
            ->assertRedirect(route('admin.outreach.topics.index'))
            ->assertSessionHas('status', 'Topic saved.');

        $this->assertDatabaseHas('outreach_topics', [
            'phrase' => 'mint sandstone tiles',
            'intent' => 'specify',
            'target_url' => '/sandstone-mint',
            'country' => 'United Kingdom',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.outreach.topics.index'))
            ->assertOk()
            ->assertSee('mint sandstone tiles')
            ->assertSee('Specify')
            ->assertSee('United Kingdom');
    }

    private function sandstone(): Category
    {
        return Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'description' => 'Indian sandstone.',
        ]);
    }
}
